<?php
if ( !defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly.

/**
 * Authenticates requests to the public sync endpoint, and keeps the webhook and macro URLs
 * registered in Chatwoot carrying this site's secret token.
 */
class Disciple_Tools_Chatwoot_Webhook_Security
{
    const SYNC_ROUTE = 'dt-public/chatwoot/v1/sync';
    const SYNC_TRANSIENT = 'dt_chatwoot_webhook_sync';
    const SYNC_ERROR_TRANSIENT = 'dt_chatwoot_webhook_sync_error';

    public function __construct() {
        add_action( 'admin_init', [ $this, 'admin_init' ] );
        add_action( 'admin_notices', [ $this, 'admin_notices' ] );
    }

    private static function get_settings() {
        $settings = get_option( 'dt_chatwoot', array() );
        return is_array( $settings ) ? $settings : array();
    }

    public static function get_sync_url( $for_macro = false ) {
        $settings = self::get_settings();
        $args = array();
        if ( $for_macro ) {
            $args['trigger'] = 'true';
        }
        if ( !empty( $settings['webhook_token'] ) ) {
            $args['token'] = $settings['webhook_token'];
        }
        return add_query_arg( $args, rest_url( self::SYNC_ROUTE ) );
    }

    /**
     * The token is only required once Chatwoot has been updated to send it.
     * Until then the handlers verify every request against the Chatwoot API.
     */
    public static function authorize_request( WP_REST_Request $request ) {
        $settings = self::get_settings();
        if ( empty( $settings['webhook_token_enforced'] ) || empty( $settings['webhook_token'] ) ) {
            return true;
        }
        $query = $request->get_query_params();
        $token = $query['token'] ?? '';
        return is_string( $token ) && hash_equals( $settings['webhook_token'], $token );
    }

    /**
     * Chatwoot (v4.11.2+) signs account webhooks with sha256=HMAC(secret, "{timestamp}.{raw_body}").
     * Macro webhooks are never signed.
     */
    public static function has_valid_signature( WP_REST_Request $request ) {
        $settings = self::get_settings();
        $secret = $settings['webhook_secret'] ?? '';
        $signature = $request->get_header( 'X-Chatwoot-Signature' );
        $timestamp = $request->get_header( 'X-Chatwoot-Timestamp' );

        if ( empty( $secret ) || !is_string( $signature ) || !is_string( $timestamp ) || !ctype_digit( $timestamp ) ) {
            return false;
        }
        if ( abs( time() - (int) $timestamp ) > 5 * MINUTE_IN_SECONDS ) {
            return false;
        }

        $expected = 'sha256=' . hash_hmac( 'sha256', $timestamp . '.' . $request->get_body(), $secret );
        return hash_equals( $expected, $signature );
    }

    public function admin_init() {
        if ( wp_doing_ajax() || !current_user_can( 'manage_dt' ) ) {
            return;
        }
        if ( isset( $_GET['dt_chatwoot_retry_webhook_sync'], $_GET['_wpnonce'] ) &&
            wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), 'dt_chatwoot_retry_webhook_sync' ) ) {
            delete_transient( self::SYNC_TRANSIENT );
        }
        if ( get_transient( self::SYNC_TRANSIENT ) ) {
            return;
        }
        self::sync_chatwoot();
    }

    public function admin_notices() {
        if ( !current_user_can( 'manage_dt' ) ) {
            return;
        }
        $error = get_transient( self::SYNC_ERROR_TRANSIENT );
        if ( empty( $error ) ) {
            return;
        }
        $retry_url = wp_nonce_url( add_query_arg( 'dt_chatwoot_retry_webhook_sync', '1' ), 'dt_chatwoot_retry_webhook_sync' );
        ?>
        <div class="notice notice-warning">
            <p>
                <strong>Disciple.Tools - Chatwoot:</strong>
                <?php esc_html_e( 'Could not add the security token to the Chatwoot webhook and the "Sync with D.T" macro. The Chatwoot API key must belong to a Chatwoot administrator. Until this succeeds, each Chatwoot request is checked against the Chatwoot API.', 'disciple-tools-chatwoot' ); ?>
            </p>
            <p><?php echo esc_html( $error ); ?></p>
            <p><a class="button" href="<?php echo esc_url( $retry_url ); ?>"><?php esc_html_e( 'Retry', 'disciple-tools-chatwoot' ); ?></a></p>
        </div>
        <?php
    }

    /**
     * Points every Chatwoot macro action and webhook that calls this site's sync endpoint at the
     * token URL, stores the webhook signing secret, then starts requiring the token.
     *
     * @return true|string True on success, or an error message.
     */
    public static function sync_chatwoot() {
        $settings = self::get_settings();
        $chatwoot_url = $settings['url'] ?? '';
        $api_key = $settings['api_key'] ?? '';
        $account_id = isset( $settings['account_id'] ) ? intval( $settings['account_id'] ) : 0;

        if ( empty( $chatwoot_url ) || empty( $api_key ) || $account_id <= 0 || empty( $settings['webhook_token'] ) ) {
            set_transient( self::SYNC_TRANSIENT, 1, HOUR_IN_SECONDS );
            delete_transient( self::SYNC_ERROR_TRANSIENT );
            return true;
        }

        $api_base = $chatwoot_url . '/api/v1/accounts/' . $account_id;
        $route_url = rest_url( self::SYNC_ROUTE );
        $macro_url = self::get_sync_url( true );
        $webhook_url = self::get_sync_url();

        $macros = self::request( 'GET', $api_base . '/macros', $api_key );
        if ( is_wp_error( $macros ) ) {
            return self::sync_failed( 'Could not list Chatwoot macros: ' . $macros->get_error_message() );
        }
        $macros = isset( $macros['payload'] ) && is_array( $macros['payload'] ) ? $macros['payload'] : array();
        foreach ( $macros as $macro ) {
            $actions = isset( $macro['actions'] ) && is_array( $macro['actions'] ) ? $macro['actions'] : array();
            $changed = false;
            foreach ( $actions as $index => $action ) {
                $url = $action['action_params'][0] ?? '';
                if ( ( $action['action_name'] ?? '' ) === 'send_webhook_event' && is_string( $url ) && strpos( $url, $route_url ) === 0 && $url !== $macro_url ) {
                    $actions[ $index ]['action_params'][0] = $macro_url;
                    $changed = true;
                }
            }
            if ( !$changed ) {
                continue;
            }
            $updated = self::request( 'PATCH', $api_base . '/macros/' . intval( $macro['id'] ), $api_key, array(
                'name' => $macro['name'] ?? '',
                'visibility' => $macro['visibility'] ?? 'global',
                'actions' => $actions,
            ) );
            if ( is_wp_error( $updated ) ) {
                return self::sync_failed( sprintf( 'Could not update the Chatwoot macro "%s": %s', $macro['name'] ?? '', $updated->get_error_message() ) );
            }
        }

        $webhooks = self::request( 'GET', $api_base . '/webhooks', $api_key );
        if ( is_wp_error( $webhooks ) ) {
            return self::sync_failed( 'Could not list Chatwoot webhooks: ' . $webhooks->get_error_message() );
        }
        $webhooks = isset( $webhooks['payload']['webhooks'] ) && is_array( $webhooks['payload']['webhooks'] ) ? $webhooks['payload']['webhooks'] : array();
        $secret = '';
        foreach ( $webhooks as $webhook ) {
            $url = $webhook['url'] ?? '';
            if ( !is_string( $url ) || strpos( $url, $route_url ) !== 0 ) {
                continue;
            }
            if ( $url !== $webhook_url ) {
                $updated = self::request( 'PATCH', $api_base . '/webhooks/' . intval( $webhook['id'] ), $api_key, array(
                    'webhook' => array(
                        'url' => $webhook_url,
                        'subscriptions' => $webhook['subscriptions'] ?? array( 'message_created' ),
                    ),
                ) );
                if ( is_wp_error( $updated ) ) {
                    return self::sync_failed( 'Could not update the Chatwoot webhook: ' . $updated->get_error_message() );
                }
            }
            if ( !empty( $webhook['secret'] ) && is_string( $webhook['secret'] ) ) {
                $secret = $webhook['secret'];
            }
        }

        $settings = self::get_settings();
        $settings['webhook_token_enforced'] = true;
        if ( $secret !== '' ) {
            $settings['webhook_secret'] = $secret;
        } else {
            unset( $settings['webhook_secret'] );
        }
        update_option( 'dt_chatwoot', $settings );

        set_transient( self::SYNC_TRANSIENT, 1, 12 * HOUR_IN_SECONDS );
        delete_transient( self::SYNC_ERROR_TRANSIENT );
        return true;
    }

    private static function sync_failed( $message ) {
        dt_write_log( 'Chatwoot webhook token sync failed: ' . $message );
        set_transient( self::SYNC_TRANSIENT, 1, HOUR_IN_SECONDS );
        set_transient( self::SYNC_ERROR_TRANSIENT, $message, DAY_IN_SECONDS );
        return $message;
    }

    private static function request( $method, $url, $api_key, $body = null ) {
        $args = array(
            'method' => $method,
            'headers' => array(
                'Content-Type' => 'application/json',
                'api_access_token' => $api_key,
            ),
            'timeout' => 30,
        );
        if ( $body !== null ) {
            $args['body'] = wp_json_encode( $body );
        }

        $response = wp_remote_request( $url, $args );
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );
        if ( $response_code !== 200 ) {
            return new WP_Error( 'chatwoot_request_failed', 'HTTP ' . $response_code . ': ' . substr( wp_strip_all_tags( $response_body ), 0, 200 ) );
        }

        $data = json_decode( $response_body, true );
        if ( !is_array( $data ) ) {
            return new WP_Error( 'chatwoot_request_failed', 'Invalid JSON response' );
        }
        return $data;
    }
}
new Disciple_Tools_Chatwoot_Webhook_Security();
