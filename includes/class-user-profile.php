<?php
/**
 * User Profile Fields
 *
 * @package Nostr_Login_Pay
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles user profile fields for Nostr and NWC
 */
class Nostr_Login_Pay_User_Profile {

    /**
     * The single instance of the class
     */
    private static $instance = null;

    /**
     * Main Instance
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Add fields to user profile
        add_action( 'show_user_profile', array( $this, 'render_profile_fields' ) );
        add_action( 'edit_user_profile', array( $this, 'render_profile_fields' ) );

        // Add fields to WooCommerce my account page
        add_action( 'woocommerce_account_dashboard', array( $this, 'render_woocommerce_nostr_section' ) );

        // Add custom endpoint for NWC management
        add_action( 'init', array( $this, 'add_nwc_endpoint' ) );
        add_filter( 'woocommerce_account_menu_items', array( $this, 'add_nwc_menu_item' ) );
        add_action( 'woocommerce_account_nwc-wallet_endpoint', array( $this, 'render_nwc_wallet_page' ) );

        // AJAX handlers
        add_action( 'wp_ajax_disconnect_nwc_wallet', array( $this, 'ajax_disconnect_wallet' ) );
    }

    /**
     * Render Nostr profile fields
     */
    public function render_profile_fields( $user ) {
        if ( ! current_user_can( 'edit_user', $user->ID ) ) {
            return;
        }

        $nostr_pubkey = get_user_meta( $user->ID, 'nostr_pubkey', true );
        $nwc_connected = get_user_meta( $user->ID, 'nwc_wallet_pubkey', true );
        $nwc_connected_at = get_user_meta( $user->ID, 'nwc_wallet_connected_at', true );
        ?>
        <h2><?php _e( 'Nostr & Lightning', 'nostr-login-pay' ); ?></h2>
        <table class="form-table">
            <tr>
                <th><label><?php _e( 'Nostr Public Key', 'nostr-login-pay' ); ?></label></th>
                <td>
                    <?php if ( $nostr_pubkey ) : ?>
                        <code><?php echo esc_html( $nostr_pubkey ); ?></code>
                        <p class="description"><?php _e( 'Your Nostr identity public key.', 'nostr-login-pay' ); ?></p>
                    <?php else : ?>
                        <p class="description"><?php _e( 'No Nostr public key connected.', 'nostr-login-pay' ); ?></p>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th><label><?php _e( 'NWC Wallet Status', 'nostr-login-pay' ); ?></label></th>
                <td>
                    <?php if ( $nwc_connected ) : ?>
                        <span style="color: green;">✓ <?php _e( 'Connected', 'nostr-login-pay' ); ?></span>
                        <?php if ( $nwc_connected_at ) : ?>
                            <p class="description">
                                <?php
                                printf(
                                    __( 'Connected on %s', 'nostr-login-pay' ),
                                    date_i18n( get_option( 'date_format' ), $nwc_connected_at )
                                );
                                ?>
                            </p>
                        <?php endif; ?>
                    <?php else : ?>
                        <span style="color: gray;">✗ <?php _e( 'Not connected', 'nostr-login-pay' ); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render Nostr section on WooCommerce dashboard
     */
    public function render_woocommerce_nostr_section() {
        if ( ! is_user_logged_in() ) {
            return;
        }

        $user_id = get_current_user_id();
        $nostr_pubkey = get_user_meta( $user_id, 'nostr_pubkey', true );
        
        // Only show section if user is logged in with Nostr
        if ( ! $nostr_pubkey ) {
            return;
        }
        ?>
        <div class="nostr-wallet-dashboard">
            <h3><?php _e( 'Nostr Identity', 'nostr-login-pay' ); ?></h3>
            
            <p>
                <strong><?php _e( 'Nostr Public Key:', 'nostr-login-pay' ); ?></strong><br>
                <code style="font-size: 11px;"><?php echo esc_html( $nostr_pubkey ); ?></code>
            </p>
            
            <p style="font-size: 13px; color: #666;">
                <?php _e( 'You can use your Nostr identity to log in to this site.', 'nostr-login-pay' ); ?>
            </p>
        </div>
        <?php
        
        /* DISABLED: NWC wallet connection UI removed
        $nwc_connected = get_user_meta( $user_id, 'nwc_wallet_pubkey', true );
        
        <?php if ( $nwc_connected ) : ?>
            <div class="nwc-wallet-status" style="padding: 15px; background: #f0f9ff; border-left: 4px solid #3b82f6; margin: 15px 0;">
                <p style="margin: 0;">
                    <strong style="color: #1e40af;">⚡ <?php _e( 'Lightning Wallet Connected', 'nostr-login-pay' ); ?></strong>
                </p>
                <p style="margin: 10px 0 0 0; font-size: 14px;">
                    <?php _e( 'You can now make instant Lightning payments at checkout.', 'nostr-login-pay' ); ?>
                    <a href="<?php echo esc_url( wc_get_account_endpoint_url( 'nwc-wallet' ) ); ?>" style="margin-left: 10px;">
                        <?php _e( 'Manage Wallet', 'nostr-login-pay' ); ?>
                    </a>
                </p>
            </div>
        <?php else : ?>
            <div class="nwc-connect-section" style="padding: 15px; background: #fef3c7; border-left: 4px solid #f59e0b; margin: 15px 0;">
                <p style="margin: 0 0 10px 0;">
                    <strong><?php _e( 'Connect Your Lightning Wallet', 'nostr-login-pay' ); ?></strong>
                </p>
                <p style="margin: 0 0 10px 0; font-size: 14px;">
                    <?php _e( 'Connect your NWC-enabled Lightning wallet to make instant payments.', 'nostr-login-pay' ); ?>
                </p>
                <button type="button" class="button button-primary" id="connect-nwc-btn">
                    <?php _e( 'Connect Wallet', 'nostr-login-pay' ); ?>
                </button>
            </div>
        <?php endif; ?>
        */
    }

    /**
     * Add NWC endpoint
     */
    public function add_nwc_endpoint() {
        // DISABLED: Customer NWC wallet connection not currently functional
        // Keeping code for future implementation when browser NWC support improves
        // add_rewrite_endpoint( 'nwc-wallet', EP_ROOT | EP_PAGES );
    }

    /**
     * Add NWC menu item to My Account
     */
    public function add_nwc_menu_item( $items ) {
        // DISABLED: Customer NWC wallet connection not currently functional
        // Just return items unchanged (no NWC wallet tab)
        return $items;
        
        /* Original code kept for future use:
        $new_items = array();
        
        foreach ( $items as $key => $item ) {
            $new_items[ $key ] = $item;
            
            // Add after dashboard
            if ( $key === 'dashboard' ) {
                $new_items['nwc-wallet'] = __( 'Lightning Wallet', 'nostr-login-pay' );
            }
        }
        
        return $new_items;
        */
    }

    /**
     * Render NWC wallet management page
     */
    public function render_nwc_wallet_page() {
        if ( ! is_user_logged_in() ) {
            return;
        }

        $user_id = get_current_user_id();
        $nwc_wallet = new Nostr_Login_Pay_NWC_Wallet();
        $connection = $nwc_wallet->get_user_connection( $user_id );
        ?>
        <div class="nwc-wallet-page">
            <h2><?php _e( 'Lightning Wallet (NWC)', 'nostr-login-pay' ); ?></h2>

            <?php if ( $connection ) : ?>
                <div class="nwc-connected-info">
                    <div style="padding: 20px; background: #f0fdf4; border: 1px solid #86efac; border-radius: 8px; margin-bottom: 20px;">
                        <h3 style="margin: 0 0 15px 0; color: #15803d;">
                            ✓ <?php _e( 'Wallet Connected', 'nostr-login-pay' ); ?>
                        </h3>
                        
                        <div style="margin-bottom: 15px;">
                            <strong><?php _e( 'Wallet Public Key:', 'nostr-login-pay' ); ?></strong><br>
                            <code style="font-size: 11px; word-break: break-all;"><?php echo esc_html( $connection['pubkey'] ); ?></code>
                        </div>

                        <div style="margin-bottom: 15px;">
                            <strong><?php _e( 'Relay:', 'nostr-login-pay' ); ?></strong><br>
                            <code><?php echo esc_html( $connection['relay'] ); ?></code>
                        </div>

                        <button type="button" class="button button-secondary" id="disconnect-nwc-btn" style="background: #dc2626; border-color: #dc2626; color: white;">
                            <?php _e( 'Disconnect Wallet', 'nostr-login-pay' ); ?>
                        </button>
                    </div>

                    <div class="nwc-features">
                        <h3><?php _e( 'What You Can Do', 'nostr-login-pay' ); ?></h3>
                        <ul>
                            <li>⚡ <?php _e( 'Make instant Lightning payments at checkout', 'nostr-login-pay' ); ?></li>
                            <li>🔒 <?php _e( 'Your keys remain secure in your wallet', 'nostr-login-pay' ); ?></li>
                            <li>🌐 <?php _e( 'Works with any NWC-compatible Lightning wallet', 'nostr-login-pay' ); ?></li>
                        </ul>
                    </div>
                </div>
            <?php else : ?>
                <div class="nwc-connect-form">
                    <p><?php _e( 'Connect your NWC-enabled Lightning wallet to enable instant payments.', 'nostr-login-pay' ); ?></p>
                    
                    <div style="padding: 20px; background: #f0f9ff; border: 1px solid #3b82f6; border-radius: 8px; margin: 20px 0;">
                        <h3 style="margin-top: 0; color: #1e40af;">💡 Recommended: Coinos</h3>
                        <p style="margin-bottom: 15px;">
                            <strong>Coinos</strong> is a free, easy-to-use Lightning wallet with excellent NWC support.
                        </p>
                        <h4 style="margin: 15px 0 10px 0;"><?php _e( 'How to Connect Your Wallet:', 'nostr-login-pay' ); ?></h4>
                        <ol style="margin: 10px 0 10px 20px; line-height: 1.8;">
                            <li>Sign up at <a href="https://coinos.io/" target="_blank" style="color: #2563eb; text-decoration: none;">coinos.io</a></li>
                            <li>Go to <strong>Settings → NWC</strong></li>
                            <li>Click <strong>"Create Connection"</strong></li>
                            <li>Enable <strong>"pay_invoice"</strong> permission (for making payments)</li>
                            <li>Copy the <strong>full connection string</strong></li>
                            <li>Paste it below and click Connect</li>
                        </ol>
                        <p style="margin: 10px 0 0 0; font-size: 13px; color: #64748b;">
                            <em>Works with Alby, Mutiny, and other NWC wallets too!</em>
                        </p>
                    </div>

                    <div style="margin: 20px 0;">
                        <label for="nwc-connection-string" style="display: block; margin-bottom: 10px; font-weight: bold;">
                            <?php _e( 'NWC Connection String', 'nostr-login-pay' ); ?>
                        </label>
                        <input 
                            type="text" 
                            id="nwc-connection-string" 
                            placeholder="nostr+walletconnect://..." 
                            style="width: 100%; padding: 10px; font-size: 14px; font-family: monospace;"
                        >
                        <p class="description">
                            <?php _e( 'Your connection string is encrypted and stored securely.', 'nostr-login-pay' ); ?>
                        </p>
                    </div>

                    <button type="button" class="button button-primary" id="connect-nwc-wallet-btn">
                        <?php _e( 'Connect Wallet', 'nostr-login-pay' ); ?>
                    </button>

                    <div id="nwc-connection-status" style="margin-top: 15px;"></div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * AJAX handler to disconnect wallet
     */
    public function ajax_disconnect_wallet() {
        check_ajax_referer( 'nostr-login-pay-nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => __( 'You must be logged in', 'nostr-login-pay' ) ) );
        }

        $nwc_wallet = new Nostr_Login_Pay_NWC_Wallet();
        $nwc_wallet->disconnect_user_wallet( get_current_user_id() );

        wp_send_json_success( array(
            'message' => __( 'Wallet disconnected successfully', 'nostr-login-pay' ),
        ) );
    }
}

