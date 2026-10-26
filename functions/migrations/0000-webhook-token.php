<?php
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly

require_once( 'abstract.php' );

/**
 * Generates the secret token required on the Chatwoot webhook and macro URLs.
 * Chatwoot itself is updated later by Disciple_Tools_Chatwoot_Webhook_Security on admin_init,
 * so a Chatwoot outage can never hold the migration lock.
 */
class DT_Chatwoot_Migration_0000 extends DT_Chatwoot_Migration {
    public function up() {
        $settings = get_option( 'dt_chatwoot', array() );
        if ( empty( $settings['webhook_token'] ) ) {
            $settings['webhook_token'] = wp_generate_password( 32, false );
            update_option( 'dt_chatwoot', $settings );
        }
    }

    public function down() {
    }

    public function test() {
    }
}
