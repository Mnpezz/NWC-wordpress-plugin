<?php
/**
 * NWC Plugin Diagnostics
 * Access via: http://localhost:10003/wp-content/plugins/nostr-login-and-pay/diagnostics.php
 */

// Load WordPress
require_once('../../../../wp-load.php');

// Check if user is admin
if (!current_user_can('manage_options')) {
    die('Unauthorized');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>NWC Plugin Diagnostics</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; padding: 20px; background: #f1f1f1; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #0073aa; padding-bottom: 10px; }
        h2 { color: #0073aa; margin-top: 30px; }
        .setting { padding: 10px; margin: 10px 0; background: #f9f9f9; border-left: 4px solid #0073aa; }
        .setting label { font-weight: bold; display: block; margin-bottom: 5px; }
        .setting .value { color: #333; }
        .success { color: #22c55e; }
        .error { color: #ef4444; }
        .gateway { padding: 10px; margin: 10px 0; background: #f0f9ff; border-left: 4px solid #3b82f6; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Nostr Login & Pay - Diagnostics</h1>
        
        <h2>Plugin Settings</h2>
        
        <div class="setting">
            <label>Enable Nostr Login:</label>
            <div class="value <?php echo get_option('nostr_login_pay_enable_login') === '1' ? 'success' : 'error'; ?>">
                <?php echo get_option('nostr_login_pay_enable_login') === '1' ? '✓ Enabled' : '✗ Disabled'; ?>
            </div>
        </div>
        
        <div class="setting">
            <label>Enable NWC Integration:</label>
            <div class="value <?php echo get_option('nostr_login_pay_enable_nwc') === '1' ? 'success' : 'error'; ?>">
                <?php echo get_option('nostr_login_pay_enable_nwc') === '1' ? '✓ Enabled' : '✗ Disabled'; ?>
            </div>
        </div>
        
        <div class="setting">
            <label>Auto-create Accounts:</label>
            <div class="value <?php echo get_option('nostr_login_pay_auto_create_account') === '1' ? 'success' : 'error'; ?>">
                <?php echo get_option('nostr_login_pay_auto_create_account') === '1' ? '✓ Enabled' : '✗ Disabled'; ?>
            </div>
        </div>
        
        <div class="setting">
            <label>Enable Payment Gateway:</label>
            <div class="value <?php echo get_option('nostr_login_pay_nwc_enable_payment_gateway') === '1' ? 'success' : 'error'; ?>">
                <?php echo get_option('nostr_login_pay_nwc_enable_payment_gateway') === '1' ? '✓ Enabled' : '✗ Disabled'; ?>
            </div>
        </div>
        
        <div class="setting">
            <label>Merchant NWC Connection:</label>
            <div class="value">
                <?php 
                $wallet = get_option('nostr_login_pay_nwc_merchant_wallet');
                if (!empty($wallet)) {
                    echo '<span class="success">✓ Connected (' . strlen($wallet) . ' characters)</span><br>';
                    echo '<code style="font-size: 11px; word-break: break-all;">' . esc_html(substr($wallet, 0, 50)) . '...</code>';
                } else {
                    echo '<span class="error">✗ Not configured</span>';
                }
                ?>
            </div>
        </div>
        
        <h2>WooCommerce Integration</h2>
        
        <?php if (class_exists('WooCommerce')): ?>
            <div class="setting">
                <label>WooCommerce Status:</label>
                <div class="value success">✓ Active</div>
            </div>
            
            <h3>All Payment Gateways (Registered with WooCommerce):</h3>
            <?php
            $gateways = WC()->payment_gateways->payment_gateways();
            $nwc_found = false;
            $coinos_found = false;
            
            echo '<p style="font-size: 13px; color: #666;">Total: ' . count($gateways) . ' gateway(s) registered</p>';
            
            foreach ($gateways as $id => $gateway):
                if ($id === 'nwc') $nwc_found = true;
                if ($id === 'coinos_lightning') $coinos_found = true;
                
                $class_name = get_class($gateway);
            ?>
                <div class="gateway">
                    <strong><?php echo esc_html($gateway->get_title()); ?></strong> (ID: <?php echo esc_html($id); ?>)<br>
                    <span style="font-size: 12px; color: #666;">Class: <?php echo esc_html($class_name); ?></span><br>
                    Enabled in Settings: <?php echo $gateway->enabled === 'yes' ? '<span class="success">Yes</span>' : '<span class="error">No</span>'; ?><br>
                    Available (is_available()): <?php echo $gateway->is_available() ? '<span class="success">Yes</span>' : '<span class="error">No</span>'; ?>
                </div>
            <?php endforeach; ?>
            
            <h3>Plugin Conflicts Check:</h3>
            
            <?php if (!$nwc_found): ?>
                <div class="setting" style="background: #fee; border-left-color: #ef4444;">
                    <strong class="error">⚠️ NWC Gateway Not Found!</strong>
                    <p>The NWC payment gateway is not registered with WooCommerce.</p>
                    <p><strong>Possible causes:</strong></p>
                    <ul>
                        <li>Gateway class file not loaded</li>
                        <li>Plugin conflict preventing registration</li>
                        <li>Filter priority issue</li>
                    </ul>
                </div>
            <?php else: ?>
                <div class="setting" style="background: #f0fdf4; border-left-color: #22c55e;">
                    <strong class="success">✓ NWC Gateway Found!</strong>
                </div>
            <?php endif; ?>
            
            <?php if ($coinos_found && $nwc_found): ?>
                <div class="setting" style="background: #f0f9ff; border-left-color: #3b82f6;">
                    <strong style="color: #1e40af;">✓ Both NWC and Coinos Active</strong>
                    <p>Both payment gateways are successfully registered and can coexist.</p>
                </div>
            <?php elseif ($coinos_found && !$nwc_found): ?>
                <div class="setting" style="background: #fef3c7; border-left-color: #f59e0b;">
                    <strong style="color: #92400e;">⚠️ Coinos Active, NWC Not Found</strong>
                    <p>Coinos is registered but NWC is not. There may be a plugin conflict.</p>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="setting" style="background: #fee; border-left-color: #ef4444;">
                <label>WooCommerce Status:</label>
                <div class="value error">✗ Not Active</div>
            </div>
        <?php endif; ?>
        
        <h2>Class Checks</h2>
        
        <div class="setting">
            <label>WC_Gateway_NWC class:</label>
            <div class="value <?php echo class_exists('WC_Gateway_NWC') ? 'success' : 'error'; ?>">
                <?php echo class_exists('WC_Gateway_NWC') ? '✓ Loaded' : '✗ Not loaded'; ?>
            </div>
        </div>
        
        <div class="setting">
            <label>WC_Gateway_NWC_Blocks_Support class:</label>
            <div class="value <?php echo class_exists('WC_Gateway_NWC_Blocks_Support') ? 'success' : 'error'; ?>">
                <?php echo class_exists('WC_Gateway_NWC_Blocks_Support') ? '✓ Loaded' : '✗ Not loaded'; ?>
            </div>
        </div>
        
    </div>
</body>
</html>

