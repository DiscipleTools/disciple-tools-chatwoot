<?php
if ( !defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly.

class Disciple_Tools_Chatwoot_API
{
    const INBOX_NAMES_OPTION = 'dt_chatwoot_inbox_names';

    private static function get_chatwoot_url() {
        $settings = get_option( 'dt_chatwoot', array() );
        $url = isset( $settings['url'] ) ? $settings['url'] : '';
        return self::is_allowed_url( $url ) ? $url : '';
    }

    /**
     * Chatwoot is only contacted over https; private addresses are allowed for self-hosted instances.
     */
    public static function is_allowed_url( $url ) {
        return is_string( $url )
            && strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) ) === 'https'
            && !empty( wp_parse_url( $url, PHP_URL_HOST ) );
    }

    private static function get_chatwoot_api_key() {
        $settings = get_option( 'dt_chatwoot', array() );
        return isset( $settings['api_key'] ) ? $settings['api_key'] : '';
    }

    public static function get_default_assigned_user() {
        $settings = get_option( 'dt_chatwoot', array() );
        return isset( $settings['default_assigned_user'] ) ? $settings['default_assigned_user'] : '';
    }

    public static function is_summarize_with_ai_enabled() {
        $settings = get_option( 'dt_chatwoot', array() );

        if ( isset( $settings['summarize_with_ai'] ) ) {
            return (bool) $settings['summarize_with_ai'];
        }

        return false;
    }

    public static function is_extract_contact_with_ai_enabled() {
        $settings = get_option( 'dt_chatwoot', array() );

        if ( isset( $settings['extract_contact_with_ai'] ) ) {
            return (bool) $settings['extract_contact_with_ai'];
        }

        return false;
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
            'redirection' => 0,
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
            'redirection' => 0,
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

    /**
     * @return array|false Every message of the conversation, or false if any page could not be fetched.
     */
    public static function get_full_conversation( $account_id, $conversation_id ) {
        $chatwoot_url = self::get_chatwoot_url();
        if ( empty( $chatwoot_url ) ){
            dt_write_log( 'Chatwoot URL is not set' );
            return false;
        }

        $chatwoot_api_token = self::get_chatwoot_api_key();
        if ( empty( $chatwoot_api_token ) ){
            dt_write_log( 'Chatwoot API token is not set' );
            return false;
        }

        $headers = array(
            'Content-Type' => 'application/json',
            'api_access_token' => $chatwoot_api_token,
        );

        $all_messages = array();
        $before_id = null;
        $page = 1;

        do {
            $api_url = $chatwoot_url . '/api/v1/accounts/' . $account_id . '/conversations/' . $conversation_id . '/messages';

            if ( $before_id !== null ) {
                // Reference: https://github.com/orgs/chatwoot/discussions/2496
                $api_url .= '?before=' . intval( $before_id );
            }

            $response = wp_remote_get( $api_url, array(
                'headers' => $headers,
                'redirection' => 0,
                'timeout' => 30
            ));

            if ( is_wp_error( $response ) ) {
                dt_write_log( 'Error fetching conversation (page ' . $page . '): ' . $response->get_error_message() );
                return false;
            }

            $response_code = wp_remote_retrieve_response_code( $response );
            if ( $response_code !== 200 ) {
                dt_write_log( 'Failed to fetch conversation (page ' . $page . '). Response code: ' . $response_code );
                return false;
            }

            $body = wp_remote_retrieve_body( $response );
            $data = json_decode( $body, true );

            if ( json_last_error() !== JSON_ERROR_NONE ) {
                dt_write_log( 'Error decoding JSON response (page ' . $page . '): ' . json_last_error_msg() );
                return false;
            }

            $messages = isset( $data['payload'] ) && is_array( $data['payload'] ) ? $data['payload'] : array();

            if ( empty( $messages ) ) {
                break;
            }

            // Add messages and collect IDs
            $message_ids = array();
            foreach ( $messages as $message ) {
                $all_messages[] = $message;
                $message_id = isset( $message['id'] ) ? intval( $message['id'] ) : null;
                if ( $message_id !== null ) {
                    $message_ids[] = $message_id;
                }
            }

            // Get the minimum ID (oldest message) for the next page
            $oldest_id = !empty( $message_ids ) ? min( $message_ids ) : null;

            if ( $oldest_id === null ) {
                break;
            }

            // Every page must reach further back than the one before, so the paging always ends.
            if ( $before_id !== null && $oldest_id >= $before_id ) {
                dt_write_log( 'Chatwoot returned a page that does not go further back in conversation ' . $conversation_id );
                return false;
            }
            $before_id = $oldest_id;

            // If we got fewer than 20 messages, we've reached the end
            if ( count( $messages ) < 20 ) {
                break;
            }

            $page++;
        } while ( true );
        if ( !empty( $all_messages ) ) {
            dt_write_log( 'Successfully fetched ' . count( $all_messages ) . ' messages across ' . $page . ' page(s) for conversation ' . $conversation_id );
        }

        return $all_messages;
    }

    public static function get_conversation_custom_attributes( $account_id, $conversation_id ) {
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

        $api_url = $chatwoot_url . '/api/v1/accounts/' . intval( $account_id ) . '/conversations/' . intval( $conversation_id );

        $response = wp_remote_get( $api_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'api_access_token' => $chatwoot_api_key,
            ),
            'redirection' => 0,
            'timeout' => 30
        ));

        if ( is_wp_error( $response ) ) {
            dt_write_log( 'Error fetching Chatwoot conversation: ' . $response->get_error_message() );
            return false;
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        if ( $response_code === 404 ) {
            return array();
        }
        if ( $response_code !== 200 ) {
            dt_write_log( 'Failed to fetch Chatwoot conversation. Response code: ' . $response_code );
            return false;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        return isset( $data['custom_attributes'] ) && is_array( $data['custom_attributes'] ) ? $data['custom_attributes'] : array();
    }

    private static function get_json( $api_path ) {
        $chatwoot_url = self::get_chatwoot_url();
        $chatwoot_api_key = self::get_chatwoot_api_key();
        if ( empty( $chatwoot_url ) || empty( $chatwoot_api_key ) ){
            dt_write_log( 'Chatwoot URL or API key is not set' );
            return false;
        }

        $response = wp_remote_get( $chatwoot_url . $api_path, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'api_access_token' => $chatwoot_api_key,
            ),
            'redirection' => 0,
            'timeout' => 30
        ));

        if ( is_wp_error( $response ) ) {
            dt_write_log( 'Error fetching ' . $api_path . ': ' . $response->get_error_message() );
            return false;
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        if ( $response_code !== 200 ) {
            dt_write_log( 'Failed to fetch ' . $api_path . '. Response code: ' . $response_code );
            return false;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        return is_array( $data ) ? $data : false;
    }

    public static function get_conversation( $account_id, $conversation_id ) {
        return self::get_json( '/api/v1/accounts/' . intval( $account_id ) . '/conversations/' . intval( $conversation_id ) );
    }

    /**
     * @return int[] ids of the conversations Chatwoot holds for a contact.
     */
    public static function get_contact_conversation_ids( $account_id, $contact_id ) {
        $contact_id = intval( $contact_id );
        if ( $contact_id <= 0 ) {
            return array();
        }

        $data = self::get_json( '/api/v1/accounts/' . intval( $account_id ) . '/contacts/' . $contact_id . '/conversations' );
        $conversations = isset( $data['payload'] ) && is_array( $data['payload'] ) ? $data['payload'] : array();
        $ids = array();
        foreach ( $conversations as $conversation ) {
            if ( isset( $conversation['id'] ) && intval( $conversation['id'] ) > 0 ) {
                $ids[] = intval( $conversation['id'] );
            }
        }
        return $ids;
    }

    /**
     * Find a message in a conversation. Chatwoot returns the page of messages older than "before", so asking for
     * the page before the next id returns the page that ends with this message.
     */
    public static function get_conversation_message( $account_id, $conversation_id, $message_id ) {
        $message_id = intval( $message_id );
        if ( $message_id <= 0 ) {
            return false;
        }

        $data = self::get_json( '/api/v1/accounts/' . intval( $account_id ) . '/conversations/' . intval( $conversation_id ) . '/messages?before=' . ( $message_id + 1 ) );
        $messages = isset( $data['payload'] ) && is_array( $data['payload'] ) ? $data['payload'] : array();
        foreach ( $messages as $message ) {
            if ( isset( $message['id'] ) && intval( $message['id'] ) === $message_id ) {
                return $message;
            }
        }
        return false;
    }

    public static function get_inbox_name( $account_id, $inbox_id ) {
        $inbox_id = intval( $inbox_id );
        if ( empty( $account_id ) || $inbox_id <= 0 ) {
            return null;
        }

        $inbox_names = self::get_cached_inbox_names();
        if ( !empty( $inbox_names[ $inbox_id ] ) ) {
            return $inbox_names[ $inbox_id ];
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

        $api_url = $chatwoot_url . '/api/v1/accounts/' . intval( $account_id ) . '/inboxes/' . $inbox_id;

        $response = wp_remote_get( $api_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'api_access_token' => $chatwoot_api_key,
            ),
            'redirection' => 0,
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

        $inbox_names = self::get_cached_inbox_names();
        $inbox_names[ $inbox_id ] = $inbox_data['name'];
        update_option( self::INBOX_NAMES_OPTION, $inbox_names, false );

        return $inbox_data['name'];
    }

    /**
     * Inbox names are cached in their own option, so caching never rewrites the settings that hold the API key and webhook token.
     */
    private static function get_cached_inbox_names() {
        $inbox_names = get_option( self::INBOX_NAMES_OPTION, array() );
        return is_array( $inbox_names ) ? $inbox_names : array();
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

        // Get all accounts
        $accounts = self::get_available_accounts( $chatwoot_url, $chatwoot_api_key );
        if ( empty( $accounts ) ) {
            return false;
        }

        // If only one account, auto-select it
        if ( count( $accounts ) === 1 ) {
            $account_id = intval( $accounts[0]['id'] );
            $settings['account_id'] = $account_id;
            update_option( 'dt_chatwoot', $settings );
            return $account_id;
        }

        // Multiple accounts - check if stored account is still valid
        $stored_account_id = isset( $settings['account_id'] ) ? intval( $settings['account_id'] ) : 0;
        if ( $stored_account_id > 0 ) {
            foreach ( $accounts as $account ) {
                if ( intval( $account['id'] ) === $stored_account_id ) {
                    // Stored account is still valid
                    return $stored_account_id;
                }
            }
            // Stored account is no longer valid, clear it
            unset( $settings['account_id'] );
            update_option( 'dt_chatwoot', $settings );
        }

        // Multiple accounts and no valid selection - user must select one
        return false;
    }

    public static function get_available_accounts( $chatwoot_url = null, $api_key = null ) {
        if ( $chatwoot_url === null ) {
            $chatwoot_url = self::get_chatwoot_url();
        }
        if ( $api_key === null ) {
            $api_key = self::get_chatwoot_api_key();
        }

        if ( empty( $chatwoot_url ) || empty( $api_key ) || !self::is_allowed_url( $chatwoot_url ) ) {
            return array();
        }

        $api_url = untrailingslashit( $chatwoot_url ) . '/api/v1/profile';

        $response = wp_remote_get( $api_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'api_access_token' => $api_key,
            ),
            'redirection' => 0,
            'timeout' => 30
        ));

        if ( is_wp_error( $response ) ) {
            dt_write_log( 'Error fetching user profile: ' . $response->get_error_message() );
            return array();
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        if ( $response_code !== 200 ) {
            dt_write_log( 'Failed to fetch user profile. Response code: ' . $response_code );
            return array();
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( json_last_error() !== JSON_ERROR_NONE || empty( $data ) ) {
            dt_write_log( 'Error parsing user profile response' );
            return array();
        }

        if ( isset( $data['accounts'] ) && is_array( $data['accounts'] ) ) {
            return $data['accounts'];
        }

        return array();
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
            'redirection' => 0,
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
        $inbox_names = self::get_cached_inbox_names();
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

            if ( !isset( $inbox_names[ $inbox_id ] ) || $inbox_names[ $inbox_id ] !== $name ) {
                $inbox_names[ $inbox_id ] = $name;
                $updated_cache = true;
            }
        }

        if ( $updated_cache ) {
            update_option( self::INBOX_NAMES_OPTION, $inbox_names, false );
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
        // This method is deprecated - use get_available_accounts() instead
        $accounts = self::get_available_accounts( $chatwoot_url, $api_key );
        if ( !empty( $accounts ) ) {
            return intval( $accounts[0]['id'] );
        }
        return false;
    }
}
