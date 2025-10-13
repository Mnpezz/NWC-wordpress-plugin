<?php
/**
 * Quick diagnostic: Check if user's NWC wallet is saved
 * 
 * Usage: Navigate to this file in your browser while logged in
 */

// Load WordPress
require_once(__DIR__ . '/../../../wp-load.php');

if (!is_user_logged_in()) {
    echo '<h1>❌ Not Logged In</h1>';
    echo '<p>Please log in first, then refresh this page.</p>';
    exit;
}

$user_id = get_current_user_id();
$user = wp_get_current_user();
$nwc_wallet = get_user_meta($user_id, 'nostr_nwc_connection', true);

echo '<h1>🔍 User Wallet Diagnostic</h1>';
echo '<hr>';

echo '<h2>User Info:</h2>';
echo '<p><strong>User ID:</strong> ' . $user_id . '</p>';
echo '<p><strong>Username:</strong> ' . $user->user_login . '</p>';
echo '<p><strong>Email:</strong> ' . $user->user_email . '</p>';

echo '<hr>';
echo '<h2>NWC Wallet Connection:</h2>';

if (empty($nwc_wallet)) {
    echo '<p style="color: red; font-weight: bold;">❌ NO WALLET CONNECTED</p>';
    echo '<p>Go to: <a href="' . wc_get_account_endpoint_url('nwc-wallet') . '">My Account → NWC Wallet</a> to connect your wallet.</p>';
} else {
    echo '<p style="color: green; font-weight: bold;">✅ WALLET CONNECTED</p>';
    
    // Parse the connection string
    if (preg_match('/^nostr\+walletconnect:\/\/([^?]+)\?(.+)$/', $nwc_wallet, $matches)) {
        $pubkey = $matches[1];
        parse_str($matches[2], $params);
        
        echo '<p><strong>Pubkey:</strong> <code>' . substr($pubkey, 0, 32) . '...</code></p>';
        
        if (isset($params['relay'])) {
            echo '<p><strong>Relay:</strong> <code>' . $params['relay'] . '</code></p>';
        }
        
        if (isset($params['lud16'])) {
            echo '<p><strong>Lightning Address:</strong> <code>' . $params['lud16'] . '</code></p>';
        }
        
        echo '<p><strong>Full Connection String:</strong></p>';
        echo '<textarea readonly style="width: 100%; height: 100px; font-family: monospace; font-size: 12px;">' . $nwc_wallet . '</textarea>';
    } else {
        echo '<p style="color: orange;">⚠️ Wallet format might be invalid</p>';
        echo '<p><strong>Saved Value:</strong></p>';
        echo '<textarea readonly style="width: 100%; height: 100px; font-family: monospace; font-size: 12px;">' . $nwc_wallet . '</textarea>';
    }
}

echo '<hr>';
echo '<h2>What This Means:</h2>';

if (empty($nwc_wallet)) {
    echo '<p>The "⚡ Pay with Connected Wallet" button will <strong>NOT</strong> appear on the payment page.</p>';
    echo '<p>You will only see:</p>';
    echo '<ul>';
    echo '<li>📱 QR Code to scan</li>';
    echo '<li>📋 Copy Invoice button</li>';
    echo '<li>⚡ Pay with Browser Wallet button (if extension detected)</li>';
    echo '</ul>';
} else {
    echo '<p>The "⚡ Pay with Connected Wallet" button <strong>SHOULD</strong> appear on the payment page.</p>';
    echo '<p>You will see:</p>';
    echo '<ul>';
    echo '<li>🟠 <strong>Pay with Connected Wallet</strong> (big orange button at top)</li>';
    echo '<li>— OR —</li>';
    echo '<li>📱 QR Code to scan</li>';
    echo '<li>📋 Copy Invoice button</li>';
    echo '<li>⚡ Pay with Browser Wallet button</li>';
    echo '</ul>';
}

echo '<hr>';
echo '<p><a href="' . admin_url('admin.php?page=woocommerce') . '">← Back to Dashboard</a></p>';

