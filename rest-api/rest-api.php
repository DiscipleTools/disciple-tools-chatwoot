<?php
if ( !defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly.

class Disciple_Tools_Chatwoot_Endpoints
{
    private function get_chatwoot_url() {
        return get_option( 'dt_chatwoot_url', '' );
    }
    private function get_chatwoot_api_key() {
        return get_option( 'dt_chatwoot_api_key', '' );
    }

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
            $formatted_params['account_id'] = $params['messages'][0]['account_id'] ?? null;
            $formatted_params['last_message'] = $params['messages'][0] ?? null;
            $formatted_params['dt_contact_id'] = $sender['custom_attributes']['contact_id'] ?? null;
            $formatted_params['dt_contact_url'] = $sender['custom_attributes']['contact_url'] ?? null;
            $formatted_params['dt_conversation_id'] = $sender['custom_attributes']['conversation_id'] ?? null;
            $formatted_params['dt_conversation_url'] = $sender['custom_attributes']['conversation_url'] ?? null;
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
            $formatted_params['last_message'] = $params['conversation']['messages'][0] ?? null;
            $formatted_params['dt_contact_id'] = $sender['custom_attributes']['contact_id'] ?? null;
            $formatted_params['dt_contact_url'] = $sender['custom_attributes']['contact_url'] ?? null;
            $formatted_params['dt_conversation_id'] = $sender['custom_attributes']['conversation_id'] ?? null;
            $formatted_params['dt_conversation_url'] = $sender['custom_attributes']['conversation_url'] ?? null;
            $formatted_params['labels'] = $params['conversation']['labels'] ?? null;
        }

        return $formatted_params;
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

        //@todo handle the case when the contact is already created in D.T
        if ( !empty( $params['dt_contact_id'] ) ){
            return;
        }

        return $this->create_contact( $params );
    }

    private function handle_message_created( $params ) {
        $labels = $params['labels'];

        //if `dt_sync` label present
        if ( empty( $labels ) || !in_array( 'dt-sync', $labels ) ){
            return;
        }

        // Check if contact ID exists in Chatwoot custom attributes
        if ( empty( $params['dt_contact_id'] ) ) {
            // No D.T contact ID found, don't create new contact
            dt_write_log( 'No contact_id found in Chatwoot custom attributes, skipping message sync' );
            return;
        }

        $contact_id = (int) $params['dt_contact_id'];

        // Verify the contact exists in D.T
        $contact = DT_Posts::get_post( 'contacts', $contact_id, false, false );
        if ( is_wp_error( $contact ) || empty( $contact ) ) {
            dt_write_log( 'Contact ID ' . $contact_id . ' not found in D.T, skipping message sync' );
            return;
        }

        // Add the new message as a comment
        $this->add_message_to_contact( $contact_id, $params['last_message'] );

        return true;
    }

    private function handle_conversation_updated( $params ) {
        return true;
    }

    private function get_full_conversation( $account_id, $conversation_id ) {
        $chatwoot_url = $this->get_chatwoot_url();
        if ( empty( $chatwoot_url ) ){
            dt_write_log( 'Chatwoot URL is not set' );
            return false;
        }
        $api_url = $chatwoot_url . '/api/v1/accounts/' . $account_id . '/conversations/' . $conversation_id . '/messages';
        
        $chatwoot_api_token = $this->get_chatwoot_api_key();
        if ( empty( $chatwoot_api_token ) ){
            dt_write_log( 'Chatwoot API token is not set' );
            return false;
        }

        $headers = array(
            'Content-Type' => 'application/json',
            'api_access_token' => $chatwoot_api_token,
        );

        $response = wp_remote_get( $api_url, array(
            'headers' => $headers,
            'timeout' => 30
        ));

        if ( is_wp_error( $response ) ) {
            dt_write_log( 'Error fetching conversation: ' . $response->get_error_message() );
            return false;
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        if ( $response_code !== 200 ) {
            dt_write_log( 'Failed to fetch conversation. Response code: ' . $response_code );
            return false;
        }

        $body = wp_remote_retrieve_body( $response );
        $messages = json_decode( $body, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            dt_write_log( 'Error decoding JSON response: ' . json_last_error_msg() );
            return false;
        }

        return $messages['payload'];
    }

    private function create_contact( $params ) {

        $contact_fields = [
            'title' => $params['sender_name'],
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

        //find or create contact
        $contact = DT_Posts::create_post( 'contacts', $contact_fields, true, false );
        if ( is_wp_error( $contact ) ){
            dt_write_log( $contact );
            return;
        }
        $contact_id = $contact['ID'];
        $contact_url = $contact['permalink'];

        $this->set_contact_dt_link( $contact_id, $contact_url, $params );

        $full_conversation = $this->get_full_conversation( $params['account_id'], $params['conversation_id'] );
        if ( empty( $full_conversation ) ){
            return;
        }
        $this->save_messages_to_contact( $contact_id, $full_conversation );

        return $full_conversation;
    }

    private function set_contact_dt_link( $contact_id, $contact_url, $params) {
        //set the D.T contact id and url in chatwoot
        $chatwoot_url = $this->get_chatwoot_url();
        if ( empty( $chatwoot_url ) ){
            dt_write_log( 'Chatwoot URL is not set' );
            return false;
        }
        
        $chatwoot_api_key = $this->get_chatwoot_api_key();
        if ( empty( $chatwoot_api_key ) ){
            dt_write_log( 'Chatwoot API key is not set' );
            return false;
        }

        // Update contact with custom attributes
        $api_url = $chatwoot_url . '/api/v1/accounts/' . $params['account_id'] . '/contacts/' . $params['sender']['id'];
        
        $data = array(
            'custom_attributes' => array(
                'dt_contact_id' => (int) $contact_id,
                'dt_contact_url' => $contact_url
            )
        );

        $response = wp_remote_request( $api_url, array(
            'method' => 'PATCH',
            'headers' => array(
                'Content-Type' => 'application/json',
                'api_access_token' => $chatwoot_api_key,
            ),
            'body' => json_encode( $data ),
            'timeout' => 30
        ));

        if ( is_wp_error( $response ) ) {
            dt_write_log( 'Error updating Chatwoot contact: ' . $response->get_error_message() );
            return false;
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        if ( $response_code !== 200 ) {
            $body = wp_remote_retrieve_body( $response );
            dt_write_log( 'Failed to update Chatwoot contact. Response code: ' . $response_code . ', Body: ' . $body );
            return false;
        }

        dt_write_log( 'Successfully updated Chatwoot contact with D.T contact ID: ' . $contact_id );
        return true;
    }

    private function save_messages_to_contact( $contact_id, $conversation_messages ) {
        if ( empty( $conversation_messages ) || !is_array( $conversation_messages ) ) {
            dt_write_log( 'No messages to save for contact: ' . $contact_id );
            return;
        }

        $saved_count = 0;
        foreach ( $conversation_messages as $message ) {
            if ( $this->add_message_to_contact( $contact_id, $message ) ) {
                $saved_count++;
            }
        }

        dt_write_log( 'Successfully saved ' . $saved_count . ' messages to contact: ' . $contact_id );
    }

    private function add_message_to_contact( $contact_id, $message_params ) {
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

        // Create comment on D.T contact (matching save_messages_to_contact pattern)
        $result = DT_Posts::add_post_comment( 
            'contacts', 
            $contact_id, 
            $message_params['content'], 
            'chatwoot', 
            ['comment_date' => $message_time, 'comment_author' => $sender_name], 
            false, 
            true 
        );
        
        if ( is_wp_error( $result ) ) {
            dt_write_log( 'Failed to add comment to contact ' . $contact_id . ': ' . $result->get_error_message() );
            return false;
        }

        return true;
    }
}
new Disciple_Tools_Chatwoot_Endpoints();
