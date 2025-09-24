<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'Chat_Functions' ) ) {
    class Chat_Functions {
        /**
         * Standardize webhook data into a predictable array format.
         * Converts different webhook types (macro.executed, message_created) into a consistent structure.
         *
         * @param array $params Raw webhook parameters.
         *
         * @return array
         */
        public static function format_params( $params ) {
            $formatted_params = [
                'event' => $params['event'] ?? null,
                'sender' => null,
                'sender_name' => null,
                'sender_email' => null,
                'sender_phone' => null,
                'sender_facebook' => null,
                'account_id' => null,
                'inbox_id' => null,
                'inbox_name' => null,
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
                $formatted_params['conversation_type'] = self::get_dt_conversation_type( $formatted_params['channel'] );
                $formatted_params['trigger'] = $params['trigger'] ?? null;
            } elseif ( isset( $params['conversation']['meta']['sender'] ) ) {
                $sender = $params['conversation']['meta']['sender'];
                $formatted_params['sender'] = $sender;
                $formatted_params['sender_name'] = $sender['name'] ?? null;
                $formatted_params['sender_email'] = $sender['email'] ?? null;
                $formatted_params['sender_phone'] = $sender['phone_number'] ?? null;
                $formatted_params['sender_facebook'] = $sender['additional_attributes']['social_profiles']['facebook'] ?? null;
                $formatted_params['account_id'] = $params['account']['id'] ?? null;
                $formatted_params['inbox_id'] = $params['inbox']['id'] ?? null;
                $formatted_params['inbox_name'] = $params['inbox']['name'] ?? null;
                $formatted_params['conversation_id'] = $params['conversation']['id'] ?? null;
                $formatted_params['channel'] = $params['conversation']['channel'] ?? null;
                $formatted_params['last_message'] = $params['conversation']['messages'][0] ?? null;
                $formatted_params['dt_contact_id'] = $sender['custom_attributes']['dt_contact_id'] ?? null;
                $formatted_params['dt_contact_url'] = $sender['custom_attributes']['dt_contact_url'] ?? null;
                $formatted_params['dt_conversation_id'] = $params['conversation']['custom_attributes']['dt_conversation_id'] ?? null;
                $formatted_params['dt_conversation_url'] = $params['conversation']['custom_attributes']['dt_conversation_url'] ?? null;
                $formatted_params['conversation_type'] = self::get_dt_conversation_type( $formatted_params['channel'] );
                $formatted_params['labels'] = $params['conversation']['labels'] ?? null;
            }

            if ( ! empty( $formatted_params['account_id'] ) && ! empty( $formatted_params['inbox_id'] ) ) {
                $formatted_params['inbox_name'] = Disciple_Tools_Chatwoot_API::get_inbox_name( $formatted_params['account_id'], $formatted_params['inbox_id'] );
            }

            return $formatted_params;
        }

        /**
         * Convert arbitrary phone-like strings into a normalized representation used by DT.
         *
         * @param string $raw_phone
         *
         * @return string
         */
        public static function normalize_phone_number( $raw_phone ) {
            if ( empty( $raw_phone ) || ! is_string( $raw_phone ) ) {
                return '';
            }

            $raw_phone = trim( wp_strip_all_tags( $raw_phone ) );
            if ( $raw_phone === '' ) {
                return '';
            }

            $has_plus = strpos( $raw_phone, '+' ) === 0;
            $digits = preg_replace( '/\D+/', '', $raw_phone );
            if ( $digits === '' ) {
                return '';
            }

            return $has_plus ? '+' . $digits : $digits;
        }

        /**
         * Transform a list of scalar values into the Disciple.Tools "values" structure expected by comm fields.
         *
         * @param mixed $items Scalar value or array of values (strings/numbers).
         *
         * @return array
         */
        public static function array_to_values( $items ) {
            if ( empty( $items ) && $items !== '0' && $items !== 0 ) {
                return [];
            }

            if ( ! is_array( $items ) ) {
                $items = [ $items ];
            }

            $values = [];
            $seen = [];

            foreach ( $items as $item ) {
                if ( is_array( $item ) || is_object( $item ) ) {
                    continue;
                }

                if ( $item === null || $item === '' ) {
                    continue;
                }

                $item_string = is_string( $item ) ? $item : (string) $item;
                $item_string = trim( wp_strip_all_tags( $item_string ) );

                if ( $item_string === '' ) {
                    continue;
                }

                $item_string = sanitize_text_field( $item_string );
                if ( $item_string === '' ) {
                    continue;
                }

                if ( isset( $seen[ $item_string ] ) ) {
                    continue;
                }

                $seen[ $item_string ] = true;
                $values[] = [ 'value' => $item_string ];
            }

            if ( empty( $values ) ) {
                return [];
            }

            return [ 'values' => $values ];
        }

        /**
         * Map a free-form age description to the DT age select key.
         *
         * @param mixed $age
         *
         * @return string
         */
        public static function map_age_value( $age ) {
            if ( $age === null || $age === '' ) {
                return '';
            }

            $age_string = strtolower( trim( (string) $age ) );
            if ( $age_string === '' ) {
                return '';
            }

            $numbers = array_filter( array_map( 'intval', preg_split( '/[^0-9]+/', $age_string ) ) );
            if ( ! empty( $numbers ) ) {
                $max_number = max( $numbers );
                if ( $max_number < 19 ) {
                    return '<19';
                }
                if ( $max_number < 26 ) {
                    return '<26';
                }
                if ( $max_number < 41 ) {
                    return '<41';
                }
                return '>41';
            }

            if ( strpos( $age_string, 'under' ) !== false || strpos( $age_string, 'teen' ) !== false ) {
                return '<19';
            }
            if ( strpos( $age_string, '18' ) !== false || strpos( $age_string, 'college' ) !== false || strpos( $age_string, 'young adult' ) !== false ) {
                return '<26';
            }
            if ( strpos( $age_string, 'middle' ) !== false || strpos( $age_string, 'thirties' ) !== false ) {
                return '<41';
            }
            if ( strpos( $age_string, 'adult' ) !== false || strpos( $age_string, 'older' ) !== false || strpos( $age_string, 'senior' ) !== false ) {
                return '>41';
            }

            return '';
        }

        /**
         * Map a free-form gender description to the DT gender select key.
         *
         * @param mixed $gender
         *
         * @return string
         */
        public static function map_gender_value( $gender ) {
            if ( $gender === null || $gender === '' ) {
                return '';
            }

            $gender_string = strtolower( trim( (string) $gender ) );
            if ( $gender_string === '' ) {
                return '';
            }

            if ( in_array( $gender_string, [ 'male', 'man', 'm', 'masculine' ], true ) ) {
                return 'male';
            }

            if ( in_array( $gender_string, [ 'female', 'woman', 'f', 'feminine' ], true ) ) {
                return 'female';
            }

            return '';
        }

        private static function get_dt_conversation_type( $channel_type ) {
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
                'Channel::TwitterProfile' => 'twitter',
            ];

            return $type_mapping[ $channel_type ] ?? 'chatwoot';
        }
    }
}
