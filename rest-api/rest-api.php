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
     * Handle macro executed event. The macro is used when declaring that a contact is ready for D.T in chatwoot.
     * @param array $params
     * @return void
     */
    private function handle_macro_executed( $params ) {
        //check if the trigger param is set.
        if ( empty($params['trigger'] ) ){
            return;
        }

        $sender = $params['meta']['sender'];
        $inbox_id = $params['inbox_id'];
        $account_id = '';
        $conversation_id = $params['id'];
        
        if ( !empty( $params['messages'][0]['account_id'] ) ){
            $account_id = $params['messages'][0]['account_id'];
        } else {
            return;
        }

        //@todo handle the case when the contact is already created in D.T
        if ( !empty( $sender['custom_attributes']['contact_id'] ) ){
            return;
        }

        return $this->create_contact( $sender, $account_id, $inbox_id, $conversation_id );
    }

    private function handle_message_created( $params ) {
        $labels = $params['conversation']['labels'];

        //if `dt_sync` label present
        if ( empty( $labels ) || !in_array( 'dt-sync', $labels ) ){
            return;
        }

        // Check if contact ID exists in Chatwoot custom attributes
        $sender = $params['conversation']['meta']['sender'];
        if ( empty( $sender['custom_attributes']['contact_id'] ) ) {
            // No D.T contact ID found, don't create new contact
            dt_write_log( 'No contact_id found in Chatwoot custom attributes, skipping message sync' );
            return;
        }

        $contact_id = (int) $sender['custom_attributes']['contact_id'];

        // Verify the contact exists in D.T
        $contact = DT_Posts::get_post( 'contacts', $contact_id, false, false );
        if ( is_wp_error( $contact ) || empty( $contact ) ) {
            dt_write_log( 'Contact ID ' . $contact_id . ' not found in D.T, skipping message sync' );
            return;
        }

        // Add the new message as a comment
        $this->add_message_to_contact( $contact_id, $params['conversation']['messages'][0] );

        return true;
    }

    private function handle_conversation_updated( $params ) {
        //dt-sync label applied
        $dt_sync_label_applied = false;
        foreach ( $params['changed_attributes'] as $attribute ) {
            if ( isset( $attribute['label_list'] ) && in_array( 'dt-sync', $attribute['label_list']['current_value'] ) && !in_array( 'dt-sync', $attribute['label_list']['previous_value'] ) ){
                $dt_sync_label_applied = true;
            }
        }
        if ( $dt_sync_label_applied ) {
            // $this->handle_message_created( $params );
        }
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

    private function create_contact( $sender, $account_id, $inbox_id, $conversation_id = '' ) {
        $contact_fields = [
            'title' => $sender['name'],
        ];
        if ( !empty( $sender['email'] ) ){
            $contact_fields['contact_email'] = [
                'values' => [
                    ['value' => $sender['email']]
                ]
            ];
        }
        if ( !empty( $sender['phone'] ) ){
            $contact_fields['contact_phone'] = [
                'values' => [
                    ['value' => $sender['phone']]
                ]
            ];
        }
        if ( !empty( $sender['additional_attributes']['social_profiles']['facebook'] ) ){
            $contact_fields['contact_facebook'] = [
                'values' => [
                    ['value' => $sender['additional_attributes']['social_profiles']['facebook']]
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

        $this->set_contact_dt_link( $contact_id, $contact_url, $account_id, $inbox_id, $sender['id'] );

        $full_conversation = $this->get_full_conversation( $account_id, $conversation_id );
        if ( empty( $full_conversation ) ){
            return;
        }
        $this->save_messages_to_contact( $contact_id, $full_conversation );

        return $full_conversation;
    }

    private function set_contact_dt_link( $contact_id, $contact_url, $account_id, $conversation_id, $chatwoot_contact_id ) {
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
        $api_url = $chatwoot_url . '/api/v1/accounts/' . $account_id . '/contacts/' . $chatwoot_contact_id;
        
        $data = array(
            'custom_attributes' => array(
                'contact_id' => (int) $contact_id,
                'contact_url' => $contact_url
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
