<?php
if (!defined('ABSPATH')) exit;

class TSF_Auth {
    public static function register($email, $password, $display_name = '') {
        global $wpdb;
        $email = sanitize_email($email);
        if (!$email || !$password) return new WP_Error('invalid', 'Email and password required.');

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM " . TSF_DB::table('tsf_driver_users') . " WHERE email = %s",
            $email
        ));
        if ($exists) return new WP_Error('exists', 'Email already registered.');

        $wpdb->insert(TSF_DB::table('tsf_driver_users'), [
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'display_name' => sanitize_text_field($display_name),
        ]);

        return (int)$wpdb->insert_id;
    }

    public static function login($email, $password) {
        global $wpdb;
        $email = sanitize_email($email);
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . TSF_DB::table('tsf_driver_users') . " WHERE email = %s",
            $email
        ), ARRAY_A);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return new WP_Error('invalid_login', 'Invalid email or password.');
        }

        $token = wp_generate_password(48, false, false);
        $wpdb->insert(TSF_DB::table('tsf_driver_sessions'), [
            'driver_user_id' => (int)$user['id'],
            'token' => $token,
            'expires_at' => gmdate('Y-m-d H:i:s', strtotime('+30 days')),
        ]);

        return [
            'token' => $token,
            'user' => [
                'id' => (int)$user['id'],
                'email' => $user['email'],
                'display_name' => $user['display_name'],
            ]
        ];
    }

    public static function operator_login($email, $password) {
        global $wpdb;
        $email = sanitize_email($email);
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . TSF_DB::table('tsf_operator_users') . " WHERE email = %s",
            $email
        ), ARRAY_A);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return new WP_Error('invalid_login', 'Invalid operator email or password.');
        }

        $token = wp_generate_password(48, false, false);
        $wpdb->insert(TSF_DB::table('tsf_operator_sessions'), [
            'operator_user_id' => (int)$user['id'],
            'token' => $token,
            'expires_at' => gmdate('Y-m-d H:i:s', strtotime('+30 days')),
        ]);

        return [
            'token' => $token,
            'operator' => [
                'id' => (int)$user['id'],
                'email' => $user['email'],
                'display_name' => $user['display_name'],
            ]
        ];
    }
}
