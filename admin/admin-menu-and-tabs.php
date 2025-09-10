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

            echo '<div class="notice notice-success"><p>Settings saved successfully!</p></div>';
        }
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

