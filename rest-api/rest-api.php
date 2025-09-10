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
        dt_write_log( $params );

        $labels = $params['conversation']['labels'];

        //if `dt_sync` label present
        if ( empty( $labels ) || !in_array( 'dt-sync', $labels ) ){
            return;
        }


        $massage_type = $params['message_type']; //incomding or outgoing

        $name = $params['conversation']['meta']['sender']['name'];
        $email = $params['conversation']['meta']['sender']['email'];
        $phone = $params['conversation']['meta']['sender']['phone_number'];
        $facebook = $params['conversation']['meta']['sender']['additional_attributes']['social_profiles']['facebook'];
        $chatwoot_conversation_id = $params['conversation']['id'];
        $inbox_id = $params['inbox']['id'];
        $inbox_name = $params['inbox']['name'];
        $account_id = $params['account']['id'];
        $account_name = $params['account']['name'];
        $conversations_link = $this->get_chatwoot_url() . '/app/accounts/' . $account_id . '/conversations/' . $chatwoot_conversation_id;



        $contact_fields = [
            'title' => $name,
        ];
        if ( !empty( $email ) ){
            $contact_fields['contact_email'] = [
                'values' => [
                    ['value' => $email]
                ]
            ];
        }
        if ( !empty( $phone ) ){
            $contact_fields['contact_phone'] = [
                'values' => [
                    ['value' => $phone]
                ]
            ];
        }
        if ( !empty( $facebook ) ){
            $contact_fields['contact_facebook'] = [
                'values' => [
                    ['value' => $facebook]
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

        //get full conversation from chatwoot
        $conversation_messages = $this->get_full_conversation( $account_id, $chatwoot_conversation_id );
        //@todo save messages to contact comments


        //set D.T link on chatwoot conv
        //set D.T contact id

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
}
new Disciple_Tools_Chatwoot_Endpoints();
