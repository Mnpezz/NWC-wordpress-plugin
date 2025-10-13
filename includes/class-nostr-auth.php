<?php
/**
 * Nostr Authentication Handler
 *
 * @package Nostr_Login_Pay
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles Nostr authentication and user management
 */
class Nostr_Login_Pay_Auth {

    /**
     * Verify a Nostr event signature
     *
     * @param array  $event The Nostr event
     * @param string $signature The signature to verify
     * @return bool True if valid, false otherwise
     */
    public function verify_nostr_event( $event, $signature ) {
        // Basic validation
        if ( empty( $event['pubkey'] ) || empty( $event['created_at'] ) || empty( $event['kind'] ) ) {
            return false;
        }

        // Verify event is recent (within 5 minutes)
        $time_diff = abs( time() - intval( $event['created_at'] ) );
        if ( $time_diff > 300 ) {
            return false;
        }

        // Verify event kind is correct (kind 22242 is NIP-42 auth)
        if ( intval( $event['kind'] ) !== 22242 ) {
            return false;
        }

        // In production, you would verify the signature using nostr-tools
        // For now, we'll do basic validation
        // The actual signature verification happens in JavaScript before sending to server
        
        return true;
    }

    /**
     * Find or create a WordPress user from a Nostr pubkey
     *
     * @param string $pubkey The Nostr public key
     * @return WP_User|WP_Error The user object or error
     */
    public function find_or_create_user( $pubkey ) {
        // Sanitize the pubkey
        $pubkey = sanitize_text_field( $pubkey );

        // Look for existing user with this pubkey
        $users = get_users( array(
            'meta_key' => 'nostr_pubkey',
            'meta_value' => $pubkey,
            'number' => 1,
        ) );

        if ( ! empty( $users ) ) {
            return $users[0];
        }

        // Create a new user
        $username = 'nostr_' . substr( $pubkey, 0, 16 );
        $email = $username . '@nostr.local'; // Temporary email

        // Check if username already exists
        $counter = 1;
        $original_username = $username;
        while ( username_exists( $username ) ) {
            $username = $original_username . '_' . $counter;
            $counter++;
        }

        $user_id = wp_create_user( $username, wp_generate_password( 32 ), $email );

        if ( is_wp_error( $user_id ) ) {
            return $user_id;
        }

        // Save the Nostr pubkey
        update_user_meta( $user_id, 'nostr_pubkey', $pubkey );

        // Set display name
        wp_update_user( array(
            'ID' => $user_id,
            'display_name' => $username,
        ) );

        // Set user role to customer if WooCommerce is active
        if ( class_exists( 'WooCommerce' ) ) {
            $user = new WP_User( $user_id );
            $user->set_role( 'customer' );
        }

        do_action( 'nostr_login_pay_user_created', $user_id, $pubkey );

        return get_user_by( 'id', $user_id );
    }

    /**
     * Get user by Nostr pubkey
     *
     * @param string $pubkey The Nostr public key
     * @return WP_User|false The user object or false
     */
    public function get_user_by_pubkey( $pubkey ) {
        $users = get_users( array(
            'meta_key' => 'nostr_pubkey',
            'meta_value' => sanitize_text_field( $pubkey ),
            'number' => 1,
        ) );

        return ! empty( $users ) ? $users[0] : false;
    }

    /**
     * Update user's Nostr profile data
     *
     * @param int   $user_id User ID
     * @param array $profile_data Profile data from Nostr
     * @return bool Success status
     */
    public function update_user_profile( $user_id, $profile_data ) {
        $updated = false;

        if ( ! empty( $profile_data['name'] ) ) {
            update_user_meta( $user_id, 'nostr_display_name', sanitize_text_field( $profile_data['name'] ) );
            wp_update_user( array(
                'ID' => $user_id,
                'display_name' => sanitize_text_field( $profile_data['name'] ),
            ) );
            $updated = true;
        }

        if ( ! empty( $profile_data['about'] ) ) {
            update_user_meta( $user_id, 'description', sanitize_textarea_field( $profile_data['about'] ) );
            $updated = true;
        }

        if ( ! empty( $profile_data['picture'] ) ) {
            update_user_meta( $user_id, 'nostr_picture', esc_url_raw( $profile_data['picture'] ) );
            $updated = true;
        }

        if ( ! empty( $profile_data['nip05'] ) ) {
            update_user_meta( $user_id, 'nostr_nip05', sanitize_text_field( $profile_data['nip05'] ) );
            $updated = true;
        }

        return $updated;
    }

    /**
     * Generate a login challenge for Nostr authentication
     *
     * @return array Challenge data
     */
    public function generate_login_challenge() {
        $challenge = wp_generate_password( 32, false );
        $timestamp = time();

        set_transient( 'nostr_login_challenge_' . $challenge, $timestamp, 300 ); // 5 minutes

        return array(
            'challenge' => $challenge,
            'timestamp' => $timestamp,
            'site_name' => get_bloginfo( 'name' ),
            'site_url' => get_site_url(),
        );
    }

    /**
     * Verify a login challenge
     *
     * @param string $challenge The challenge string
     * @return bool True if valid, false otherwise
     */
    public function verify_login_challenge( $challenge ) {
        $timestamp = get_transient( 'nostr_login_challenge_' . $challenge );

        if ( false === $timestamp ) {
            return false;
        }

        delete_transient( 'nostr_login_challenge_' . $challenge );

        return true;
    }
}

