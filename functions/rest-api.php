<?php
if ( !defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly.

class Disciple_Tools_Chatwoot_Endpoints
{
    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'add_api_routes' ] );
    }


    public function add_api_routes() {
        $namespace = 'dt-public/chatwoot/v1';

        register_rest_route(
            $namespace, '/sync', [
                'methods'  => 'POST',
                'callback' => [ $this, 'sync' ],
                'permission_callback' => function( WP_REST_Request $request ) {
                    return true;
                },
            ]
        );
    }


    public function sync( WP_REST_Request $request ) {
        $params = $request->get_params();
        dt_write_log( $params['event'] );


        if ( !isset( $params['event'] ) ){
            return;
        }

        $event = $params['event'];

        $params = $this->format_params( $params );

        switch ( $event ) {
            case 'message_created':
                $this->handle_message_created( $params );
                break;
            case 'conversation_updated':
                $this->handle_conversation_updated( $params );
                break;
            case 'conversation_status_changed':
                break;
            case 'macro.executed':
                $this->handle_macro_executed( $params );
                break;
        }
        return true;
    }


    /**
     * Standardize webhook data into a predictable array format.
     * Converts different webhook types (macro.executed, message_created) into consistent structure.
     * @param array $params Raw webhook parameters
     * @return array Standardized parameters with sender, account, inbox, conversation data
     */

    public function format_params( $params ) {

        $formatted_params = [
            'event' => $params['event'] ?? null,
            'sender' => null,
            'sender_name' => null,
            'sender_email' => null,
            'sender_phone' => null,
            'sender_facebook' => null,
            'account_id' => null,
            'inbox_id' => null,
            'conversation_id' => null,
            'channel' => null,
            'conversation_type' => null,
            'last_message' => null,
            'dt_contact_id' => null,
            'dt_contact_url' => null,
            'dt_conversation_id' => null,
            'dt_conversation_url' => null,
            'trigger' => null,
            'labels' => null,
        ];

        // macro.executed format
        if ( isset( $params['meta']['sender'] ) ) {
            $sender = $params['meta']['sender'];
            $formatted_params['sender'] = $sender;
            $formatted_params['sender_name'] = $sender['name'] ?? null;
            $formatted_params['sender_email'] = $sender['email'] ?? null;
            $formatted_params['sender_phone'] = $sender['phone_number'] ?? null;
            $formatted_params['sender_facebook'] = $sender['additional_attributes']['social_profiles']['facebook'] ?? null;
            $formatted_params['inbox_id'] = $params['inbox_id'] ?? null;
            $formatted_params['conversation_id'] = $params['id'] ?? null;
            $formatted_params['channel'] = $params['channel'] ?? null;
            $formatted_params['account_id'] = $params['messages'][0]['account_id'] ?? null;
            $formatted_params['last_message'] = $params['messages'][0] ?? null;
            $formatted_params['dt_contact_id'] = $sender['custom_attributes']['dt_contact_id'] ?? null;
            $formatted_params['dt_contact_url'] = $sender['custom_attributes']['dt_contact_url'] ?? null;
            $formatted_params['dt_conversation_id'] = $params['custom_attributes']['dt_conversation_id'] ?? null;
            $formatted_params['dt_conversation_url'] = $params['custom_attributes']['dt_conversation_url'] ?? null;
            $formatted_params['conversation_type'] = $this->get_dt_conversation_type( $formatted_params['channel'] );
            $formatted_params['trigger'] = $params['trigger'] ?? null;
        }
        // message_created format  
        elseif ( isset( $params['conversation']['meta']['sender'] ) ) {
            $sender = $params['conversation']['meta']['sender'];
            $formatted_params['sender'] = $sender;
            $formatted_params['sender_name'] = $sender['name'] ?? null;
            $formatted_params['sender_email'] = $sender['email'] ?? null;
            $formatted_params['sender_phone'] = $sender['phone_number'] ?? null;
            $formatted_params['sender_facebook'] = $sender['additional_attributes']['social_profiles']['facebook'] ?? null;
            $formatted_params['account_id'] = $params['account']['id'] ?? null;
            $formatted_params['inbox_id'] = $params['inbox']['id'] ?? null;
            $formatted_params['conversation_id'] = $params['conversation']['id'] ?? null;
            $formatted_params['channel'] = $params['conversation']['channel'] ?? null;
            $formatted_params['last_message'] = $params['conversation']['messages'][0] ?? null;
            $formatted_params['dt_contact_id'] = $sender['custom_attributes']['dt_contact_id'] ?? null;
            $formatted_params['dt_contact_url'] = $sender['custom_attributes']['dt_contact_url'] ?? null;
            $formatted_params['dt_conversation_id'] = $params['conversation']['custom_attributes']['dt_conversation_id'] ?? null;
            $formatted_params['dt_conversation_url'] = $params['conversation']['custom_attributes']['dt_conversation_url'] ?? null;
            $formatted_params['conversation_type'] = $this->get_dt_conversation_type( $formatted_params['channel'] );
            $formatted_params['labels'] = $params['conversation']['labels'] ?? null;
        }

        return $formatted_params;
    }

    /**
     * Map Chatwoot channel type to DT conversation type
     * @param string $channel_type Chatwoot channel type
     * @return string DT conversation type
     */
    private function get_dt_conversation_type( $channel_type ) {
        $type_mapping = [
            'Channel::Email' => 'email',
            'Channel::WebWidget' => 'web_chat',
            'Channel::Api' => 'web_chat',
            'Channel::Sms' => 'sms', 
            'Channel::FacebookPage' => 'facebook',
            'Channel::InstagramDirectMessage' => 'instagram',
            'Channel::Whatsapp' => 'whatsapp',
            'Channel::TelegramBot' => 'telegram',
            'Channel::Line' => 'line',
            'Channel::TwitterProfile' => 'twitter'
        ];

        return $type_mapping[$channel_type] ?? 'chatwoot';
    }

    /**
     * Handle macro executed event. The macro is used when declaring that a contact is ready for D.T in chatwoot.
     * @param array $params
     * @return void
     */
    private function handle_macro_executed( $params ) {
        //check if the trigger param is set.
        if ( empty($params['trigger'] ) ){
            return;
        }
        if ( !class_exists( 'Disciple_Tools_Chatwoot_API' ) ){
            dt_write_log( 'Disciple_Tools_Chatwoot_API class not found' );
            return;
        }

        $contact_id = $params['dt_contact_id'];

        if ( empty( $contact_id ) ){
            $contact = $this->create_contact( $params );
            if ( is_wp_error( $contact ) ){
                dt_write_log( $contact );
                return;
            }
    
            $contact_id = $contact['ID'];
            $contact_url = $contact['permalink'];
            Disciple_Tools_Chatwoot_API::set_contact_attributes( 
                ['dt_contact_id' => $contact_id, 'dt_contact_url' => $contact_url], 
                $params['account_id'], 
                $params['sender']['id'] 
            );
        }

        if ( empty( $params['dt_conversation_id'] ) ){
            $full_conversation = Disciple_Tools_Chatwoot_API::get_full_conversation( $params['account_id'], $params['conversation_id'] );
            if ( empty( $full_conversation ) ){
                return;
            }
    
            $conversation_type = $params['conversation_type'] ?? 'chatwoot';
            dt_write_log( 'Using conversation type: ' . $conversation_type );
            
            $handle = 'chatwoot_' . $params['account_id'] . '_' . $params['conversation_id'];
            $dt_conversation = DT_Conversations_API::create_or_update_conversation_record(
                $handle,
                [
                    'type' => $conversation_type,
                    'status' => 'verified',
                ],
                $contact_id 
            );
            if ( is_wp_error( $dt_conversation ) ){
                dt_write_log( $dt_conversation );
                return;
            }
            $this->save_messages_to_conversation( $dt_conversation['ID'], $full_conversation, $conversation_type );
    
            Disciple_Tools_Chatwoot_API::set_conversation_attributes( 
                ['dt_conversation_id' => $dt_conversation['ID'], 'dt_conversation_url' => $dt_conversation['permalink']], 
                $params['account_id'], 
                $params['conversation_id'] 
            );
        }
        return true;
    }

    private function handle_message_created( $params ) {
        if ( empty( $params['dt_conversation_id'] ) ) {
            dt_write_log( 'No conversation_id found in Chatwoot custom attributes, skipping message sync' );
            return;
        }

        $dt_conversation_id = (int) $params['dt_conversation_id'];

        // Verify the conversation exists in D.T
        $handle = 'chatwoot_' . $params['account_id'] . '_' . $params['conversation_id'];
        $exists = DT_Conversations_API::find_record_by_handle( $handle, true );
        
        if ( is_wp_error( $exists ) || empty( $exists ) ) {
            dt_write_log( 'Conversation ID ' . $dt_conversation_id . ' not found in D.T, skipping message sync' );
            return;
        }

        $this->add_message_to_conversation( $dt_conversation_id, $params['last_message'], $params['conversation_type'] );

        return true;
    }

    private function handle_conversation_updated( $params ) {
        return true;
    }


    private function create_contact( $params ) {

        $contact_fields = [
            'title' => $params['sender_name'],
            'overall_status' => 'unassigned'
        ];
        if ( !empty( $params['sender_email'] ) ){
            $contact_fields['contact_email'] = [
                'values' => [
                    ['value' => $params['sender_email']]
                ]
            ];
        }
        if ( !empty( $params['sender_phone'] ) ){
            $contact_fields['contact_phone'] = [
                'values' => [
                    ['value' => $params['sender_phone']]
                ]
            ];
        }
        if ( !empty( $params['sender_facebook'] ) ){
            $contact_fields['contact_facebook'] = [
                'values' => [
                    ['value' => $params['sender_facebook']]
                ]
            ];
        }

        return DT_Posts::create_post( 'contacts', $contact_fields, true, false, [ 'check_for_duplicates' => [ 'contact_email', 'contact_phone', 'contact_facebook' ] ] );
    }


    private function save_messages_to_conversation( $conversation_id, $conversation_messages, $conversation_type = 'chatwoot' ) {
        if ( empty( $conversation_messages ) || !is_array( $conversation_messages ) ) {
            dt_write_log( 'No messages to save for contact: ' . $conversation_id );
            return;
        }


        $saved_count = 0;
        foreach ( $conversation_messages as $message ) {
            if ( $this->add_message_to_conversation( $conversation_id, $message, $conversation_type ) ) {
                $saved_count++;
            }
        }

        dt_write_log( 'Successfully saved ' . $saved_count . ' messages to conversation: ' . $conversation_id );
    }

    private function add_message_to_conversation( $dt_conversation_id, $message_params, $conversation_type = 'chatwoot' ) {
        // Skip if not a valid message
        if ( empty( $message_params['content'] ) || empty( $message_params['created_at'] ) ) {
            return false;
        }
        
        $message_type = isset( $message_params['message_type'] ) ? $message_params['message_type'] : -1;
        if ( $message_type !== 0 && $message_type !== 1 ) {
            return false;
        }

        $sender_name = isset( $message_params['sender']['name'] ) ? $message_params['sender']['name'] : 'Chatwoot';
        $message_time = gmdate( 'Y-m-d H:i:s', $message_params['created_at'] );

        // Create comment on D.T conversation with the appropriate type
        $result = DT_Posts::add_post_comment( 
            'conversations', 
            $dt_conversation_id, 
            $message_params['content'], 
            $conversation_type, 
            ['comment_date' => $message_time, 'comment_author' => $sender_name], 
            false, 
            true 
        );
        
        if ( is_wp_error( $result ) ) {
            dt_write_log( 'Failed to add comment to conversation ' . $dt_conversation_id . ': ' . $result->get_error_message() );
            return false;
        }

        return true;
    }
}
new Disciple_Tools_Chatwoot_Endpoints();
