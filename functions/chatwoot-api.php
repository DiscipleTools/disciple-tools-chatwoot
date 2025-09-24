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

    public static function get_inbox_name( $account_id, $inbox_id ) {
        if ( empty( $account_id ) || empty( $inbox_id ) ) {
            return null;
        }

        $settings = get_option( 'dt_chatwoot', array() );
        if ( isset( $settings['inbox_names'][ $inbox_id ] ) && !empty( $settings['inbox_names'][ $inbox_id ] ) ) {
            return $settings['inbox_names'][ $inbox_id ];
        }

        $chatwoot_url = self::get_chatwoot_url();
        if ( empty( $chatwoot_url ) ) {
            dt_write_log( 'Chatwoot URL is not set' );
            return null;
        }

        $chatwoot_api_key = self::get_chatwoot_api_key();
        if ( empty( $chatwoot_api_key ) ) {
            dt_write_log( 'Chatwoot API key is not set' );
            return null;
        }

        $api_url = $chatwoot_url . '/api/v1/accounts/' . intval( $account_id ) . '/inboxes/' . intval( $inbox_id );

        $response = wp_remote_get( $api_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'api_access_token' => $chatwoot_api_key,
            ),
            'timeout' => 30
        ));

        if ( is_wp_error( $response ) ) {
            dt_write_log( 'Error fetching Chatwoot inbox: ' . $response->get_error_message() );
            return null;
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        if ( $response_code !== 200 ) {
            dt_write_log( 'Failed to fetch Chatwoot inbox. Response code: ' . $response_code );
            return null;
        }

        $body = wp_remote_retrieve_body( $response );
        $inbox = json_decode( $body, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            dt_write_log( 'Error decoding Chatwoot inbox response: ' . json_last_error_msg() );
            return null;
        }

        $inbox_data = isset( $inbox['inbox'] ) && is_array( $inbox['inbox'] ) ? $inbox['inbox'] : $inbox;
        if ( empty( $inbox_data['name'] ) ) {
            return null;
        }

        if ( !isset( $settings['inbox_names'] ) || !is_array( $settings['inbox_names'] ) ) {
            $settings['inbox_names'] = array();
        }

        $settings['inbox_names'][ $inbox_id ] = $inbox_data['name'];
        update_option( 'dt_chatwoot', $settings );

        return $settings['inbox_names'][ $inbox_id ];
    }

    public static function get_account_id( $force_refresh = false ) {
        $settings = get_option( 'dt_chatwoot', array() );

        if ( !$force_refresh && isset( $settings['account_id'] ) && intval( $settings['account_id'] ) > 0 ) {
            return intval( $settings['account_id'] );
        }

        $chatwoot_url = self::get_chatwoot_url();
        if ( empty( $chatwoot_url ) ) {
            dt_write_log( 'Chatwoot URL is not set' );
            return false;
        }

        $chatwoot_api_key = self::get_chatwoot_api_key();
        if ( empty( $chatwoot_api_key ) ) {
            dt_write_log( 'Chatwoot API key is not set' );
            return false;
        }

        $account_id = self::fetch_account_id_from_api( $chatwoot_url, $chatwoot_api_key );
        if ( !$account_id ) {
            return false;
        }

        $settings['account_id'] = intval( $account_id );
        update_option( 'dt_chatwoot', $settings );

        return intval( $account_id );
    }

    public static function get_chatwoot_inboxes( $force_refresh = false ) {
        $chatwoot_url = self::get_chatwoot_url();
        if ( empty( $chatwoot_url ) ) {
            return new WP_Error( 'chatwoot_credentials_missing', __( 'Chatwoot URL is not set.', 'disciple-tools-chatwoot' ) );
        }

        $chatwoot_api_key = self::get_chatwoot_api_key();
        if ( empty( $chatwoot_api_key ) ) {
            return new WP_Error( 'chatwoot_credentials_missing', __( 'Chatwoot API key is not set.', 'disciple-tools-chatwoot' ) );
        }

        $account_id = self::get_account_id( $force_refresh );
        if ( !$account_id ) {
            return new WP_Error( 'chatwoot_account_unavailable', __( 'Unable to determine the Chatwoot account ID.', 'disciple-tools-chatwoot' ) );
        }

        $api_url = untrailingslashit( $chatwoot_url ) . '/api/v1/accounts/' . $account_id . '/inboxes';
        $response = wp_remote_get( $api_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'api_access_token' => $chatwoot_api_key,
            ),
            'timeout' => 30,
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        if ( 200 !== intval( $response_code ) ) {
            $body = wp_remote_retrieve_body( $response );
            return new WP_Error(
                'chatwoot_inboxes_http_' . intval( $response_code ),
                sprintf(
                    /* translators: %d is the HTTP error code. */
                    __( 'Failed to fetch Chatwoot inboxes (HTTP %d).', 'disciple-tools-chatwoot' ),
                    intval( $response_code )
                ),
                array( 'body' => $body )
            );
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( JSON_ERROR_NONE !== json_last_error() ) {
            return new WP_Error(
                'chatwoot_inboxes_json',
                sprintf(
                    /* translators: %s is the JSON parse error message. */
                    __( 'Error parsing inbox response: %s', 'disciple-tools-chatwoot' ),
                    json_last_error_msg()
                )
            );
        }

        $payload = array();
        if ( isset( $data['payload'] ) && is_array( $data['payload'] ) ) {
            $payload = $data['payload'];
        } elseif ( is_array( $data ) ) {
            $payload = $data;
        }

        $inboxes = array();
        $settings = get_option( 'dt_chatwoot', array() );
        $updated_cache = false;

        foreach ( $payload as $inbox ) {
            if ( !isset( $inbox['id'] ) ) {
                continue;
            }

            $inbox_id = intval( $inbox['id'] );
            $name = isset( $inbox['name'] ) ? $inbox['name'] : sprintf( __( 'Inbox %d', 'disciple-tools-chatwoot' ), $inbox_id );
            $channel_type = isset( $inbox['channel_type'] ) ? $inbox['channel_type'] : '';

            $inboxes[] = array(
                'id' => $inbox_id,
                'name' => $name,
                'channel_type' => $channel_type,
            );

            if ( !isset( $settings['inbox_names'] ) || !is_array( $settings['inbox_names'] ) ) {
                $settings['inbox_names'] = array();
            }

            if ( !isset( $settings['inbox_names'][ $inbox_id ] ) || $settings['inbox_names'][ $inbox_id ] !== $name ) {
                $settings['inbox_names'][ $inbox_id ] = $name;
                $updated_cache = true;
            }
        }

        if ( $updated_cache ) {
            update_option( 'dt_chatwoot', $settings );
        }

        return $inboxes;
    }

    public static function get_inbox_source( $inbox_id ) {
        if ( empty( $inbox_id ) ) {
            return '';
        }

        $settings = get_option( 'dt_chatwoot', array() );
        if ( empty( $settings['inbox_sources'] ) || ! is_array( $settings['inbox_sources'] ) ) {
            return '';
        }

        $inbox_id = intval( $inbox_id );
        if ( $inbox_id <= 0 || ! isset( $settings['inbox_sources'][ $inbox_id ] ) ) {
            return '';
        }

        return (string) $settings['inbox_sources'][ $inbox_id ];
    }

    private static function fetch_account_id_from_api( $chatwoot_url, $api_key ) {
        $api_url = untrailingslashit( $chatwoot_url ) . '/api/v1/profile';

        $response = wp_remote_get( $api_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'api_access_token' => $api_key,
            ),
            'timeout' => 30
        ));

        if ( is_wp_error( $response ) ) {
            dt_write_log( 'Error fetching user profile: ' . $response->get_error_message() );
            return false;
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        if ( $response_code !== 200 ) {
            dt_write_log( 'Failed to fetch user profile. Response code: ' . $response_code );
            return false;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( json_last_error() !== JSON_ERROR_NONE || empty( $data ) ) {
            dt_write_log( 'Error parsing user profile response' );
            return false;
        }

        if ( isset( $data['accounts'] ) && !empty( $data['accounts'] ) ) {
            return intval( $data['accounts'][0]['id'] );
        }

        dt_write_log( 'No accounts found for user' );
        return false;
    }
}
