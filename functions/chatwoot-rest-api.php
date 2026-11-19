<?php
if ( !defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly.

class Disciple_Tools_Chatwoot_Endpoints
{
    // Postmeta on a D.T contact holding "<account id>_<Chatwoot contact id>" for each Chatwoot contact mapped to it.
    const CHATWOOT_CONTACT_META = 'chatwoot_contact';
    // Comment meta holding the Chatwoot message id of a message added by message_created.
    const CHATWOOT_MESSAGE_META = 'chatwoot_message_id';

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'add_api_routes' ] );
    }


    public function add_api_routes() {
        $namespace = 'dt-public/chatwoot/v1';

        register_rest_route(
            $namespace, '/sync', [
                'methods'  => 'POST',
                'callback' => [ $this, 'sync' ],
                'permission_callback' => [ 'Disciple_Tools_Chatwoot_Webhook_Security', 'authorize_request' ],
            ]
        );
    }


    public function sync( WP_REST_Request $request ) {
        // Imports run as no user, so a logged-in caller is never made the creator or sharer of the records.
        wp_set_current_user( 0 );

        $params = $request->get_params();


        if ( !isset( $params['event'] ) ){
            return;
        }

        $event = $params['event'];

        $params = Chat_Functions::format_params( $params );

        switch ( $event ) {
            case 'message_created':
                $this->handle_message_created( $params, Disciple_Tools_Chatwoot_Webhook_Security::has_valid_signature( $request ) );
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
        if ( empty( $params['trigger'] ) ){
            return;
        }
        if ( !class_exists( 'Disciple_Tools_Chatwoot_API' ) ){
            dt_write_log( 'Disciple_Tools_Chatwoot_API class not found' );
            return;
        }

        $params = $this->get_verified_macro_params( $params['conversation_id'] );
        if ( empty( $params ) ){
            return;
        }

        $contact_id = $this->get_linked_contact_id( $params );
        $full_conversation = [];

        if ( empty( $contact_id ) ){
            $full_conversation = Disciple_Tools_Chatwoot_API::get_full_conversation( $params['account_id'], $params['conversation_id'] );
            if ( is_wp_error( $full_conversation ) ) {
                dt_write_log( $full_conversation );
                $full_conversation = [];
            } elseif ( empty( $full_conversation ) ) {
                dt_write_log( 'Unable to fetch full conversation for contact extraction' );
                $full_conversation = [];
            }

            $contact = $this->create_contact( $params, is_array( $full_conversation ) ? $full_conversation : [] );
            if ( is_wp_error( $contact ) ){
                dt_write_log( $contact );
                return;
            }

            $contact_id = $contact['ID'];
            $contact_url = $contact['permalink'];
            add_post_meta( $contact_id, self::CHATWOOT_CONTACT_META, $this->get_chatwoot_contact_key( $params ) );
            Disciple_Tools_Chatwoot_API::set_contact_attributes(
                [ 'dt_contact_id' => $contact_id, 'dt_contact_url' => $contact_url ],
                $params['account_id'],
                $params['sender']['id']
            );
        }

        if ( empty( $params['dt_conversation_id'] ) ){
            if ( empty( $full_conversation ) ) {
                $full_conversation = Disciple_Tools_Chatwoot_API::get_full_conversation( $params['account_id'], $params['conversation_id'] );
            }
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

            $summary = [];
            if ( Disciple_Tools_Chatwoot_API::is_summarize_with_ai_enabled() ) {
                $summary_response = Disciple_Tools_Chatwoot_AI::summarize( $full_conversation, $dt_conversation['ID'] );
                if ( is_wp_error( $summary_response ) ) {
                    dt_write_log( $summary_response );
                } else {
                    $summary = $summary_response['summary'];
                }
            }
            //on the contact add a message about this new conversation and the summary
            $this->add_message_to_contact( $contact_id, $params, $summary );

            Disciple_Tools_Chatwoot_API::set_conversation_attributes(
                [ 'dt_conversation_id' => $dt_conversation['ID'], 'dt_conversation_url' => $dt_conversation['permalink'] ],
                $params['account_id'],
                $params['conversation_id']
            );
        }
        return true;
    }

    private function get_chatwoot_contact_key( $params ) {
        return intval( $params['account_id'] ) . '_' . intval( $params['sender']['id'] ?? 0 );
    }

    /**
     * The Chatwoot dt_contact_id attribute is editable by the chat visitor, so it is only trusted when the
     * D.T contact is mapped to this Chatwoot contact, or is already linked to one of its conversations.
     */
    private function get_linked_contact_id( $params ) {
        $contact_id = intval( $params['dt_contact_id'] ?? 0 );
        if ( $contact_id <= 0 || get_post_type( $contact_id ) !== 'contacts' ) {
            return 0;
        }

        $contact_key = $this->get_chatwoot_contact_key( $params );
        if ( in_array( $contact_key, get_post_meta( $contact_id, self::CHATWOOT_CONTACT_META ), true ) ) {
            return $this->get_merged_contact_id( $contact_id );
        }

        $chatwoot_conversation_ids = Disciple_Tools_Chatwoot_API::get_contact_conversation_ids( $params['account_id'], $params['sender']['id'] ?? 0 );
        foreach ( $chatwoot_conversation_ids as $chatwoot_conversation_id ) {
            $dt_conversation_id = (int) DT_Conversations_API::find_record_by_handle( 'chatwoot_' . intval( $params['account_id'] ) . '_' . $chatwoot_conversation_id );
            if ( empty( $dt_conversation_id ) ) {
                continue;
            }
            $dt_conversation = DT_Posts::get_post( 'conversations', $dt_conversation_id, true, false );
            $linked_contact_ids = is_wp_error( $dt_conversation ) ? [] : array_map( 'intval', array_column( $dt_conversation['contacts'] ?? [], 'ID' ) );
            if ( in_array( $contact_id, $linked_contact_ids, true ) ) {
                add_post_meta( $contact_id, self::CHATWOOT_CONTACT_META, $contact_key );
                return $this->get_merged_contact_id( $contact_id );
            }
        }

        dt_write_log( 'Chatwoot dt_contact_id ' . $contact_id . ' is not linked to Chatwoot contact ' . $contact_key . ', creating a new contact' );
        return 0;
    }

    /**
     * D.T closes a merged duplicate with duplicate_of pointing at the contact that remains, so new chats go there.
     */
    private function get_merged_contact_id( $contact_id ) {
        $seen = [];
        while ( !in_array( $contact_id, $seen, true ) ) {
            $seen[] = $contact_id;
            $primary_id = intval( get_post_meta( $contact_id, 'duplicate_of', true ) );
            if ( $primary_id <= 0 || get_post_type( $primary_id ) !== 'contacts' ) {
                break;
            }
            $contact_id = $primary_id;
        }
        return $contact_id;
    }

    /**
     * Rebuild the macro params from the conversation as stored in Chatwoot, so nothing in the
     * unsigned macro request is trusted. Only conversations labelled dt-sync by the macro are accepted.
     */
    private function get_verified_macro_params( $conversation_id ) {
        $account_id = Disciple_Tools_Chatwoot_API::get_account_id();
        $conversation_id = absint( $conversation_id );
        if ( empty( $account_id ) || empty( $conversation_id ) ){
            return false;
        }

        $conversation = Disciple_Tools_Chatwoot_API::get_conversation( $account_id, $conversation_id );
        $labels = isset( $conversation['labels'] ) && is_array( $conversation['labels'] ) ? $conversation['labels'] : [];
        if ( empty( $conversation ) || !in_array( 'dt-sync', $labels, true ) ){
            dt_write_log( 'Chatwoot conversation ' . $conversation_id . ' not found or not labelled dt-sync, skipping macro' );
            return false;
        }

        $conversation['channel'] = $conversation['meta']['channel'] ?? null;
        $conversation['trigger'] = true;
        $params = Chat_Functions::format_params( $conversation );
        if ( empty( $params['conversation_id'] ) || empty( $params['sender']['id'] ) ){
            return false;
        }
        $params['account_id'] = $account_id;
        $params['inbox_name'] = Disciple_Tools_Chatwoot_API::get_inbox_name( $account_id, $params['inbox_id'] );

        return $params;
    }

    /**
     * @param array $params
     * @param bool $is_signed Whether Chatwoot's webhook signature was verified. Unsigned messages are re-fetched from Chatwoot.
     */
    private function handle_message_created( $params, $is_signed = false ) {
        if ( empty( $params['dt_conversation_id'] ) ) {
            dt_write_log( 'No conversation_id found in Chatwoot custom attributes, skipping message sync' );
            return;
        }

        $account_id = Disciple_Tools_Chatwoot_API::get_account_id();
        $conversation_id = absint( $params['conversation_id'] );
        if ( empty( $account_id ) || empty( $conversation_id ) || intval( $params['account_id'] ) !== intval( $account_id ) ) {
            return;
        }

        // Verify the conversation exists in D.T
        $handle = 'chatwoot_' . $account_id . '_' . $conversation_id;
        $dt_conversation_id = (int) DT_Conversations_API::find_record_by_handle( $handle );

        if ( empty( $dt_conversation_id ) ) {
            dt_write_log( 'Conversation ' . $handle . ' not found in D.T, skipping message sync' );
            return;
        }

        // Chatwoot names the D.T record the conversation was synced to, and it must be the record found by handle.
        if ( intval( $params['dt_conversation_id'] ) !== $dt_conversation_id ) {
            dt_write_log( 'Chatwoot conversation ' . $conversation_id . ' is linked to another D.T record, skipping message sync' );
            return;
        }

        $message = $params['last_message'];
        if ( !$is_signed ) {
            $message = Disciple_Tools_Chatwoot_API::get_conversation_message( $account_id, $conversation_id, $message['id'] ?? 0 );
            if ( empty( $message ) ) {
                dt_write_log( 'Message not found in Chatwoot conversation ' . $conversation_id . ', skipping message sync' );
                return;
            }
        }

        $conversation_type = get_post_meta( $dt_conversation_id, 'type', true );
        $this->add_message_to_conversation( $dt_conversation_id, $message, !empty( $conversation_type ) ? $conversation_type : 'chatwoot' );

        return true;
    }

    private function handle_conversation_updated( $params ) {
        return true;
    }


    private function create_contact( $params, array $conversation_messages = [] ) {

        $assigned_to = Disciple_Tools_Chatwoot_API::get_default_assigned_user();

        $sender_name = isset( $params['sender_name'] ) ? sanitize_text_field( $params['sender_name'] ) : 'Chatwoot Contact';

        $contact_fields = [
            'title' => $sender_name,
            'overall_status' => 'unassigned'
        ];
        if ( !empty( $assigned_to ) ){
            $contact_fields['assigned_to'] = $assigned_to;
        }

        if ( !empty( $params['inbox_id'] ) ) {
            $inbox_source = Disciple_Tools_Chatwoot_API::get_inbox_source( $params['inbox_id'] );
            if ( !empty( $inbox_source ) ) {
                $contact_fields['sources'] = [
                    'values' => [
                        [ 'value' => $inbox_source ],
                    ],
                ];
            }
        }

        if ( !empty( $params['sender_email'] ) ){
            $contact_fields['contact_email'] = [
                'values' => [
                    [ 'value' => $params['sender_email'] ]
                ]
            ];
        }

        $ai_contact_details = [];
        if ( Disciple_Tools_Chatwoot_API::is_extract_contact_with_ai_enabled() ) {
            $ai_contact_details = Disciple_Tools_Chatwoot_AI::extract_contact_attributes( $conversation_messages );
            if ( is_wp_error( $ai_contact_details ) ) {
                dt_write_log( $ai_contact_details );
                $ai_contact_details = [];
            }
        }

        if ( is_array( $ai_contact_details ) ) {
            $ai_name = isset( $ai_contact_details['name'] ) && is_string( $ai_contact_details['name'] ) ? sanitize_text_field( $ai_contact_details['name'] ) : '';
            if ( $ai_name !== '' ) {
                $contact_fields['title'] = $ai_name;
            }
        }

        $email_values = [];
        if ( !empty( $params['sender_email'] ) ) {
            $sender_email = sanitize_email( $params['sender_email'] );
            if ( !empty( $sender_email ) ) {
                $email_values[] = $sender_email;
            }
        }
        if ( is_array( $ai_contact_details ) && !empty( $ai_contact_details['emails'] ) && is_array( $ai_contact_details['emails'] ) ) {
            foreach ( $ai_contact_details['emails'] as $email_candidate ) {
                if ( !is_string( $email_candidate ) && !is_numeric( $email_candidate ) ) {
                    continue;
                }
                $email_candidate = sanitize_email( (string) $email_candidate );
                if ( empty( $email_candidate ) ) {
                    continue;
                }
                if ( !in_array( $email_candidate, $email_values, true ) ) {
                    $email_values[] = $email_candidate;
                }
            }
        }
        if ( !empty( $email_values ) ) {
            $contact_fields['contact_email'] = Chat_Functions::array_to_values( $email_values );
        }

        $phone_values = [];
        if ( !empty( $params['sender_phone'] ) ) {
            $normalized_sender_phone = Chat_Functions::normalize_phone_number( $params['sender_phone'] );
            if ( !empty( $normalized_sender_phone ) ) {
                $phone_values[] = $normalized_sender_phone;
            }
        }
        if ( is_array( $ai_contact_details ) && !empty( $ai_contact_details['phone_numbers'] ) && is_array( $ai_contact_details['phone_numbers'] ) ) {
            foreach ( $ai_contact_details['phone_numbers'] as $phone_candidate ) {
                $normalized_phone = Chat_Functions::normalize_phone_number( $phone_candidate );
                if ( ( !is_string( $phone_candidate ) && !is_numeric( $phone_candidate ) ) || empty( $normalized_phone ) ) {
                    continue;
                }
                if ( !in_array( $normalized_phone, $phone_values, true ) ) {
                    $phone_values[] = $normalized_phone;
                }
            }
        }
        if ( !empty( $phone_values ) ) {
            $contact_fields['contact_phone'] = Chat_Functions::array_to_values( $phone_values );
        }

        $address_values = [];
        if ( is_array( $ai_contact_details ) ) {
            $address_sources = [];
            if ( !empty( $ai_contact_details['addresses'] ) && is_array( $ai_contact_details['addresses'] ) ) {
                $address_sources = array_merge( $address_sources, $ai_contact_details['addresses'] );
            }
            if ( empty( $address_sources ) && !empty( $ai_contact_details['locations'] ) && is_array( $ai_contact_details['locations'] ) ) {
                $address_sources = array_merge( $address_sources, $ai_contact_details['locations'] );
            }

            foreach ( $address_sources as $address_candidate ) {
                if ( !is_string( $address_candidate ) && !is_numeric( $address_candidate ) ) {
                    continue;
                }
                $address_candidate = trim( wp_strip_all_tags( (string) $address_candidate ) );
                if ( $address_candidate === '' ) {
                    continue;
                }
                if ( !in_array( $address_candidate, $address_values, true ) ) {
                    $address_values[] = $address_candidate;
                }
            }
        }
        if ( !empty( $address_values ) ) {
            $contact_fields['contact_address'] = Chat_Functions::array_to_values( $address_values );
            foreach ( $contact_fields['contact_address']['values'] as $key => $address ) {
                $contact_fields['contact_address']['values'][$key]['geolocate'] = true;
            }
        }

        if ( is_array( $ai_contact_details ) ) {

            $age_key = Chat_Functions::map_age_value( $ai_contact_details['age'] ?? null );
            if ( $age_key !== '' ) {
                $contact_fields['age'] = $age_key;
            }

            $gender_key = Chat_Functions::map_gender_value( $ai_contact_details['gender'] ?? null );
            if ( $gender_key !== '' ) {
                $contact_fields['gender'] = $gender_key;
            }
        }

        // Chat identifiers are unverified, so never merge into an existing contact; the theme flags possible duplicates for review.
        return DT_Posts::create_post( 'contacts', $contact_fields, true, false );
    }

    private function save_messages_to_conversation( $conversation_id, $conversation_messages, $conversation_type = 'chatwoot' ) {
        if ( empty( $conversation_messages ) || !is_array( $conversation_messages ) ) {
            dt_write_log( 'No messages to save for conversation: ' . $conversation_id );
            return;
        }

        // Use bulk insert for better performance
        $result = $this->insert_bulk_conversation_messages( $conversation_messages, $conversation_id, $conversation_type );

        if ( is_wp_error( $result ) ) {
            dt_write_log( 'Failed to bulk insert messages: ' . $result->get_error_message() );
            return;
        }

        dt_write_log( 'Successfully saved ' . $result . ' messages to conversation: ' . $conversation_id );
    }

    /**
     * Bulk insert conversation messages directly into the database
     * Based on insert_bulk_comments from contacts-transfer.php
     *
     * @param array $messages Array of Chatwoot messages
     * @param int $conversation_id The D.T conversation ID
     * @param string $conversation_type The conversation type (e.g., 'chatwoot', 'whatsapp', etc.)
     * @return int|WP_Error Number of messages inserted or WP_Error on failure
     */
    private function insert_bulk_conversation_messages( $messages, $conversation_id, $conversation_type = 'chatwoot' ) {
        if ( empty( $messages ) ) {
            return 0;
        }

        global $wpdb;
        $total_inserted = 0;

        // Process messages in chunks of 200
        $chunks = array_chunk( $messages, 200 );

        foreach ( $chunks as $chunk ) {
            if ( empty( $chunk ) ) {
                continue;
            }

            $sql = "INSERT INTO $wpdb->comments (comment_post_ID, comment_author, comment_author_email, comment_date, comment_date_gmt, comment_content, comment_approved, comment_type, comment_parent, user_id) VALUES ";

            $valid_messages = 0;
            foreach ( $chunk as $message ) {
                // Skip invalid messages and Chatwoot private notes
                if ( empty( $message['content'] ) || empty( $message['created_at'] ) || !empty( $message['private'] ) ) {
                    continue;
                }

                // Filter by message_type (0 = incoming, 1 = outgoing)
                $message_type = isset( $message['message_type'] ) ? $message['message_type'] : -1;
                if ( $message_type !== 0 && $message_type !== 1 ) {
                    continue;
                }

                // Extract sender name
                $sender_name = isset( $message['sender']['name'] ) ? $message['sender']['name'] : 'Chatwoot';

                // Convert timestamp to MySQL datetime
                $message_time = gmdate( 'Y-m-d H:i:s', $message['created_at'] );

                // Sanitize content
                $content = Chat_Functions::disable_markdown_images( $message['content'] );

                $sql .= $wpdb->prepare( '( %d, %s, %s, %s, %s, %s, %d, %s, %d, %d ),',
                    $conversation_id,
                    $sender_name,
                    '', // comment_author_email (not used for Chatwoot)
                    $message_time,
                    $message_time, // comment_date_gmt (same as comment_date since we use gmdate)
                    $content,
                    1, // comment_approved
                    $conversation_type,
                    0, // comment_parent
                    0  // user_id
                );

                $valid_messages++;
            }

            // Skip if no valid messages in this chunk
            if ( $valid_messages === 0 ) {
                continue;
            }

            $sql .= ';';
            $sql = str_replace( ',;', ';', $sql ); // remove last comma

            // A failed query is logged with its values, which here are people's messages.
            $suppress_errors = $wpdb->suppress_errors( true );
            $result = $wpdb->query( $sql ); // @phpcs:ignore
            $wpdb->suppress_errors( $suppress_errors );
            if ( empty( $result ) || is_wp_error( $result ) ) {
                return new WP_Error( __FUNCTION__, 'Failed to insert messages for conversation ' . $conversation_id );
            }

            $total_inserted += $valid_messages;
        }

        return $total_inserted;
    }

    private function add_message_to_conversation( $dt_conversation_id, $message_params, $conversation_type = 'chatwoot' ) {
        // Skip if not a valid message, or a Chatwoot private note
        if ( empty( $message_params['content'] ) || empty( $message_params['created_at'] ) || !empty( $message_params['private'] ) ) {
            return false;
        }

        $message_type = isset( $message_params['message_type'] ) ? $message_params['message_type'] : -1;
        if ( $message_type !== 0 && $message_type !== 1 ) {
            return false;
        }

        $sender_name = isset( $message_params['sender']['name'] ) ? $message_params['sender']['name'] : 'Chatwoot';
        $message_time = gmdate( 'Y-m-d H:i:s', $message_params['created_at'] );
        $message_id = intval( $message_params['id'] ?? 0 );

        $content = Chat_Functions::disable_markdown_images( $message_params['content'] );

        if ( $this->is_message_imported( $dt_conversation_id, $message_id, $message_time, $content ) ) {
            return false;
        }

        $comment_args = [ 'comment_date' => $message_time, 'comment_author' => $sender_name ];
        if ( $message_id > 0 ) {
            $comment_args['comment_meta'] = [ self::CHATWOOT_MESSAGE_META => $message_id ];
        }

        // Create comment on D.T conversation with the appropriate type
        $result = DT_Posts::add_post_comment(
            'conversations',
            $dt_conversation_id,
            $content,
            $conversation_type,
            $comment_args,
            false,
            true
        );

        if ( is_wp_error( $result ) ) {
            dt_write_log( 'Failed to add comment to conversation ' . $dt_conversation_id . ': ' . $result->get_error_message() );
            return false;
        }

        return true;
    }

    /**
     * A message is already on the record if a comment carries its Chatwoot id, or (for bulk-imported rows,
     * which carry no comment meta) has the same date and content.
     */
    private function is_message_imported( $dt_conversation_id, $message_id, $message_time, $content ) {
        global $wpdb;
        $found = $wpdb->get_var( $wpdb->prepare(
            "SELECT c.comment_ID FROM $wpdb->comments c
            LEFT JOIN $wpdb->commentmeta m ON ( m.comment_id = c.comment_ID AND m.meta_key = %s )
            WHERE c.comment_post_ID = %d
            AND ( m.meta_value = %s OR ( c.comment_date_gmt = %s AND c.comment_content = %s ) )
            LIMIT 1",
            self::CHATWOOT_MESSAGE_META,
            $dt_conversation_id,
            $message_id > 0 ? (string) $message_id : '',
            $message_time,
            $content
        ) );
        return !empty( $found );
    }

    private function add_message_to_contact( $contact_id, $params, $summary = [] ) {
        if ( empty( $contact_id ) ) {
            return;
        }

        $inbox_name = isset( $params['inbox_name'] ) ? trim( (string) $params['inbox_name'] ) : '';
        $inbox_name = $inbox_name !== '' ? wp_strip_all_tags( $inbox_name ) : '';

        $comment_content = sprintf(
            "New conversation from %s.\nPlease see the Conversations tile to read the full conversation.",
            $inbox_name !== '' ? $inbox_name : 'Chatwoot'
        );

        if ( !empty( $summary ) ) {
            $comment_content .= "\n\nSummary:\n";
            if ( is_array( $summary ) ) {
                foreach ( $summary as $language => $value ) {
                    if ( is_array( $value ) ) {
                        $value = implode( ' ', array_map( 'strval', $value ) );
                    }

                    $clean_summary = trim( Chat_Functions::disable_markdown_images( wp_strip_all_tags( (string) $value ) ) );
                    if ( $clean_summary === '' ) {
                        continue;
                    }

                    $language_label = is_string( $language ) ? trim( (string) $language ) : '';
                    $language_label = $language_label !== '' ? strtoupper( $language_label ) : 'SUMMARY';

                    $comment_content .= sprintf( "[%s] %s\n", $language_label, $clean_summary );
                }
            } else {
                $comment_content .= Chat_Functions::disable_markdown_images( wp_strip_all_tags( (string) $summary ) ) . "\n";
            }
        }

        DT_Posts::add_post_comment( 'contacts', (int) $contact_id, Chat_Functions::disable_mentions( $comment_content ), 'comment', [], false, false );
    }
}
new Disciple_Tools_Chatwoot_Endpoints();
