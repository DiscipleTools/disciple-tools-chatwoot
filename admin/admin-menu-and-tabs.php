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
                </tbody>
            </table>
        </form>
        
        <!-- Integration Setup Section -->
        <div style="background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); margin-top: 20px; padding: 0;">
            <div style="background: #f1f1f1; border-bottom: 1px solid #ccd0d4; padding: 15px 20px;">
                <h3 style="margin: 0; display: flex; align-items: center;">
                    <span style="margin-right: 10px; font-size: 18px;"><?php echo $integration_setup ? '⚡' : '🔗'; ?></span>
                    Integration Setup
                </h3>
            </div>
            <div style="padding: 20px;">
                <form method="post">
                    <?php wp_nonce_field( 'dt_admin_form', 'dt_admin_form_nonce' ) ?>
                    
                    <div>
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
                            <h4 style="margin: 0; color: #23282d;">
                                <?php echo $integration_setup ? 'Integration Status' : 'Chatwoot Integration'; ?>
                            </h4>
                            <?php if ( $integration_setup ): ?>
                                <button type="submit" name="enable-integration" value="1" class="button" style="
                                    background: #6c757d; 
                                    border-color: #5a6268; 
                                    color: #fff;
                                    font-size: 12px;
                                    padding: 6px 12px;
                                    border-radius: 4px;
                                    text-decoration: none;
                                    cursor: pointer;
                                " onmouseover="this.style.background='#5a6268'" 
                                   onmouseout="this.style.background='#6c757d'">
                                    ⚙️ Reconfigure
                                </button>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ( $integration_setup ): ?>
                            <div style="background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; padding: 15px; margin-bottom: 15px;">
                                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                                    <span style="color: #155724; font-weight: 500; font-size: 16px;">✅ Integration Active</span>
                                </div>
                                <p style="margin: 0; color: #155724; font-size: 14px;">Your Chatwoot instance is connected and synchronized</p>
                            </div>
                        <?php else: ?>
                            <div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; padding: 15px; margin-bottom: 15px;">
                                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                                    <span style="color: #856404; font-weight: 500; font-size: 16px;">⚠️ Setup Required</span>
                                </div>
                                <p style="margin: 0; color: #856404; font-size: 14px;">Click the button below to set up your Chatwoot integration</p>
                            </div>
                        <?php endif; ?>
                        
                        <div style="background: #f8f9fa; border-radius: 4px; padding: 15px; margin-bottom: <?php echo $integration_setup ? '0' : '20px'; ?>;">
                            <p style="margin: 0 0 10px 0; font-weight: 500; color: #495057;">
                                <?php echo $integration_setup ? 'Configured Components:' : 'Components to be created:'; ?>
                            </p>
                            <ul style="margin: 0; padding-left: 20px; color: #6c757d;">
                                <li style="margin-bottom: 5px;">
                                    <strong>"dt-sync" label</strong> - Marks conversations for synchronization
                                </li>
                                <li style="margin-bottom: 5px;">
                                    <strong>"Sync with D.T" macro</strong> - One-click labeling for agents
                                </li>
                                <li style="margin-bottom: 5px;">
                                    <strong>Webhook</strong> - Real-time message synchronization
                                </li>
                                <li style="margin-bottom: 5px;">
                                    <strong>Contact ID attribute</strong> - Stores Disciple.Tools contact ID
                                </li>
                                <li style="margin-bottom: 0;">
                                    <strong>Contact URL attribute</strong> - Links to Disciple.Tools contact
                                </li>
                            </ul>
                        </div>
                        
                        <?php if ( !$integration_setup ): ?>
                            <div style="text-align: center;">
                                <button type="submit" name="enable-integration" value="1" class="button" style="
                                    background: #00a32a; 
                                    border-color: #007f23;
                                    color: #fff;
                                    font-size: 16px;
                                    height: auto;
                                    padding: 15px 30px;
                                    border-radius: 8px;
                                    box-shadow: 0 3px 6px rgba(0,0,0,0.1);
                                    transition: all 0.2s ease;
                                    cursor: pointer;
                                    font-weight: 600;
                                    width: 100%;
                                    max-width: 300px;
                                " onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 12px rgba(0,0,0,0.15)'; this.style.background='#028a22'" 
                                   onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 3px 6px rgba(0,0,0,0.1)'; this.style.background='#00a32a'">
                                    🚀 Enable Integration Now
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        
        <table style="display: none;"><tbody><tr><td>
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

        // Create custom attributes
        $contact_id_result = $this->create_custom_attribute( $chatwoot_url, $chatwoot_api_key, $account_id, 'contact_id', 'number', 'Contact ID' );
        if ( $contact_id_result !== true ) {
            return 'Failed to create contact_id custom attribute: ' . $contact_id_result;
        }

        $contact_url_result = $this->create_custom_attribute( $chatwoot_url, $chatwoot_api_key, $account_id, 'contact_url', 'link', 'Contact URL' );
        if ( $contact_url_result !== true ) {
            return 'Failed to create contact_url custom attribute: ' . $contact_url_result;
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
        $webhook_url = rest_url( 'dt-public/chatwoot/v1/sync' ) . '?trigger=true';
        
        $data = array(
            'name' => 'Sync with D.T',
            'visibility' => 'global',
            'actions' => array(
                array(
                    'action_name' => 'add_label',
                    'action_params' => array('dt-sync')
                ),
                array(
                    'action_name' => 'send_webhook_event',
                    'action_params' => array($webhook_url)
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
                //'conversation_updated',
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

    private function create_custom_attribute( $chatwoot_url, $api_key, $account_id, $attribute_key, $attribute_type, $attribute_display_name ) {
        $api_url = $chatwoot_url . '/api/v1/accounts/' . $account_id . '/custom_attribute_definitions';
        
        $data = array(
            'attribute_display_name' => $attribute_display_name,
            'attribute_key' => $attribute_key,
            'attribute_display_type' => $attribute_type,
            'attribute_model' => 'contact_attribute'
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
        
        // If custom attribute already exists, that's OK
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

