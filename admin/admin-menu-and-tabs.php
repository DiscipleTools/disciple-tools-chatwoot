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

        $settings = get_option( $token, array() );
        $chatwoot_url = isset( $settings['url'] ) ? $settings['url'] : '';
        $chatwoot_api_key = isset( $settings['api_key'] ) ? $settings['api_key'] : '';
        $default_assigned_user = isset( $settings['default_assigned_user'] ) ? $settings['default_assigned_user'] : '';
        $integration_setup = isset( $settings['integration_setup'] ) ? $settings['integration_setup'] : false;
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
                        <input type="password" id="chatwoot-api-key" name="chatwoot-api-key" placeholder="<?php echo !empty( $chatwoot_api_key ) ? 'API key is set' : 'Enter your API key'; ?>" value="" style="width: 100%; max-width: 400px;">
                        <?php if ( !empty( $chatwoot_api_key ) ): ?>
                            <p class="description" style="color: #46b450;">✓ API key is configured</p>
                        <?php else : ?>
                            <p class="description">Enter your Chatwoot API key from your account settings</p>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td>
                        <label for="default-assigned-user">Default Assigned User</label>
                    </td>
                    <td>
                        <select id="default-assigned-user" name="default-assigned-user" style="width: 100%; max-width: 400px;">
                            <option value="">Select a user...</option>
                            <?php
                            $users = get_users( array( 'role__in' => array( 'dispatcher', 'strategist', 'multiplier', 'marketer' ) ) );
                            foreach ( $users as $user ) {
                                $selected = ( $default_assigned_user == $user->ID ) ? 'selected' : '';
                                echo '<option value="' . esc_attr( $user->ID ) . '" ' . esc_attr( $selected ) . '>' . esc_html( $user->display_name ) . ' (' . esc_html( $user->user_email ) . ')</option>';
                            }
                            ?>
                        </select>
                        <p class="description">Select the default user to assign new contacts created from Chatwoot conversations</p>
                    </td>
                </tr>
                <tr>
                    <td>
                        <button class="button button-primary">Save Settings</button>
                    </td>
                    <td></td>
                </tr>
            </tbody>
        </table>
        </form>

        <?php if ( empty( $chatwoot_url ) || empty( $chatwoot_api_key ) ) :
            return;
        endif; ?>

        <?php if ( $integration_setup ) : ?>
            <?php $this->render_inbox_source_mapping( $token, $settings ); ?>
        <?php endif; ?>

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
                                    ⚙️ Re-run configuration
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
                        <?php else : ?>
                            <div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; padding: 15px; margin-bottom: 15px;">
                                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                                    <span style="color: #856404; font-weight: 500; font-size: 16px;">⚠️ Setup Required</span>
                                </div>
                                <p style="margin: 0; color: #856404; font-size: 14px;">Click the button below to set up your Chatwoot integration</p>
                            </div>
                        <?php endif; ?>

                        <div style="background: #f8f9fa; border-radius: 4px; padding: 15px; margin-bottom: <?php echo $integration_setup ? '0' : '20px'; ?>;">
                            <p style="margin: 0 0 10px 0; font-weight: 500; color: #495057;">
                              This integration sets up labels, webhooks, macros, and custom attributes in your Chatwoot instance:
                            </p>
                            <ul style="margin: 0; padding-left: 20px; color: #6c757d;">
                                <li style="margin-bottom: 5px;">
                                    <strong>"dt-sync" label</strong> - Marks conversations for synchronization
                                </li>
                                <li style="margin-bottom: 5px;">
                                    <strong>"Sync with D.T" macro</strong> - Applies the "dt-sync" label and creates the contact in Disciple.Tools
                                </li>
                                <li style="margin-bottom: 5px;">
                                    <strong>Webhook</strong> - Real-time message synchronization
                                </li>
                                <li style="margin-bottom: 5px;">
                                    <strong>Contact ID attribute</strong> - Stores Disciple.Tools contact ID
                                </li>
                                <li style="margin-bottom: 5px;">
                                    <strong>Contact URL attribute</strong> - Links to Disciple.Tools contact
                                </li>
                                <li style="margin-bottom: 5px;">
                                    <strong>Conversation ID attribute</strong> - Stores Disciple.Tools conversation ID
                                </li>
                                <li style="margin-bottom: 0;">
                                    <strong>Conversation URL attribute</strong> - Links to Disciple.Tools conversation
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

        <br>
        <?php
    }

    public function process_form_fields( $token ){
        if ( isset( $_POST['dt_admin_form_nonce'] ) &&
            wp_verify_nonce( sanitize_key( wp_unslash( $_POST['dt_admin_form_nonce'] ) ), 'dt_admin_form' ) ) {

            $post_vars = dt_recursive_sanitize_array( $_POST );
            $settings = get_option( $token, array() );
            $action = isset( $post_vars['chatwoot_action'] ) ? sanitize_key( $post_vars['chatwoot_action'] ) : '';

            if ( 'save_inbox_sources' === $action ) {
                $available_sources = $this->get_contact_source_options();
                $valid_source_keys = array_keys( $available_sources );
                $mapped_sources = array();

                if ( isset( $post_vars['chatwoot_inbox_sources'] ) && is_array( $post_vars['chatwoot_inbox_sources'] ) ) {
                    foreach ( $post_vars['chatwoot_inbox_sources'] as $inbox_id => $source_key ) {
                        $inbox_id = intval( $inbox_id );
                        if ( $inbox_id <= 0 ) {
                            continue;
                        }

                        $source_key = sanitize_key( $source_key );
                        if ( empty( $source_key ) ) {
                            continue;
                        }

                        if ( in_array( $source_key, $valid_source_keys, true ) ) {
                            $mapped_sources[ $inbox_id ] = $source_key;
                        }
                    }
                }

                $settings['inbox_sources'] = $mapped_sources;
                update_option( $token, $settings );

                echo '<div class="notice notice-success"><p>' . esc_html__( 'Inbox sources saved successfully.', 'disciple-tools-chatwoot' ) . '</p></div>';
                return;
            }

            if ( isset( $post_vars['chatwoot-url'] ) ) {
                $settings['url'] = esc_url_raw( $post_vars['chatwoot-url'] );
            }

            if ( isset( $post_vars['chatwoot-api-key'] ) && !empty( $post_vars['chatwoot-api-key'] ) ) {
                $settings['api_key'] = sanitize_text_field( $post_vars['chatwoot-api-key'] );
            }

            if ( isset( $post_vars['default-assigned-user'] ) ) {
                $settings['default_assigned_user'] = sanitize_text_field( $post_vars['default-assigned-user'] );
            }

            update_option( $token, $settings );

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

    private function render_inbox_source_mapping( $token, $settings ) {
        $inboxes = Disciple_Tools_Chatwoot_API::get_chatwoot_inboxes();
        $sources = $this->get_contact_source_options();
        $current_mapping = isset( $settings['inbox_sources'] ) && is_array( $settings['inbox_sources'] ) ? $settings['inbox_sources'] : array();

        ?>
        <div style="margin-top: 20px;">
            <div style="background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); padding: 0;">
                <div style="background: #f1f1f1; border-bottom: 1px solid #ccd0d4; padding: 15px 20px;">
                    <h3 style="margin: 0; color: #23282d;"><?php esc_html_e( 'Inbox Source Mapping', 'disciple-tools-chatwoot' ); ?></h3>
                </div>
                <div style="padding: 20px;">
                    <p style="margin-top: 0; color: #495057;">
                        <?php esc_html_e( 'Choose which Disciple.Tools source should be applied to contacts coming from each Chatwoot inbox.', 'disciple-tools-chatwoot' ); ?>
                    </p>
                    <?php if ( is_wp_error( $inboxes ) ) : ?>
                        <div class="notice notice-error" style="margin: 0 0 15px 0;">
                            <p><?php echo esc_html( $inboxes->get_error_message() ); ?></p>
                        </div>
                        <?php
                        $error_data = $inboxes->get_error_data();
                        if ( is_array( $error_data ) && !empty( $error_data['body'] ) ) :
                            ?>
                            <div class="notice notice-error" style="margin: 0;">
                                <p><code><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $error_data['body'] ), 30, '&hellip;' ) ); ?></code></p>
                            </div>
                            <?php
                        endif;
                    elseif ( empty( $inboxes ) ) :
                        ?>
                        <div class="notice notice-info" style="margin: 0;">
                            <p><?php esc_html_e( 'No inboxes were returned from Chatwoot. Create an inbox in Chatwoot to map its Disciple.Tools source.', 'disciple-tools-chatwoot' ); ?></p>
                        </div>
                    <?php elseif ( empty( $sources ) ) : ?>
                        <div class="notice notice-info" style="margin: 0;">
                            <p><?php esc_html_e( 'There are no Disciple.Tools sources available. Add sources under your Disciple.Tools custom lists to enable mapping.', 'disciple-tools-chatwoot' ); ?></p>
                        </div>
                    <?php else : ?>
                        <form method="post">
                            <?php wp_nonce_field( 'dt_admin_form', 'dt_admin_form_nonce' ); ?>
                            <input type="hidden" name="chatwoot_action" value="save_inbox_sources" />
                            <table class="widefat striped">
                                <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Inbox', 'disciple-tools-chatwoot' ); ?></th>
                                    <th><?php esc_html_e( 'Channel', 'disciple-tools-chatwoot' ); ?></th>
                                    <th style="width: 260px;">
                                        <?php esc_html_e( 'Disciple.Tools Source', 'disciple-tools-chatwoot' ); ?>
                                    </th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ( $inboxes as $inbox ) :
                                    $inbox_id = isset( $inbox['id'] ) ? intval( $inbox['id'] ) : 0;
                                    if ( $inbox_id <= 0 ) {
                                        continue;
                                    }
                                    $selected_source = isset( $current_mapping[ $inbox_id ] ) ? $current_mapping[ $inbox_id ] : '';
                                    $channel_type = isset( $inbox['channel_type'] ) ? $inbox['channel_type'] : '';
                                    if ( !empty( $channel_type ) ) {
                                        $channel_label = ucwords( str_replace( '_', ' ', $channel_type ) );
                                    } else {
                                        $channel_label = __( 'Unknown', 'disciple-tools-chatwoot' );
                                    }
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo esc_html( $inbox['name'] ?? sprintf( __( 'Inbox %d', 'disciple-tools-chatwoot' ), $inbox_id ) ); ?></strong>
                                        </td>
                                        <td><?php echo esc_html( $channel_label ); ?></td>
                                        <td>
                                            <select name="chatwoot_inbox_sources[<?php echo esc_attr( $inbox_id ); ?>]" style="width: 100%; max-width: 240px;">
                                                <option value=""><?php esc_html_e( 'Select a source...', 'disciple-tools-chatwoot' ); ?></option>
                                                <?php foreach ( $sources as $source_key => $source_label ) : ?>
                                                    <option value="<?php echo esc_attr( $source_key ); ?>" <?php selected( $selected_source, $source_key ); ?>>
                                                        <?php echo esc_html( $source_label ); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                            <p style="margin-top: 15px;">
                                <button type="submit" class="button button-primary"><?php esc_html_e( 'Save Inbox Sources', 'disciple-tools-chatwoot' ); ?></button>
                            </p>
                        </form>
                        <p style="margin: 0; color: #6c757d; font-size: 13px;">
                            <?php esc_html_e( 'Leave the selection empty to keep new contacts from that inbox without a source.', 'disciple-tools-chatwoot' ); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    private function get_contact_source_options() {
        if ( !class_exists( 'DT_Posts' ) ) {
            return array();
        }

        $post_settings = DT_Posts::get_post_settings( 'contacts' );
        $defaults = isset( $post_settings['fields']['sources']['default'] ) && is_array( $post_settings['fields']['sources']['default'] )
            ? $post_settings['fields']['sources']['default']
            : array();

        $sources = array();
        foreach ( $defaults as $key => $source ) {
            if ( isset( $source['deleted'] ) && $source['deleted'] ) {
                continue;
            }
            if ( isset( $source['enabled'] ) && $source['enabled'] === false ) {
                continue;
            }

            $label = isset( $source['label'] ) && !empty( $source['label'] ) ? $source['label'] : $key;
            $sources[ $key ] = $label;
        }

        natcasesort( $sources );

        return $sources;
    }

    private function setup_chatwoot_integration( $token ) {
        $settings = get_option( $token, array() );
        $chatwoot_url = isset( $settings['url'] ) ? $settings['url'] : '';
        $chatwoot_api_key = isset( $settings['api_key'] ) ? $settings['api_key'] : '';

        if ( empty( $chatwoot_url ) || empty( $chatwoot_api_key ) ) {
            return 'Missing Chatwoot URL or API key';
        }

        // Get account ID first (needed for API calls)
        $account_id = Disciple_Tools_Chatwoot_API::get_account_id( true );
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
        $contact_id_result = $this->create_custom_attribute( $chatwoot_url, $chatwoot_api_key, $account_id, 'dt_contact_id', 'number', 'Contact ID' );
        if ( $contact_id_result !== true ) {
            return 'Failed to create contact_id custom attribute: ' . $contact_id_result;
        }

        $contact_url_result = $this->create_custom_attribute( $chatwoot_url, $chatwoot_api_key, $account_id, 'dt_contact_url', 'link', 'Contact URL' );
        if ( $contact_url_result !== true ) {
            return 'Failed to create contact_url custom attribute: ' . $contact_url_result;
        }
        $conversation_id_result = $this->create_conversation_custom_attribute( $chatwoot_url, $chatwoot_api_key, $account_id, 'dt_conversation_id', 'number', 'Conversation ID' );
        if ( $conversation_id_result !== true ) {
            return 'Failed to create conversation_id custom attribute: ' . $conversation_id_result;
        }
        $conversation_url_result = $this->create_conversation_custom_attribute( $chatwoot_url, $chatwoot_api_key, $account_id, 'dt_conversation_url', 'link', 'Conversation URL' );
        if ( $conversation_url_result !== true ) {
            return 'Failed to create conversation_url custom attribute: ' . $conversation_url_result;
        }

        // Save integration setup status
        $settings['account_id'] = intval( $account_id );
        $settings['integration_setup'] = true;
        update_option( $token, $settings );

        return true;
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
                    'action_params' => array( 'dt-sync' )
                ),
                array(
                    'action_name' => 'send_webhook_event',
                    'action_params' => array( $webhook_url )
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

    private function create_conversation_custom_attribute( $chatwoot_url, $api_key, $account_id, $attribute_key, $attribute_type, $attribute_display_name ) {
        $api_url = $chatwoot_url . '/api/v1/accounts/' . $account_id . '/custom_attribute_definitions';

        $data = array(
            'attribute_display_name' => $attribute_display_name,
            'attribute_key' => $attribute_key,
            'attribute_display_type' => $attribute_type,
            'attribute_model' => 'conversation_attribute'
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

    public static function get_default_assigned_user() {
        $settings = get_option( 'dt_chatwoot', array() );
        return isset( $settings['default_assigned_user'] ) ? $settings['default_assigned_user'] : '';
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
