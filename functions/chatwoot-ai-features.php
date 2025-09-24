<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
} // Exit if accessed directly.

class Disciple_Tools_Chatwoot_AI {

    /**
     * Create a summary from a full Chatwoot conversation transcript.
     *
     * @param array $conversation_messages Array of message payloads returned from Chatwoot.
     * @param int|null $conversation_post_id Optional conversations post ID to persist the summary to.
     *
     * @return array|WP_Error
     */
    public static function summarize( array $conversation_messages, $conversation_post_id = null ) {
        if ( empty( $conversation_messages ) ) {
            return new WP_Error( 'empty_conversation', 'Conversation messages are required to generate a summary.', [ 'status' => 400 ] );
        }
        if ( !class_exists( 'Disciple_Tools_AI_API' ) ) {
            return new WP_Error( 'ai_api_not_found', 'Disciple_Tools_AI_API class not found.', [ 'status' => 500 ] );
        }

        $connection_settings = Disciple_Tools_AI_API::get_ai_connection_settings();
        if ( empty( $connection_settings['llm_endpoint'] ) || empty( $connection_settings['llm_api_key'] ) || empty( $connection_settings['llm_model'] ) ) {
            return new WP_Error( 'missing_ai_configuration', 'LLM connection settings are not configured.', [ 'status' => 500 ] );
        }

        $conversation_post_id = is_null( $conversation_post_id ) ? null : absint( $conversation_post_id );

        $system_prompt = 'You summarize conversations between a contact and a ministry teammate.
Return a concise handoff note that captures the conversation type and the main request and response.
Keep the note to 1–2 short sentences (no more than 35 words) in clear, friendly teammate language.
Only mention blockers, commitments, follow-ups, or next steps if they are explicit in the transcript.
Do not invent details, quote messages verbatim, or restate every exchange.
Only return the summary text itself—do not include headings, labels, or prefixes such as “Summary:”, “Handoff Note:”, bullets, hyphens, or numbering. Start directly with the first word of the summary.
';

        $transcript = "Conversation Transcript:\n";
        foreach ( $conversation_messages as $message ) {
            if ( empty( $message['content'] ) ) {
                continue;
            }

            $content = wp_strip_all_tags( (string) $message['content'] );
            $content = trim( html_entity_decode( $content, ENT_QUOTES | ENT_HTML5 ) );
            if ( $content === '' ) {
                continue;
            }

            $timestamp = isset( $message['created_at'] ) ? gmdate( 'Y-m-d H:i:s', (int) $message['created_at'] ) : 'Unknown time';
            $sender_name = $message['sender']['name'] ?? '';

            if ( $sender_name === '' ) {
                $message_type = isset( $message['message_type'] ) ? (int) $message['message_type'] : null;
                if ( $message_type === 0 ) {
                    $sender_name = 'Contact';
                } elseif ( $message_type === 1 ) {
                    $sender_name = 'Team';
                }  elseif ( $message_type === 2 ) {
                    continue;
                } elseif ( $message_type === 3 ) {
                    $sender_name = 'Bot';
                } else {
                    $sender_name = 'Participant';
                }
            }

            $transcript .= sprintf( "- [%s] %s: %s\n", $timestamp, $sender_name, $content );
        }

        if ( $transcript === "Conversation Transcript:\n" ) {
            return new WP_Error( 'no_message_content', 'No usable message content found in the conversation.', [ 'status' => 400 ] );
        }



        $llm_endpoint = trailingslashit( $connection_settings['llm_endpoint'] ) . 'chat/completions';

        $response = wp_remote_post( $llm_endpoint, [
            'method' => 'POST',
            'headers' => [
                'Authorization' => 'Bearer ' . $connection_settings['llm_api_key'],
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode( [
                'model' => $connection_settings['llm_model'],
                'messages' => [
                    [ 'role' => 'system', 'content' => $system_prompt ],
                    [ 'role' => 'user', 'content' => $transcript ],
                ],
                'max_completion_tokens' => 600,
                'temperature' => 0.3,
                'top_p' => 1,
            ] ),
            'timeout' => 30,
        ] );

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'api_error', 'Failed to connect to LLM API', [ 'status' => 500 ] );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $summary = $body['choices'][0]['message']['content'] ?? '';

        if ( $summary === '' ) {
            return new WP_Error( 'invalid_api_response', 'LLM API did not return a summary.', [ 'status' => 500 ] );
        }

        $summary_array = Disciple_Tools_AI_API::translate_summary( $summary );

        $post_updated = false;
        if ( ! is_null( $conversation_post_id ) ) {
            $updated = DT_Posts::update_post( 'conversations', $conversation_post_id, [ 'ai_summary_array' => $summary_array ], true, false );
            $post_updated = ! is_wp_error( $updated );
        }

        return [
            'updated' => $post_updated,
            'summary' => $summary_array,
        ];
    }

    /**
     * Extract structured contact attributes from a conversation transcript via LLM.
     *
     * @param array $conversation_messages Array of message payloads returned from Chatwoot.
     *
     * @return array|WP_Error
     */
    public static function extract_contact_attributes( array $conversation_messages ) {
        if ( empty( $conversation_messages ) ) {
            return new WP_Error( 'empty_conversation', 'Conversation messages are required to extract contact attributes.', [ 'status' => 400 ] );
        }
        if ( !class_exists( 'Disciple_Tools_AI_API' ) ) {
            return new WP_Error( 'ai_api_not_found', 'Disciple_Tools_AI_API class not found.', [ 'status' => 500 ] );
        }

        $connection_settings = Disciple_Tools_AI_API::get_ai_connection_settings();
        if ( empty( $connection_settings['llm_endpoint'] ) || empty( $connection_settings['llm_api_key'] ) || empty( $connection_settings['llm_model'] ) ) {
            return new WP_Error( 'missing_ai_configuration', 'LLM connection settings are not configured.', [ 'status' => 500 ] );
        }

        $transcript = "Conversation Transcript:\n";
        foreach ( $conversation_messages as $message ) {
            if ( empty( $message['content'] ) ) {
                continue;
            }

            $content = wp_strip_all_tags( (string) $message['content'] );
            $content = trim( html_entity_decode( $content, ENT_QUOTES | ENT_HTML5 ) );
            if ( $content === '' ) {
                continue;
            }

            $timestamp = isset( $message['created_at'] ) ? gmdate( 'Y-m-d H:i:s', (int) $message['created_at'] ) : 'Unknown time';
            $sender_name = $message['sender']['name'] ?? '';

            if ( $sender_name === '' ) {
                $message_type = isset( $message['message_type'] ) ? (int) $message['message_type'] : null;
                if ( $message_type === 0 ) {
                    $sender_name = 'Contact';
                } elseif ( $message_type === 1 ) {
                    $sender_name = 'Team';
                }  elseif ( $message_type === 2 ) {
                    continue;
                } elseif ( $message_type === 3 ) {
                    $sender_name = 'Bot';
                } else {
                    $sender_name = 'Participant';
                }
            }

            $transcript .= sprintf( "- [%s] %s: %s\n", $timestamp, $sender_name, $content );
        }

        if ( $transcript === "Conversation Transcript:\n" ) {
            return new WP_Error( 'no_message_content', 'No usable message content found in the conversation.', [ 'status' => 400 ] );
        }

        $system_prompt = 'You extract personal attributes about a contact from conversation transcripts between the contact and a ministry teammate.
Return a JSON object that uses this exact schema:
{
  "name": string|null,
  "phone_numbers": string[],
  "emails": string[],
  "addresses": string[],
  "locations": string[],
  "language": string|null,
  "age": string|null,
  "gender": string|null
}
Rules:
- Use null for any scalar value that is unknown or not stated.
- detect the language of the contact from the transcript.
- Use an empty array [] when no values are available for a list.
- Never invent values that are not clearly present or strongly implied.
- Standardize phone numbers and emails exactly as they appear in the transcript.
- The response must be valid JSON only, with no commentary or Markdown.';

        $user_prompt = $transcript . "\n\nReturn only the JSON object.";

        $llm_endpoint = trailingslashit( $connection_settings['llm_endpoint'] ) . 'chat/completions';

        $response = wp_remote_post( $llm_endpoint, [
            'method' => 'POST',
            'headers' => [
                'Authorization' => 'Bearer ' . $connection_settings['llm_api_key'],
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode( [
                'model' => $connection_settings['llm_model'],
                'messages' => [
                    [ 'role' => 'system', 'content' => $system_prompt ],
                    [ 'role' => 'user', 'content' => $user_prompt ],
                ],
                'max_completion_tokens' => 600,
                'temperature' => 0.2,
                'top_p' => 1,
            ] ),
            'timeout' => 30,
        ] );

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'api_error', 'Failed to connect to LLM API', [ 'status' => 500 ] );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $content = isset( $body['choices'][0]['message']['content'] ) ? trim( $body['choices'][0]['message']['content'] ) : '';

        if ( $content === '' ) {
            return new WP_Error( 'invalid_api_response', 'LLM API did not return extracted attributes.', [ 'status' => 500 ] );
        }

        $attributes = json_decode( $content, true );

        if ( ! is_array( $attributes ) ) {
            return new WP_Error( 'invalid_json', 'LLM API did not return valid JSON.', [ 'status' => 500 ] );
        }

        $defaults = [
            'name' => null,
            'phone_numbers' => [],
            'emails' => [],
            'addresses' => [],
            'locations' => [],
            'language' => null,
            'age' => null,
            'gender' => null,
        ];

        $normalized = $defaults;
        foreach ( array_keys( $defaults ) as $key ) {
            if ( array_key_exists( $key, $attributes ) ) {
                $normalized[ $key ] = $attributes[ $key ];
            }
        }

        $list_fields = [ 'phone_numbers', 'emails', 'addresses', 'locations' ];
        foreach ( $list_fields as $field ) {
            $value = $normalized[ $field ];
            if ( is_null( $value ) ) {
                $normalized[ $field ] = [];
                continue;
            }
            if ( is_string( $value ) ) {
                $value = trim( $value );
                $normalized[ $field ] = $value === '' ? [] : [ $value ];
                continue;
            }
            if ( is_array( $value ) ) {
                $clean = [];
                foreach ( $value as $item ) {
                    if ( is_string( $item ) || is_numeric( $item ) ) {
                        $item = trim( (string) $item );
                        if ( $item !== '' ) {
                            $clean[] = $item;
                        }
                    }
                }
                $normalized[ $field ] = $clean;
                continue;
            }
            $normalized[ $field ] = [];
        }

        $scalar_fields = [ 'name', 'language', 'age', 'gender' ];
        foreach ( $scalar_fields as $field ) {
            $value = $normalized[ $field ];
            if ( is_string( $value ) ) {
                $value = trim( $value );
                $normalized[ $field ] = $value === '' ? null : $value;
            } elseif ( is_numeric( $value ) ) {
                $normalized[ $field ] = (string) $value;
            } elseif ( ! is_null( $value ) ) {
                $normalized[ $field ] = null;
            }
        }

        return $normalized;
    }

    public static function summarize_transcript( $transcript ) {
        if ( empty( $transcript ) ) {
            return new WP_Error( 'empty_transcript', 'Transcript is required to generate a summary.', [ 'status' => 400 ] );
        }
        $connection_settings = Disciple_Tools_AI_API::get_ai_connection_settings();
        if ( empty( $connection_settings['llm_endpoint'] ) || empty( $connection_settings['llm_api_key'] ) || empty( $connection_settings['llm_model'] ) ) {
            return new WP_Error( 'missing_ai_configuration', 'LLM connection settings are not configured.', [ 'status' => 500 ] );
        }

        $system_prompt = 'You summarize conversations between a contact and a ministry teammate.
Return a concise handoff note that captures the conversation type and the main request and response.
Keep the note to 1–2 short sentences (no more than 35 words) in clear, friendly teammate language.
Only mention blockers, commitments, follow-ups, or next steps if they are explicit in the transcript.
Do not invent details, quote messages verbatim, or restate every exchange.
Only return the summary text itself—do not include headings, labels, or prefixes such as “Summary:”, “Handoff Note:”, bullets, hyphens, or numbering. Start directly with the first word of the summary.
';

$llm_endpoint = trailingslashit( $connection_settings['llm_endpoint'] ) . 'chat/completions';

        $response = wp_remote_post( $llm_endpoint, [
            'method' => 'POST',
            'headers' => [
                'Authorization' => 'Bearer ' . $connection_settings['llm_api_key'],
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode( [
                'model' => $connection_settings['llm_model'],
                'messages' => [
                    [ 'role' => 'system', 'content' => $system_prompt ],
                    [ 'role' => 'user', 'content' => $transcript ],
                ],
                'max_completion_tokens' => 600,
                'temperature' => 0.3,
                'top_p' => 1,
            ] ),
            'timeout' => 30,
        ] );

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'api_error', 'Failed to connect to LLM API', [ 'status' => 500 ] );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $summary = $body['choices'][0]['message']['content'] ?? '';

        if ( $summary === '' ) {
            return new WP_Error( 'invalid_api_response', 'LLM API did not return a summary.', [ 'status' => 500 ] );
        }
        return $summary;
    }
}
