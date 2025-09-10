<?php
if ( ! defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly

/**
 * Class Disciple_Tools_Chatwoot_Menu
 */
class Disciple_Tools_Chatwoot_Menu {

    public $token = 'dt_chatwoot';
    public $page_title = 'Chatwoot';

    private static $_instance = null;

    /**
     * Disciple_Tools_Chatwoot_Menu Instance
     *
     * Ensures only one instance of Disciple_Tools_Chatwoot_Menu is loaded or can be loaded.
     *
     * @since 0.1.0
     * @static
     * @return Disciple_Tools_Chatwoot_Menu instance
     */
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    } // End instance()


    /**
     * Constructor function.
     * @access  public
     * @since   0.1.0
     */
    public function __construct() {

        add_action( 'admin_menu', array( $this, 'register_menu' ) );

        $this->page_title = __( 'Chatwoot', 'disciple-tools-chatwoot' );
    } // End __construct()


    /**
     * Loads the subnav page
     * @since 0.1
     */
    public function register_menu() {
        $this->page_title = __( 'Chatwoot', 'disciple-tools-chatwoot' );

        add_submenu_page( 'dt_extensions', $this->page_title, $this->page_title, 'manage_dt', $this->token, [ $this, 'content' ] );
    }

    /**
     * Menu stub. Replaced when Disciple.Tools Theme fully loads.
     */
    public function extensions_menu() {}

    /**
     * Builds page contents
     * @since 0.1
     */
    public function content() {

        if ( !current_user_can( 'manage_dt' ) ) { // manage dt is a permission that is specific to Disciple.Tools and allows admins, strategists and dispatchers into the wp-admin
            wp_die( 'You do not have sufficient permissions to access this page.' );
        }

        if ( isset( $_GET['tab'] ) ) {
            $tab = sanitize_key( wp_unslash( $_GET['tab'] ) );
        } else {
            $tab = 'general';
        }

        $link = 'admin.php?page='.$this->token.'&tab=';

        ?>
        <div class="wrap">
            <h2><?php echo esc_html( $this->page_title ) ?></h2>
            <h2 class="nav-tab-wrapper">
                <a href="<?php echo esc_attr( $link ) . 'general' ?>"
                   class="nav-tab <?php echo esc_html( ( $tab == 'general' || !isset( $tab ) ) ? 'nav-tab-active' : '' ); ?>">General</a>
                <a href="<?php echo esc_attr( $link ) . 'second' ?>" class="nav-tab <?php echo esc_html( ( $tab == 'second' ) ? 'nav-tab-active' : '' ); ?>">Second</a>
            </h2>

            <?php
            switch ( $tab ) {
                case 'general':
                    $object = new Disciple_Tools_Chatwoot_Tab_General();
                    $object->content();
                    break;
                case 'second':
                    $object = new Disciple_Tools_Chatwoot_Tab_Second();
                    $object->content();
                    break;
                default:
                    break;
            }
            ?>

        </div><!-- End wrap -->

        <?php
    }
}
Disciple_Tools_Chatwoot_Menu::instance();

/**
 * Class Disciple_Tools_Chatwoot_Tab_General
 */
class Disciple_Tools_Chatwoot_Tab_General {
    public function content() {
        ?>
        <div class="wrap">
            <div id="poststuff">
                <div id="post-body" class="metabox-holder columns-2">
                    <div id="post-body-content">
                        <!-- Main Column -->

                        <?php $this->main_column() ?>

                        <!-- End Main Column -->
                    </div><!-- end post-body-content -->
                    <div id="postbox-container-1" class="postbox-container">
                        <!-- Right Column -->

                        <?php $this->right_column() ?>

                        <!-- End Right Column -->
                    </div><!-- postbox-container 1 -->
                    <div id="postbox-container-2" class="postbox-container">
                    </div><!-- postbox-container 2 -->
                </div><!-- post-body meta box container -->
            </div><!--poststuff end -->
        </div><!-- wrap end -->
        <?php
    }

    public function main_column() {
        $token = Disciple_Tools_Chatwoot_Menu::instance()->token;
        $this->process_form_fields( $token );

        $chatwoot_url = get_option( $token . '_url' );
        $chatwoot_api_key = get_option( $token . '_api_key' );
        $integration_setup = get_option( $token . '_integration_setup', false );
        ?>
        <form method="post">
            <?php wp_nonce_field( 'dt_admin_form', 'dt_admin_form_nonce' ) ?>
            <table class="widefat striped">
                <thead>
                <tr>
                    <th>Chatwoot Settings</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <tr>
                    <td>
                        <label for="chatwoot-url">Chatwoot URL</label>
                    </td>
                    <td>
                        <input type="url" id="chatwoot-url" name="chatwoot-url" placeholder="https://your-chatwoot-instance.com" value="<?php echo esc_attr( $chatwoot_url ) ?>" style="width: 100%; max-width: 400px;">
                        <p class="description">Enter your Chatwoot instance URL (e.g., https://your-chatwoot-instance.com)</p>
                    </td>
                </tr>
                <tr>
                    <td>
                        <label for="chatwoot-api-key">Chatwoot API Key</label>
                    </td>
                    <td>
                        <input type="password" id="chatwoot-api-key" name="chatwoot-api-key" placeholder="<?php echo !empty($chatwoot_api_key) ? 'API key is set' : 'Enter your API key'; ?>" value="" style="width: 100%; max-width: 400px;">
                        <?php if ( !empty( $chatwoot_api_key ) ): ?>
                            <p class="description" style="color: #46b450;">✓ API key is configured</p>
                        <?php else: ?>
                            <p class="description">Enter your Chatwoot API key from your account settings</p>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td>
                        <button class="button button-primary">Save Settings</button>
                    </td>
                    <td></td>
                </tr>
                <?php if ( !empty( $chatwoot_url ) && !empty( $chatwoot_api_key ) ): ?>
                <tr>
                    <td colspan="2">
                        <hr style="margin: 20px 0;">
                        <h3>Integration Setup</h3>
                    </td>
                </tr>
                <tr>
                    <td>
                        <strong><?php echo $integration_setup ? 'Chatwoot Integration Status' : 'Enable Chatwoot Integration'; ?></strong>
                        <?php if ( $integration_setup ): ?>
                            <p class="description" style="color: #46b450;">✓ Integration is configured and active</p>
                            <p class="description">The following components are set up in your Chatwoot instance:</p>
                        <?php else: ?>
                            <p class="description">This will create the necessary components in your Chatwoot instance:</p>
                        <?php endif; ?>
                        <ul style="margin-left: 20px;">
                            <li>• "dt-sync" label</li>
                            <li>• "Sync with D.T" macro</li>
                            <li>• Webhook for real-time synchronization</li>
                        </ul>
                    </td>
                    <td>
                        <button type="submit" name="enable-integration" value="1" class="button button-secondary" style="<?php echo $integration_setup ? 'background: #0073aa; border-color: #0073aa;' : 'background: #00a32a; border-color: #00a32a;'; ?> color: #fff;">
                            <?php echo $integration_setup ? 'Reconfigure Integration' : 'Enable Integration'; ?>
                        </button>
                    </td>
                </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </form>
        <br>
        <?php
    }

    public function process_form_fields( $token ){
        if ( isset( $_POST['dt_admin_form_nonce'] ) &&
            wp_verify_nonce( sanitize_key( wp_unslash( $_POST['dt_admin_form_nonce'] ) ), 'dt_admin_form' ) ) {

            $post_vars = dt_recursive_sanitize_array( $_POST );

            if ( isset( $post_vars['chatwoot-url'] ) ) {
                $chatwoot_url = esc_url_raw( $post_vars['chatwoot-url'] );
                update_option( $token . '_url', $chatwoot_url );
            }

            if ( isset( $post_vars['chatwoot-api-key'] ) && !empty( $post_vars['chatwoot-api-key'] ) ) {
                update_option( $token . '_api_key', sanitize_text_field( $post_vars['chatwoot-api-key'] ) );
            }

            if ( isset( $post_vars['enable-integration'] ) && $post_vars['enable-integration'] == '1' ) {
                $result = $this->setup_chatwoot_integration( $token );
                if ( $result === true ) {
                    echo '<div class="notice notice-success"><p>Integration enabled successfully! Chatwoot components created.</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>Error enabling integration: ' . esc_html( $result ) . '</p></div>';
                }
            } else {
                echo '<div class="notice notice-success"><p>Settings saved successfully!</p></div>';
            }
        }
    }

    private function setup_chatwoot_integration( $token ) {
        $chatwoot_url = get_option( $token . '_url' );
        $chatwoot_api_key = get_option( $token . '_api_key' );

        if ( empty( $chatwoot_url ) || empty( $chatwoot_api_key ) ) {
            return 'Missing Chatwoot URL or API key';
        }

        // Get account ID first (needed for API calls)
        $account_id = $this->get_account_id( $chatwoot_url, $chatwoot_api_key );
        if ( !$account_id ) {
            return 'Could not retrieve account information';
        }

        // Create dt-sync label
        $label_result = $this->create_label( $chatwoot_url, $chatwoot_api_key, $account_id );
        if ( $label_result !== true ) {
            return 'Failed to create label: ' . $label_result;
        }

        // Create macro
        $macro_result = $this->create_macro( $chatwoot_url, $chatwoot_api_key, $account_id );
        if ( $macro_result !== true ) {
            return 'Failed to create macro: ' . $macro_result;
        }

        // Create webhook
        $webhook_result = $this->create_webhook( $chatwoot_url, $chatwoot_api_key, $account_id );
        if ( $webhook_result !== true ) {
            return 'Failed to create webhook: ' . $webhook_result;
        }

        // Save integration setup status
        update_option( $token . '_integration_setup', true );

        return true;
    }

    private function get_account_id( $chatwoot_url, $api_key ) {
        $api_url = $chatwoot_url . '/api/v1/profile';
        
        $response = wp_remote_get( $api_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'api_access_token' => $api_key,
            ),
            'timeout' => 30
        ));

        if ( is_wp_error( $response ) ) {
            dt_write_log( 'Error fetching user profile: ' . $response->get_error_message() );
            return false;
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        if ( $response_code !== 200 ) {
            dt_write_log( 'Failed to fetch user profile. Response code: ' . $response_code );
            return false;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( json_last_error() !== JSON_ERROR_NONE || empty( $data ) ) {
            dt_write_log( 'Error parsing user profile response' );
            return false;
        }

        // Get the first account from the user's accounts
        if ( isset( $data['accounts'] ) && !empty( $data['accounts'] ) ) {
            return $data['accounts'][0]['id'];
        }

        dt_write_log( 'No accounts found for user' );
        return false;
    }

    private function create_label( $chatwoot_url, $api_key, $account_id ) {
        $api_url = $chatwoot_url . '/api/v1/accounts/' . $account_id . '/labels';
        
        $data = array(
            'title' => 'dt-sync',
            'description' => 'Sync conversation with Disciple.Tools',
            'color' => '#1f7ec8'
        );

        $response = wp_remote_post( $api_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'api_access_token' => $api_key,
            ),
            'body' => json_encode( $data ),
            'timeout' => 30
        ));

        if ( is_wp_error( $response ) ) {
            return $response->get_error_message();
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        if ( $response_code === 200 || $response_code === 201 ) {
            return true;
        }

        $body = wp_remote_retrieve_body( $response );
        $error_data = json_decode( $body, true );
        
        // If label already exists, that's OK
        if ( $response_code === 422 && strpos( $body, 'already been taken' ) !== false ) {
            return true;
        }

        return 'HTTP ' . $response_code . ': ' . ( isset( $error_data['message'] ) ? $error_data['message'] : $body );
    }

    private function create_macro( $chatwoot_url, $api_key, $account_id ) {
        // First check if a macro that adds dt-sync label already exists
        if ( $this->dt_sync_macro_exists( $chatwoot_url, $api_key, $account_id ) ) {
            return true;
        }

        $api_url = $chatwoot_url . '/api/v1/accounts/' . $account_id . '/macros';
        
        $data = array(
            'name' => 'Sync with D.T',
            'visibility' => 'global',
            'actions' => array(
                array(
                    'action_name' => 'add_label',
                    'action_params' => array('dt-sync')
                )
            )
        );

        $response = wp_remote_post( $api_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'api_access_token' => $api_key,
            ),
            'body' => json_encode( $data ),
            'timeout' => 30
        ));

        if ( is_wp_error( $response ) ) {
            return $response->get_error_message();
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        if ( $response_code === 200 || $response_code === 201 ) {
            return true;
        }

        $body = wp_remote_retrieve_body( $response );
        $error_data = json_decode( $body, true );

        return 'HTTP ' . $response_code . ': ' . ( isset( $error_data['message'] ) ? $error_data['message'] : $body );
    }

    private function dt_sync_macro_exists( $chatwoot_url, $api_key, $account_id ) {
        $api_url = $chatwoot_url . '/api/v1/accounts/' . $account_id . '/macros';
        
        $response = wp_remote_get( $api_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'api_access_token' => $api_key,
            ),
            'timeout' => 30
        ));

        if ( is_wp_error( $response ) ) {
            dt_write_log( 'Error fetching macros: ' . $response->get_error_message() );
            return false;
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        if ( $response_code !== 200 ) {
            dt_write_log( 'Failed to fetch macros. Response code: ' . $response_code );
            return false;
        }

        $body = wp_remote_retrieve_body( $response );
        $macros = json_decode( $body, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            dt_write_log( 'Error parsing macros response: ' . json_last_error_msg() );
            return false;
        }

        if ( !is_array( $macros ) ) {
            return false;
        }

        // Check if any macro has an action that adds the 'dt-sync' label
        foreach ( $macros['payload'] as $macro ) {
            if ( isset( $macro['actions'] ) && is_array( $macro['actions'] ) ) {
                foreach ( $macro['actions'] as $action ) {
                    if ( isset( $action['action_name'] ) && $action['action_name'] === 'add_label' ) {
                        if ( isset( $action['action_params'] ) && is_array( $action['action_params'] ) ) {
                            if ( in_array( 'dt-sync', $action['action_params'] ) ) {
                                return true;
                            }
                        }
                    }
                }
            }
        }

        return false;
    }

    private function create_webhook( $chatwoot_url, $api_key, $account_id ) {
        $api_url = $chatwoot_url . '/api/v1/accounts/' . $account_id . '/webhooks';
        
        $webhook_url = rest_url( 'dt-public/chatwoot/v1/sync' );
        
        $data = array(
            'url' => $webhook_url,
            'subscriptions' => array(
                'message_created',
                // 'message_updated',
                'conversation_updated',
                // 'conversation_status_changed',
                // 'contact_updated'

            )
        );

        $response = wp_remote_post( $api_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'api_access_token' => $api_key,
            ),
            'body' => json_encode( $data ),
            'timeout' => 30
        ));

        if ( is_wp_error( $response ) ) {
            return $response->get_error_message();
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        if ( $response_code === 200 || $response_code === 201 ) {
            return true;
        }

        $body = wp_remote_retrieve_body( $response );
        $error_data = json_decode( $body, true );
        
        // If webhook already exists, that's OK
        if ( $response_code === 422 && strpos( $body, 'already been taken' ) !== false ) {
            return true;
        }

        return 'HTTP ' . $response_code . ': ' . ( isset( $error_data['message'] ) ? $error_data['message'] : $body );
    }

    public function right_column() {
        ?>
        <!-- Box -->
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>Information</th>
                </tr>
            </thead>
            <tbody>
            <tr>
                <td>
                    Content
                </td>
            </tr>
            </tbody>
        </table>
        <br>
        <!-- End Box -->
        <?php
    }
}


/**
 * Class Disciple_Tools_Chatwoot_Tab_Second
 */
class Disciple_Tools_Chatwoot_Tab_Second {
    public function content() {
        ?>
        <div class="wrap">
            <div id="poststuff">
                <div id="post-body" class="metabox-holder columns-2">
                    <div id="post-body-content">
                        <!-- Main Column -->

                        <?php $this->main_column() ?>

                        <!-- End Main Column -->
                    </div><!-- end post-body-content -->
                    <div id="postbox-container-1" class="postbox-container">
                        <!-- Right Column -->

                        <?php $this->right_column() ?>

                        <!-- End Right Column -->
                    </div><!-- postbox-container 1 -->
                    <div id="postbox-container-2" class="postbox-container">
                    </div><!-- postbox-container 2 -->
                </div><!-- post-body meta box container -->
            </div><!--poststuff end -->
        </div><!-- wrap end -->
        <?php
    }

    public function main_column() {
        ?>
        <!-- Box -->
        <table class="widefat striped">
            <thead>
            <tr>
                <th>Header</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>
                    Content
                </td>
            </tr>
            </tbody>
        </table>
        <br>
        <!-- End Box -->
        <?php
    }

    public function right_column() {
        ?>
        <!-- Box -->
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>Information</th>
                </tr>
            </thead>
            <tbody>
            <tr>
                <td>
                    Content
                </td>
            </tr>
            </tbody>
        </table>
        <br>
        <!-- End Box -->
        <?php
    }
}

