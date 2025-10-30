<?php
if ( !defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly.

/**
 * Add Chatwoot tile to conversation records
 */
class Disciple_Tools_Chatwoot_Conversation_Tile {

    public function __construct() {
        add_filter( 'dt_details_additional_tiles', [ $this, 'dt_details_additional_tiles' ], 10, 2 );
        add_action( 'dt_details_additional_section', [ $this, 'add_chatwoot_tile' ], 10, 2 );
        add_action( 'wp_footer', [ $this, 'add_inline_scripts' ] );
        add_action( 'rest_api_init', [ $this, 'add_api_routes' ] );
    }

    /**
     * Register the Chatwoot tile
     */
    public function dt_details_additional_tiles( $tiles, $post_type ) {
        if ( $post_type === 'conversations' ) {
            $tiles['chatwoot'] = [
                'label' => __( 'Chatwoot', 'disciple-tools-chatwoot' )
            ];
        }
        return $tiles;
    }

    /**
     * Add Chatwoot tile to conversation details page
     */
    public function add_chatwoot_tile( $section, $post_type ) {
        if ( $section !== 'chatwoot' ) {
            return;
        }
        if ( $post_type !== 'conversations' ) {
            return;
        }

        $post_id = get_the_ID();

        // Get the conversation post
        $conversation = DT_Posts::get_post( 'conversations', $post_id, true, false );
        if ( is_wp_error( $conversation ) || empty( $conversation ) ) {
            return;
        }

        // Check if this is a Chatwoot conversation by looking at the handle
        $handle = isset( $conversation['name'] ) ? $conversation['name'] : '';
        if ( strpos( $handle, 'chatwoot_' ) !== 0 ) {
            return;
        }

        // Extract account_id and conversation_id from handle (format: chatwoot_{account_id}_{conversation_id})
        $handle_parts = explode( '_', $handle );
        if ( count( $handle_parts ) !== 3 ) {
            return;
        }

        $account_id = $handle_parts[1];
        $conversation_id = $handle_parts[2];

        // Get Chatwoot URL from settings
        $settings = get_option( 'dt_chatwoot', array() );
        $chatwoot_url = isset( $settings['url'] ) ? $settings['url'] : '';
        $chatwoot_conversation_url = '';
        if ( !empty( $chatwoot_url ) ) {
            $chatwoot_conversation_url = trailingslashit( $chatwoot_url ) . 'app/accounts/' . $account_id . '/conversations/' . $conversation_id;
        }

        ?>
        <div class="section-subheader">
            <?php esc_html_e( 'Sync conversation messages from Chatwoot to this D.T conversation record.', 'disciple-tools-chatwoot' ); ?>
        </div>

        <p>
            <strong><?php esc_html_e( 'Chatwoot Account ID:', 'disciple-tools-chatwoot' ); ?></strong> <?php echo esc_html( $account_id ); ?><br>
            <strong><?php esc_html_e( 'Chatwoot Conversation ID:', 'disciple-tools-chatwoot' ); ?></strong> <?php echo esc_html( $conversation_id ); ?>
        </p>

        <?php if ( !empty( $chatwoot_conversation_url ) ) : ?>
            <p>
                <a href="<?php echo esc_url( $chatwoot_conversation_url ); ?>" target="_blank" rel="noopener noreferrer">
                    <?php esc_html_e( 'View in Chatwoot', 'disciple-tools-chatwoot' ); ?>
                    <img class="dt-icon" src="<?php echo esc_html( get_template_directory_uri() . '/dt-assets/images/open-link.svg' ) ?>"/>
                </a>
            </p>
        <?php endif; ?>

        <button id="chatwoot-resync-button"
                class="button"
                data-conversation-id="<?php echo esc_attr( $post_id ); ?>"
                data-account-id="<?php echo esc_attr( $account_id ); ?>"
                data-chatwoot-conversation-id="<?php echo esc_attr( $conversation_id ); ?>">
            <span class="button-text"><?php esc_html_e( 'Re-sync Conversation', 'disciple-tools-chatwoot' ); ?></span>
            <span class="loading-spinner" style="display: none;"></span>
        </button>

        <div id="chatwoot-resync-message" style="margin-top: 10px; display: none;">
            <span id="chatwoot-resync-message-text"></span>
        </div>
        <?php
    }

    /**
     * Add inline scripts and styles for the Chatwoot tile
     */
    public function add_inline_scripts() {
        if ( get_post_type() !== 'conversations' ) {
            return;
        }

        ?>
        <style>
            #chatwoot-resync-message {
                padding: 10px;
                border-radius: 4px;
                font-size: 14px;
            }

            #chatwoot-resync-message.success {
                background-color: #d4edda;
                border: 1px solid #c3e6cb;
                color: #155724;
            }

            #chatwoot-resync-message.error {
                background-color: #f8d7da;
                border: 1px solid #f5c6cb;
                color: #721c24;
            }

            #chatwoot-resync-button {
                position: relative;
            }

            #chatwoot-resync-button .loading-spinner {
                display: inline-block;
                width: 16px;
                height: 16px;
                border: 2px solid #f3f3f3;
                border-top: 2px solid #3498db;
                border-radius: 50%;
                animation: spin 1s linear infinite;
                margin-left: 8px;
                vertical-align: middle;
            }

            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }

            #chatwoot-resync-button:disabled {
                opacity: 0.6;
                cursor: not-allowed;
            }
        </style>

        <script type="text/javascript">
            jQuery(document).ready(function($) {
                'use strict';

                const chatwootTile = {
                    restUrl: <?php echo json_encode( rest_url( 'dt-chatwoot/v1/resync' ) ); ?>,
                    nonce: <?php echo json_encode( wp_create_nonce( 'wp_rest' ) ); ?>,
                    translations: {
                        syncing: <?php echo json_encode( __( 'Syncing...', 'disciple-tools-chatwoot' ) ); ?>,
                        success: <?php echo json_encode( __( 'Successfully synced {count} messages!', 'disciple-tools-chatwoot' ) ); ?>,
                        error: <?php echo json_encode( __( 'Error syncing conversation: {error}', 'disciple-tools-chatwoot' ) ); ?>
                    }
                };

                // Handle re-sync button click
                $('#chatwoot-resync-button').on('click', function(e) {
                    e.preventDefault();

                    const button = $(this);
                    const buttonText = button.find('.button-text');
                    const spinner = button.find('.loading-spinner');
                    const messageDiv = $('#chatwoot-resync-message');
                    const messageText = $('#chatwoot-resync-message-text');

                    // Get data attributes
                    const conversationId = button.data('conversation-id');
                    const accountId = button.data('account-id');
                    const chatwootConversationId = button.data('chatwoot-conversation-id');

                    // Store original button text if not already stored
                    if (!button.data('original-text')) {
                        button.data('original-text', buttonText.text());
                    }

                    // Disable button and show loading state
                    button.prop('disabled', true);
                    buttonText.text(chatwootTile.translations.syncing);
                    spinner.show();
                    messageDiv.hide();

                    // Make API request
                    $.ajax({
                        url: chatwootTile.restUrl,
                        method: 'POST',
                        dataType: 'json',
                        beforeSend: function(xhr) {
                            xhr.setRequestHeader('X-WP-Nonce', chatwootTile.nonce);
                        },
                        data: {
                            conversation_id: conversationId,
                            account_id: accountId,
                            chatwoot_conversation_id: chatwootConversationId
                        },
                        success: function(response) {
                            // Show success message
                            const successMessage = chatwootTile.translations.success.replace('{count}', response.count || 0);
                            messageText.text(successMessage);
                            messageDiv.removeClass('error').addClass('success').show();

                            // Reload the page after 2 seconds to show updated messages
                            setTimeout(function() {
                                window.location.reload();
                            }, 2000);
                        },
                        error: function(xhr) {
                            let errorMessage = chatwootTile.translations.error;

                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                errorMessage = errorMessage.replace('{error}', xhr.responseJSON.message);
                            } else {
                                errorMessage = errorMessage.replace('{error}', 'Unknown error');
                            }

                            messageText.text(errorMessage);
                            messageDiv.removeClass('success').addClass('error').show();

                            // Re-enable button
                            button.prop('disabled', false);
                            buttonText.text(button.data('original-text'));
                            spinner.hide();
                        }
                    });
                });
            });
        </script>
        <?php
    }

    /**
     * REST API routes
     */
    public function add_api_routes() {
        register_rest_route( 'dt-chatwoot/v1', '/resync', [
            'methods' => 'POST',
            'callback' => [ $this, 'resync_conversation' ],
            'permission_callback' => function() {
                return current_user_can( 'access_conversations' );
            },
        ] );
    }

    /**
     * Re-sync conversation from Chatwoot
     */
    public function resync_conversation( WP_REST_Request $request ) {
        $params = $request->get_params();

        if ( !isset( $params['conversation_id'], $params['account_id'], $params['chatwoot_conversation_id'] ) ) {
            return new WP_Error( 'missing_params', __( 'Missing required parameters', 'disciple-tools-chatwoot' ), [ 'status' => 400 ] );
        }

        $dt_conversation_id = intval( $params['conversation_id'] );
        $account_id = intval( $params['account_id'] );
        $chatwoot_conversation_id = intval( $params['chatwoot_conversation_id'] );

        // Verify the conversation exists and user has access
        $conversation = DT_Posts::get_post( 'conversations', $dt_conversation_id, true, false );
        if ( is_wp_error( $conversation ) ) {
            return $conversation;
        }

        // Fetch full conversation from Chatwoot
        if ( !class_exists( 'Disciple_Tools_Chatwoot_API' ) ) {
            return new WP_Error( 'api_class_missing', __( 'Chatwoot API class not found', 'disciple-tools-chatwoot' ), [ 'status' => 500 ] );
        }

        $messages = Disciple_Tools_Chatwoot_API::get_full_conversation( $account_id, $chatwoot_conversation_id );

        if ( $messages === false ) {
            return new WP_Error( 'fetch_failed', __( 'Failed to fetch conversation from Chatwoot', 'disciple-tools-chatwoot' ), [ 'status' => 500 ] );
        }

        if ( empty( $messages ) ) {
            return [
                'success' => true,
                'message' => __( 'No messages found in Chatwoot conversation', 'disciple-tools-chatwoot' ),
                'count' => 0
            ];
        }

        // Delete existing comments for this conversation
        global $wpdb;
        $deleted = $wpdb->delete(
            $wpdb->comments,
            [ 'comment_post_ID' => $dt_conversation_id ],
            [ '%d' ]
        );

        if ( $deleted === false ) {
            dt_write_log( 'Failed to delete existing comments for conversation ' . $dt_conversation_id );
        }

        // Get conversation type from the conversation record
        $conversation_type = isset( $conversation['type']['key'] ) ? $conversation['type']['key'] : 'chatwoot';

        // Re-insert all messages using bulk insert
        $endpoints_class = new Disciple_Tools_Chatwoot_Endpoints();
        $reflection = new ReflectionClass( $endpoints_class );
        $method = $reflection->getMethod( 'insert_bulk_conversation_messages' );
        $method->setAccessible( true );
        $result = $method->invoke( $endpoints_class, $messages, $dt_conversation_id, $conversation_type );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return [
            'success' => true,
            'message' => sprintf( __( 'Successfully synced %d messages', 'disciple-tools-chatwoot' ), $result ),
            'count' => $result
        ];
    }
}

new Disciple_Tools_Chatwoot_Conversation_Tile();
