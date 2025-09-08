<?php

class PluginTest extends TestCase
{
    public function test_plugin_installed() {
        activate_plugin( 'disciple-tools-chatwoot/disciple-tools-chatwoot.php' );

        $this->assertContains(
            'disciple-tools-chatwoot/disciple-tools-chatwoot.php',
            get_option( 'active_plugins' )
        );
    }
}
