<?php
if ( !defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly.

/**
 * Add additional conversation types for Chatwoot integration
 */
class Disciple_Tools_Chatwoot_Conversations_Setup {

    public function __construct() {
        add_filter( 'dt_custom_fields_settings', [ $this, 'dt_custom_fields_settings' ], 250, 2 );
    }

    /**
     * Add additional conversation types to support Chatwoot channels
     */
    public function dt_custom_fields_settings( $fields, $post_type ) {
        if ( $post_type === 'conversations' && isset( $fields['type'] ) ) {
            
            // Add additional conversation types for Chatwoot channels
            $additional_types = [
                'web_chat' => [
                    'label' => __( 'Web Chat', 'disciple-tools-chatwoot' ),
                    'description' => __( 'Web widget conversation', 'disciple-tools-chatwoot' ),
                ],
                'whatsapp' => [
                    'label' => __( 'WhatsApp', 'disciple-tools-chatwoot' ),
                    'description' => __( 'WhatsApp conversation', 'disciple-tools-chatwoot' ),
                ],
                'instagram' => [
                    'label' => __( 'Instagram', 'disciple-tools-chatwoot' ),
                    'description' => __( 'Instagram Direct Message conversation', 'disciple-tools-chatwoot' ),
                ],
            ];

            // Merge with existing types
            $fields['type']['default'] = array_merge( $fields['type']['default'], $additional_types );
        }

        return $fields;
    }
}

new Disciple_Tools_Chatwoot_Conversations_Setup();