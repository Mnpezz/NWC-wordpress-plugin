<?php
/**
 * Admin Settings Page
 *
 * @package Nostr_Login_Pay
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles admin settings page
 */
class Nostr_Login_Pay_Admin_Settings {

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
        add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        
        // Manually handle settings save to fix checkbox issues
        add_action( 'admin_init', array( $this, 'manual_save_settings' ), 999 );
    }

    /**
     * Manually save settings to handle checkboxes properly
     */
    public function manual_save_settings() {
        // Handle clear BTC price cache action
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'clear_btc_cache' && isset( $_GET['_wpnonce'] ) ) {
            if ( wp_verify_nonce( $_GET['_wpnonce'], 'clear_btc_cache' ) && current_user_can( 'manage_options' ) ) {
                // Clear all BTC price caches
                global $wpdb;
                $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_nwc_btc_price_%'" );
                $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_nwc_btc_price_%'" );
                
                wp_redirect( add_query_arg( array(
                    'page' => 'nostr-login-pay',
                    'tab' => 'nwc',
                    'btc_cache_cleared' => '1'
                ), admin_url( 'options-general.php' ) ) );
                exit;
            }
        }
        
        // Only run when saving our settings
        if ( ! isset( $_POST['option_page'] ) ) {
            return;
        }

        $option_page = $_POST['option_page'];

        if ( $option_page !== 'nostr_login_pay_general' && $option_page !== 'nostr_login_pay_nwc' ) {
            return;
        }

        // Verify nonce
        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'nostr_login_pay_general-options' ) && ! wp_verify_nonce( $_POST['_wpnonce'], 'nostr_login_pay_nwc-options' ) ) {
            return;
        }

        // Define which checkboxes belong to which option page
        $checkboxes_by_page = array(
            'nostr_login_pay_general' => array(
                'nostr_login_pay_enable_login',
                'nostr_login_pay_enable_nwc',
                'nostr_login_pay_auto_create_account',
            ),
            'nostr_login_pay_nwc' => array(
                'nostr_login_pay_nwc_enable_payment_gateway',
            ),
        );

        // Only process checkboxes for the current option page
        if ( isset( $checkboxes_by_page[ $option_page ] ) ) {
            foreach ( $checkboxes_by_page[ $option_page ] as $checkbox ) {
                // If checkbox exists in POST (checked), set to '1', otherwise set to ''
                $value = isset( $_POST[ $checkbox ] ) ? '1' : '';
                update_option( $checkbox, $value );
            }
        }
    }

    /**
     * Initialize default option values if they don't exist
     */
    private function maybe_initialize_defaults() {
        $defaults = array(
            'nostr_login_pay_enable_login' => '1',
            'nostr_login_pay_enable_nwc' => '1',
            'nostr_login_pay_auto_create_account' => '1',
            'nostr_login_pay_nwc_enable_payment_gateway' => '1',
            'nostr_login_pay_default_role' => 'customer',
            'nostr_login_pay_relays' => "wss://relay.damus.io\nwss://relay.primal.net\nwss://nos.lol",
            'nostr_login_pay_nwc_payment_timeout' => 300,
        );

        foreach ( $defaults as $option_name => $default_value ) {
            if ( get_option( $option_name ) === false ) {
                add_option( $option_name, $default_value );
            }
        }
    }


    /**
     * Sanitize NWC connection string
     */
    public function sanitize_nwc_connection( $value ) {
        // Return empty if no value
        if ( empty( $value ) ) {
            return '';
        }
        
        // Trim whitespace
        $value = trim( $value );
        
        // Fix common typo: "nostr walletconnect" should be "nostr+walletconnect"
        if ( strpos( $value, 'nostr walletconnect://' ) === 0 ) {
            $value = str_replace( 'nostr walletconnect://', 'nostr+walletconnect://', $value );
            add_settings_error(
                'nostr_login_pay_nwc_merchant_wallet',
                'nwc_fixed',
                __( 'Connection string was automatically corrected (added missing + sign).', 'nostr-login-pay' ),
                'success'
            );
        }
        
        // Decode URL encoding (Coinos provides URL-encoded strings like wss%3A%2F%2F)
        // Check if it contains URL encoding before decoding
        if ( strpos( $value, '%' ) !== false ) {
            $value = urldecode( $value );
            
            // Show success message about decoding
            add_settings_error(
                'nostr_login_pay_nwc_merchant_wallet',
                'nwc_decoded',
                __( 'Connection string was automatically decoded from URL format.', 'nostr-login-pay' ),
                'updated'
            );
        }
        
        // Basic validation - check if it starts with the right prefix (case-insensitive)
        $value_lower = strtolower( $value );
        if ( strpos( $value_lower, 'nostr+walletconnect://' ) !== 0 && strpos( $value_lower, 'nostr+walletconnect://' ) === false ) {
            // Debug: show what we actually got
            $debug_prefix = substr( $value, 0, 50 );
            $debug_length = strlen( $value );
            add_settings_error(
                'nostr_login_pay_nwc_merchant_wallet',
                'invalid_nwc_format',
                sprintf( 
                    __( 'Invalid NWC format. Expected nostr+walletconnect://... but got (length %d): %s...', 'nostr-login-pay' ),
                    $debug_length,
                    esc_html( $debug_prefix )
                ),
                'error'
            );
            // Still save it for debugging purposes
            // return '';
        }
        
        // Check for required parameters
        if ( strpos( $value, 'relay=' ) === false || strpos( $value, 'secret=' ) === false ) {
            add_settings_error(
                'nostr_login_pay_nwc_merchant_wallet',
                'missing_params',
                __( 'Connection string appears to be missing required parameters (relay or secret).', 'nostr-login-pay' ),
                'error'
            );
            // Return empty if missing critical parameters
            return '';
        }
        
        // All validation passed
        add_settings_error(
            'nostr_login_pay_nwc_merchant_wallet',
            'nwc_saved',
            __( '✓ NWC connection saved successfully!', 'nostr-login-pay' ),
            'success'
        );
        
        return $value;
    }

    /**
     * Add settings page to admin menu
     */
    public function add_menu_page() {
        add_options_page(
            __( 'Nostr Login & Pay Settings', 'nostr-login-pay' ),
            __( 'Nostr Login & Pay', 'nostr-login-pay' ),
            'manage_options',
            'nostr-login-pay',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        // General Settings - checkboxes handled manually
        register_setting( 'nostr_login_pay_general', 'nostr_login_pay_enable_login' );
        register_setting( 'nostr_login_pay_general', 'nostr_login_pay_enable_nwc' );
        register_setting( 'nostr_login_pay_general', 'nostr_login_pay_auto_create_account' );

        register_setting( 'nostr_login_pay_general', 'nostr_login_pay_default_role', array(
            'type' => 'string',
            'default' => 'customer',
            'sanitize_callback' => 'sanitize_text_field',
        ) );

        register_setting( 'nostr_login_pay_general', 'nostr_login_pay_relays', array(
            'type' => 'string',
            'default' => "wss://relay.damus.io\nwss://relay.primal.net\nwss://nos.lol",
            'sanitize_callback' => 'sanitize_textarea_field',
        ) );

        // NWC Settings - checkbox handled manually
        register_setting( 'nostr_login_pay_nwc', 'nostr_login_pay_nwc_enable_payment_gateway' );

        register_setting( 'nostr_login_pay_nwc', 'nostr_login_pay_lightning_address', array(
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field',
        ) );

        register_setting( 'nostr_login_pay_nwc', 'nostr_login_pay_nwc_merchant_wallet', array(
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => array( $this, 'sanitize_nwc_connection' ),
        ) );

        register_setting( 'nostr_login_pay_nwc', 'nostr_login_pay_nwc_payment_timeout', array(
            'type' => 'integer',
            'default' => 300,
            'sanitize_callback' => 'absint',
        ) );

        // Add settings sections and fields
        add_settings_section(
            'nostr_login_pay_general_section',
            __( 'General Settings', 'nostr-login-pay' ),
            array( $this, 'render_general_section' ),
            'nostr-login-pay-general'
        );

        add_settings_field(
            'nostr_login_pay_enable_login',
            __( 'Enable Nostr Login', 'nostr-login-pay' ),
            array( $this, 'render_checkbox_field' ),
            'nostr-login-pay-general',
            'nostr_login_pay_general_section',
            array( 'name' => 'nostr_login_pay_enable_login', 'label' => __( 'Allow users to login with Nostr', 'nostr-login-pay' ) )
        );

        // HIDDEN: NWC Integration checkbox removed - customer wallet connections disabled
        // This was for allowing customers to connect their own NWC wallets (not implemented)
        // Payment gateway is controlled by the "Enable Payment Gateway" setting in NWC Settings tab
        /*
        add_settings_field(
            'nostr_login_pay_enable_nwc',
            __( 'Enable NWC Integration', 'nostr-login-pay' ),
            array( $this, 'render_checkbox_field' ),
            'nostr-login-pay-general',
            'nostr_login_pay_general_section',
            array( 'name' => 'nostr_login_pay_enable_nwc', 'label' => __( 'Allow users to connect NWC wallets', 'nostr-login-pay' ) )
        );
        */

        add_settings_field(
            'nostr_login_pay_auto_create_account',
            __( 'Auto-create Accounts', 'nostr-login-pay' ),
            array( $this, 'render_checkbox_field' ),
            'nostr-login-pay-general',
            'nostr_login_pay_general_section',
            array( 'name' => 'nostr_login_pay_auto_create_account', 'label' => __( 'Automatically create WordPress accounts for new Nostr users', 'nostr-login-pay' ) )
        );

        add_settings_field(
            'nostr_login_pay_default_role',
            __( 'Default User Role', 'nostr-login-pay' ),
            array( $this, 'render_role_select_field' ),
            'nostr-login-pay-general',
            'nostr_login_pay_general_section',
            array( 'name' => 'nostr_login_pay_default_role' )
        );

        // HIDDEN: Nostr Relays field removed - not used by the plugin
        // Default relays are hardcoded in the plugin and work fine for most users
        // Advanced users can modify relays directly in code if needed
        /*
        add_settings_field(
            'nostr_login_pay_relays',
            __( 'Nostr Relays', 'nostr-login-pay' ),
            array( $this, 'render_textarea_field' ),
            'nostr-login-pay-general',
            'nostr_login_pay_general_section',
            array( 'name' => 'nostr_login_pay_relays', 'description' => __( 'One relay URL per line', 'nostr-login-pay' ) )
        );
        */

        // NWC Settings Section
        add_settings_section(
            'nostr_login_pay_nwc_section',
            __( 'NWC Payment Settings', 'nostr-login-pay' ),
            array( $this, 'render_nwc_section' ),
            'nostr-login-pay-nwc'
        );

        add_settings_field(
            'nostr_login_pay_nwc_enable_payment_gateway',
            __( 'Enable Payment Gateway', 'nostr-login-pay' ),
            array( $this, 'render_checkbox_field' ),
            'nostr-login-pay-nwc',
            'nostr_login_pay_nwc_section',
            array( 'name' => 'nostr_login_pay_nwc_enable_payment_gateway', 'label' => __( 'Enable NWC as a WooCommerce payment method', 'nostr-login-pay' ) )
        );

        add_settings_field(
            'nostr_login_pay_lightning_address',
            __( 'Lightning Address (Recommended)', 'nostr-login-pay' ),
            array( $this, 'render_lightning_address_field' ),
            'nostr-login-pay-nwc',
            'nostr_login_pay_nwc_section',
            array( 'name' => 'nostr_login_pay_lightning_address' )
        );

        // NWC Connection field - REQUIRED for auto-verification!
        add_settings_field(
            'nostr_login_pay_nwc_merchant_wallet',
            __( 'NWC Connection (For Auto-Verification)', 'nostr-login-pay' ),
            array( $this, 'render_nwc_connection_field' ),
            'nostr-login-pay-nwc',
            'nostr_login_pay_nwc_section',
            array( 'name' => 'nostr_login_pay_nwc_merchant_wallet' )
        );

        // HIDDEN: Payment timeout and webhook fields not needed for current functionality
        // Keeping code for future use if automatic verification is implemented
        /*
        add_settings_field(
            'nostr_login_pay_nwc_payment_timeout',
            __( 'Payment Timeout', 'nostr-login-pay' ),
            array( $this, 'render_number_field' ),
            'nostr-login-pay-nwc',
            'nostr_login_pay_nwc_section',
            array( 'name' => 'nostr_login_pay_nwc_payment_timeout', 'description' => __( 'Seconds to wait for payment confirmation', 'nostr-login-pay' ) )
        );

        add_settings_field(
            'nostr_login_pay_webhook_url',
            __( 'Webhook URL', 'nostr-login-pay' ),
            array( $this, 'render_webhook_url_field' ),
            'nostr-login-pay-nwc',
            'nostr_login_pay_nwc_section',
            array()
        );
        */
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Initialize default values if they don't exist
        $this->maybe_initialize_defaults();

        $active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'general';
        
        // Show success message if BTC cache was cleared
        if ( isset( $_GET['btc_cache_cleared'] ) ) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><strong>✅ Bitcoin price cache cleared!</strong> The next order will fetch the current BTC price.</p>
            </div>
            <?php
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

            <h2 class="nav-tab-wrapper">
                <a href="?page=nostr-login-pay&tab=general" class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">
                    <?php _e( 'General', 'nostr-login-pay' ); ?>
                </a>
                <a href="?page=nostr-login-pay&tab=nwc" class="nav-tab <?php echo $active_tab === 'nwc' ? 'nav-tab-active' : ''; ?>">
                    <?php _e( 'NWC Settings', 'nostr-login-pay' ); ?>
                </a>
            </h2>

            <form action="options.php" method="post">
                <?php
                if ( $active_tab === 'general' ) {
                    settings_fields( 'nostr_login_pay_general' );
                    do_settings_sections( 'nostr-login-pay-general' );
                } elseif ( $active_tab === 'nwc' ) {
                    settings_fields( 'nostr_login_pay_nwc' );
                    do_settings_sections( 'nostr-login-pay-nwc' );
                }
                submit_button( __( 'Save Settings', 'nostr-login-pay' ) );
                ?>
            </form>
            
            <?php if ( $active_tab === 'nwc' ) : ?>
                <div style="margin-top: 20px; padding: 15px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px;">
                    <h3 style="margin-top: 0;">🔧 Advanced Tools</h3>
                    <p style="margin: 5px 0 15px 0; color: #6b7280;">
                        The plugin caches Bitcoin prices for 5 minutes to improve performance. 
                        If you notice incorrect pricing, clear the cache to fetch fresh rates.
                    </p>
                    <a href="<?php echo wp_nonce_url( admin_url( 'options-general.php?page=nostr-login-pay&tab=nwc&action=clear_btc_cache' ), 'clear_btc_cache' ); ?>" 
                       class="button button-secondary" 
                       onclick="return confirm('Clear Bitcoin price cache and fetch fresh rates?');">
                        🔄 Clear BTC Price Cache
                    </a>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render general section description
     */
    public function render_general_section() {
        echo '<p>' . esc_html__( 'Configure how Nostr login works on your site.', 'nostr-login-pay' ) . '</p>';
    }

    /**
     * Render NWC section description
     */
    public function render_nwc_section() {
        ?>
        <p><?php esc_html_e( 'Configure Lightning Network payment settings for WooCommerce.', 'nostr-login-pay' ); ?></p>
        
        <div style="background: #f0f9ff; border-left: 4px solid #3b82f6; padding: 15px; margin: 15px 0;">
            <h4 style="margin-top: 0; color: #1e40af;">⚡ Lightning Payment Setup</h4>
            <p style="margin: 0 0 10px 0;">
                Enter your <strong>Lightning Address</strong> below to accept instant Bitcoin payments.
            </p>
            <ul style="margin: 5px 0 0 20px;">
                <li>Customers scan QR code and pay with any Lightning wallet</li>
                <li>Browser extension users get instant automatic confirmation</li>
                <li>QR code payments verified manually via "Mark as Paid" button</li>
            </ul>
        </div>
        
        <div style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 15px 0;">
            <h4 style="margin-top: 0; color: #92400e;">🎯 Quick Setup with Coinos</h4>
            <ol style="margin: 10px 0 10px 20px;">
                <li>Sign up at <a href="https://coinos.io/" target="_blank" style="font-weight: bold;">coinos.io</a></li>
                <li>Your Lightning address is: <code>username@coinos.io</code></li>
                <li>Paste it in the "Lightning Address" field below</li>
                <li><strong>Done!</strong> You're ready to accept Lightning payments</li>
            </ol>
        </div>
        <?php
    }

    /**
     * Render checkbox field
     */
    public function render_checkbox_field( $args ) {
        $name = $args['name'];
        $label = isset( $args['label'] ) ? $args['label'] : '';
        $value = get_option( $name, '' );
        $is_checked = ( $value === '1' || $value === 1 || $value === true );
        ?>
        <label>
            <input type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( $is_checked, true ); ?>>
            <?php echo esc_html( $label ); ?>
        </label>
        <?php
    }

    /**
     * Render text field
     */
    public function render_text_field( $args ) {
        $name = $args['name'];
        $description = isset( $args['description'] ) ? $args['description'] : '';
        $value = get_option( $name, '' );
        ?>
        <input type="text" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" class="regular-text">
        <?php if ( $description ) : ?>
            <p class="description"><?php echo esc_html( $description ); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Render number field
     */
    public function render_number_field( $args ) {
        $name = $args['name'];
        $description = isset( $args['description'] ) ? $args['description'] : '';
        $value = get_option( $name, 0 );
        ?>
        <input type="number" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" class="small-text">
        <?php if ( $description ) : ?>
            <p class="description"><?php echo esc_html( $description ); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Render textarea field
     */
    public function render_textarea_field( $args ) {
        $name = $args['name'];
        $description = isset( $args['description'] ) ? $args['description'] : '';
        $value = get_option( $name, '' );
        ?>
        <textarea name="<?php echo esc_attr( $name ); ?>" rows="5" class="large-text"><?php echo esc_textarea( $value ); ?></textarea>
        <?php if ( $description ) : ?>
            <p class="description"><?php echo esc_html( $description ); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Render role select field
     */
    public function render_role_select_field( $args ) {
        $name = $args['name'];
        $value = get_option( $name, 'customer' );
        $roles = wp_roles()->get_names();
        ?>
        <select name="<?php echo esc_attr( $name ); ?>">
            <?php foreach ( $roles as $role_value => $role_name ) : ?>
                <option value="<?php echo esc_attr( $role_value ); ?>" <?php selected( $value, $role_value ); ?>>
                    <?php echo esc_html( $role_name ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    /**
     * Render Lightning Address field
     */
    public function render_lightning_address_field( $args ) {
        $name = $args['name'];
        $value = get_option( $name, '' );
        ?>
        <input 
            type="text" 
            name="<?php echo esc_attr( $name ); ?>" 
            value="<?php echo esc_attr( $value ); ?>" 
            class="regular-text" 
            style="width: 100%; max-width: 400px; font-size: 16px;" 
            placeholder="yourname@coinos.io"
        >
        
        <p class="description" style="margin-top: 10px;">
            <?php _e( 'Your Lightning address where you want to receive payments.', 'nostr-login-pay' ); ?>
        </p>
        
        <?php if ( ! empty( $value ) ) : ?>
            <div style="background: #f0fdf4; border-left: 4px solid #22c55e; padding: 12px; margin: 10px 0; max-width: 500px;">
                <strong style="color: #15803d;">✓ Lightning Address Configured</strong><br>
                <span style="font-size: 13px; color: #166534;">
                    Payments will be sent to: <code style="background: #dcfce7; padding: 2px 6px; border-radius: 3px;"><?php echo esc_html( $value ); ?></code>
                </span>
            </div>
        <?php else : ?>
            <div style="background: #fffbeb; border-left: 4px solid #f59e0b; padding: 12px; margin: 10px 0; max-width: 500px;">
                <strong style="color: #92400e;">⚡ Enter your Lightning Address to start accepting payments</strong><br>
                <span style="font-size: 12px; color: #78350f;">
                    Get one free at <a href="https://coinos.io" target="_blank">coinos.io</a>, 
                    <a href="https://getalby.com" target="_blank">getalby.com</a>, or 
                    <a href="https://strike.me" target="_blank">strike.me</a>
                </span>
            </div>
        <?php endif; ?>
        <?php
    }

    /**
     * Render NWC connection field with help text
     */
    public function render_nwc_connection_field( $args ) {
        $name = $args['name'];
        $value = get_option( $name, '' );
        
        // Validate the stored value
        $is_valid = false;
        if ( ! empty( $value ) ) {
            $is_valid = ( strpos( $value, 'nostr+walletconnect://' ) === 0 ) &&
                        ( strpos( $value, 'relay=' ) !== false ) &&
                        ( strpos( $value, 'secret=' ) !== false );
        }
        ?>
        <input type="text" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" class="regular-text" style="width: 100%; max-width: 600px; font-family: 'Courier New', monospace; font-size: 13px;" placeholder="nostr+walletconnect://...">
        
        <p class="description" style="margin-top: 10px;">
            <?php _e( '<strong>For Auto-Verification of QR Code Payments.</strong> This gives the plugin read-only access to check if invoices are paid.', 'nostr-login-pay' ); ?>
        </p>
        
        <div style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 12px; margin: 10px 0; max-width: 600px;">
            <strong>⚡ Quick Setup (2 minutes):</strong><br>
            <span style="font-size: 12px; color: #92400e;">
                1. Go to <a href="https://coinos.io" target="_blank">coinos.io</a> → Settings → Plugins → NWC<br>
                2. Create connection with: <code>lookup_invoice</code> permission<br>
                3. Copy the connection string (starts with <code>nostr+walletconnect://</code>)<br>
                4. Paste above → <strong>QR payments now auto-complete in seconds!</strong> ✅
            </span>
        </div>

        <?php if ( $is_valid ) : ?>
            <div style="background: #f0fdf4; border-left: 4px solid #22c55e; padding: 12px; margin: 10px 0; max-width: 600px;">
                <strong style="color: #15803d;">✓ NWC Auto-Verification Enabled!</strong><br>
                <span style="font-size: 12px; color: #166534;">
                    ✅ Browser wallet payments: Auto-complete<br>
                    ✅ QR code payments: Auto-complete via NWC lookup_invoice<br>
                    Your store now has fully automated Lightning payments!
                </span>
            </div>
        <?php elseif ( ! empty( $value ) ) : ?>
            <div style="background: #fee; border-left: 4px solid #ef4444; padding: 12px; margin: 10px 0; max-width: 600px;">
                <strong style="color: #dc2626;">✗ Invalid NWC Connection</strong><br>
                <span style="font-size: 12px; color: #991b1b;">
                    The connection string is invalid. It must start with <code>nostr+walletconnect://</code> and include <code>relay=</code> and <code>secret=</code> parameters.
                </span>
            </div>
        <?php else : ?>
            <div style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 12px; margin: 10px 0; max-width: 600px;">
                <strong style="color: #92400e;">⚠️ Manual Payment Verification Required</strong><br>
                <span style="font-size: 12px; color: #92400e;">
                    QR code payments require manual "Mark as Paid" button click.<br>
                    <strong>Add NWC above for instant auto-verification!</strong>
                </span>
            </div>
        <?php endif; ?>
        <?php
    }

    /**
     * Render webhook URL field (read-only display)
     */
    public function render_webhook_url_field( $args ) {
        $webhook_url = rest_url( 'nostr-login-pay/v1/webhook/payment' );
        ?>
        <input 
            type="text" 
            value="<?php echo esc_url( $webhook_url ); ?>" 
            class="regular-text" 
            readonly
            onclick="this.select()"
            style="width: 100%; max-width: 600px; background: #f9f9f9; cursor: pointer;"
        >
        
        <p class="description" style="margin-top: 10px; color: #6b7280;">
            <?php _e( 'Not required. Webhooks are not currently configured.', 'nostr-login-pay' ); ?>
        </p>
        
        <div style="background: #f0fdf4; border-left: 4px solid #22c55e; padding: 12px; margin: 10px 0; max-width: 600px;">
            <strong style="color: #15803d;">✅ How Payment Verification Works:</strong><br>
            <span style="font-size: 12px; color: #166534;">
                <strong>Browser Extension Payments:</strong> Auto-complete instantly (no action needed!)<br>
                <strong>QR Code Payments:</strong> Use the "✓ Mark as Paid" button in order admin (30 seconds)
            </span>
        </div>
        <?php
    }
}

