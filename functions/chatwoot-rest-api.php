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

        $params = Chat_Functions::format_params( $params );

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
        if ( empty( $params['trigger'] ) ){
            return;
        }
        if ( !class_exists( 'Disciple_Tools_Chatwoot_API' ) ){
            dt_write_log( 'Disciple_Tools_Chatwoot_API class not found' );
            return;
        }

        $contact_id = $params['dt_contact_id'];
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

        return DT_Posts::create_post( 'contacts', $contact_fields, true, false, [ 'check_for_duplicates' => [ 'contact_email', 'contact_phone' ] ] );
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
            [ 'comment_date' => $message_time, 'comment_author' => $sender_name ],
            false,
            true
        );

        if ( is_wp_error( $result ) ) {
            dt_write_log( 'Failed to add comment to conversation ' . $dt_conversation_id . ': ' . $result->get_error_message() );
            return false;
        }

        return true;
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

                    $clean_summary = trim( wp_strip_all_tags( (string) $value ) );
                    if ( $clean_summary === '' ) {
                        continue;
                    }

                    $language_label = is_string( $language ) ? trim( (string) $language ) : '';
                    $language_label = $language_label !== '' ? strtoupper( $language_label ) : 'SUMMARY';

                    $comment_content .= sprintf( "[%s] %s\n", $language_label, $clean_summary );
                }
            } else {
                $comment_content .= wp_strip_all_tags( (string) $summary ) . "\n";
            }
        }

        DT_Posts::add_post_comment( 'contacts', (int) $contact_id, $comment_content, 'comment', [], false, false );
    }
}
new Disciple_Tools_Chatwoot_Endpoints();
