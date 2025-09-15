<?php
if ( !defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly.

class Disciple_Tools_Chatwoot_API
{
    private static function get_chatwoot_url() {
        $settings = get_option( 'dt_chatwoot', array() );
        return isset( $settings['url'] ) ? $settings['url'] : '';
    }

    private static function get_chatwoot_api_key() {
        $settings = get_option( 'dt_chatwoot', array() );
        return isset( $settings['api_key'] ) ? $settings['api_key'] : '';
    }

    public static function get_default_assigned_user() {
        $settings = get_option( 'dt_chatwoot', array() );
        return isset( $settings['default_assigned_user'] ) ? $settings['default_assigned_user'] : '';
    }

    public static function set_contact_attributes( $attributes, $account_id, $contact_id ) {
        $chatwoot_url = self::get_chatwoot_url();
        if ( empty( $chatwoot_url ) ){
            dt_write_log( 'Chatwoot URL is not set' );
            return false;
        }

        $chatwoot_api_key = self::get_chatwoot_api_key();
        if ( empty( $chatwoot_api_key ) ){
            dt_write_log( 'Chatwoot API key is not set' );
            return false;
        }

        $api_url = $chatwoot_url . '/api/v1/accounts/' . $account_id . '/contacts/' . $contact_id;

        $data = array(
            'custom_attributes' => $attributes
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
            dt_write_log( 'Error updating Chatwoot contact attributes: ' . $response->get_error_message() );
            return false;
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        if ( $response_code !== 200 ) {
            $body = wp_remote_retrieve_body( $response );
            dt_write_log( 'Failed to update Chatwoot contact attributes. Response code: ' . $response_code . ', Body: ' . $body );
            return false;
        }

        dt_write_log( 'Successfully updated Chatwoot contact attributes for contact ID: ' . $contact_id );
        return true;
    }

    public static function set_conversation_attributes( $attributes, $account_id, $conversation_id ) {
        $chatwoot_url = self::get_chatwoot_url();
        if ( empty( $chatwoot_url ) ){
            dt_write_log( 'Chatwoot URL is not set' );
            return false;
        }

        $chatwoot_api_key = self::get_chatwoot_api_key();
        if ( empty( $chatwoot_api_key ) ){
            dt_write_log( 'Chatwoot API key is not set' );
            return false;
        }

        $api_url = $chatwoot_url . '/api/v1/accounts/' . $account_id . '/conversations/' . $conversation_id . '/custom_attributes';

        $data = array(
            'custom_attributes' => $attributes
        );

        $response = wp_remote_request( $api_url, array(
            'method' => 'POST',
            'headers' => array(
                'Content-Type' => 'application/json',
                'api_access_token' => $chatwoot_api_key,
            ),
            'body' => json_encode( $data ),
            'timeout' => 30
        ));

        if ( is_wp_error( $response ) ) {
            dt_write_log( 'Error updating Chatwoot conversation attributes: ' . $response->get_error_message() );
            return false;
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        if ( $response_code !== 200 ) {
            $body = wp_remote_retrieve_body( $response );
            dt_write_log( 'Failed to update Chatwoot conversation attributes. Response code: ' . $response_code . ', Body: ' . $body );
            return false;
        }

        dt_write_log( 'Successfully updated Chatwoot conversation attributes for conversation ID: ' . $conversation_id );
        return true;
    }

    public static function get_full_conversation( $account_id, $conversation_id ) {
        $chatwoot_url = self::get_chatwoot_url();
        if ( empty( $chatwoot_url ) ){
            dt_write_log( 'Chatwoot URL is not set' );
            return false;
        }

        $api_url = $chatwoot_url . '/api/v1/accounts/' . $account_id . '/conversations/' . $conversation_id . '/messages';

        $chatwoot_api_token = self::get_chatwoot_api_key();
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
