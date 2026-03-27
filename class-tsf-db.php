<?php
if (!defined('ABSPATH')) exit;

class TSF_DB {
    public static function table($name) {
        global $wpdb;
        return $wpdb->prefix . $name;
    }

    public static function activate() {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        global $wpdb;
        $c = $wpdb->get_charset_collate();

        $queries = [];

        $queries[] = "CREATE TABLE " . self::table('tsf_driver_users') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            email VARCHAR(190) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            display_name VARCHAR(190) DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY email (email)
        ) $c;";

        $queries[] = "CREATE TABLE " . self::table('tsf_driver_sessions') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            driver_user_id BIGINT UNSIGNED NOT NULL,
            token VARCHAR(100) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY token (token),
            KEY driver_user_id (driver_user_id)
        ) $c;";

        $queries[] = "CREATE TABLE " . self::table('tsf_operator_users') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            email VARCHAR(190) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            display_name VARCHAR(190) DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY email (email)
        ) $c;";

        $queries[] = "CREATE TABLE " . self::table('tsf_operator_sessions') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            operator_user_id BIGINT UNSIGNED NOT NULL,
            token VARCHAR(100) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY token (token),
            KEY operator_user_id (operator_user_id)
        ) $c;";

        $queries[] = "CREATE TABLE " . self::table('tsf_operator_listing_access') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            operator_user_id BIGINT UNSIGNED NOT NULL,
            post_id BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY operator_listing (operator_user_id, post_id)
        ) $c;";

        $queries[] = "CREATE TABLE " . self::table('tsf_listing_details') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT UNSIGNED NOT NULL,
            address_line_1 VARCHAR(190) DEFAULT '',
            town_city VARCHAR(120) DEFAULT '',
            county VARCHAR(120) DEFAULT '',
            postcode VARCHAR(32) DEFAULT '',
            country VARCHAR(120) DEFAULT 'United Kingdom',
            latitude DECIMAL(10,7) DEFAULT NULL,
            longitude DECIMAL(10,7) DEFAULT NULL,
            opening_hours VARCHAR(190) DEFAULT '',
            parking_type VARCHAR(80) DEFAULT '',
            price_night DECIMAL(10,2) DEFAULT NULL,
            secure_parking TINYINT(1) DEFAULT 0,
            showers TINYINT(1) DEFAULT 0,
            overnight_parking TINYINT(1) DEFAULT 0,
            fuel TINYINT(1) DEFAULT 0,
            food TINYINT(1) DEFAULT 0,
            toilets TINYINT(1) DEFAULT 0,
            featured TINYINT(1) DEFAULT 0,
            owner_email VARCHAR(190) DEFAULT '',
            feature_style VARCHAR(40) DEFAULT 'standard',
            is_featured_paid TINYINT(1) DEFAULT 0,
            featured_until DATETIME DEFAULT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY post_id (post_id),
            KEY postcode (postcode),
            KEY town_city (town_city),
            KEY latlng (latitude, longitude),
            KEY featured (featured)
        ) $c;";

        $queries[] = "CREATE TABLE " . self::table('tsf_reviews') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT UNSIGNED NOT NULL,
            driver_user_id BIGINT UNSIGNED DEFAULT NULL,
            rating TINYINT UNSIGNED NOT NULL,
            review_text TEXT,
            visit_date DATE DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY status (status)
        ) $c;";

        $queries[] = "CREATE TABLE " . self::table('tsf_favourites') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT UNSIGNED NOT NULL,
            driver_user_id BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY post_driver (post_id, driver_user_id)
        ) $c;";

        $queries[] = "CREATE TABLE " . self::table('tsf_submissions') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            driver_user_id BIGINT UNSIGNED DEFAULT NULL,
            submission_type VARCHAR(20) NOT NULL DEFAULT 'listing',
            target_post_id BIGINT UNSIGNED DEFAULT NULL,
            payload LONGTEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY target_post_id (target_post_id)
        ) $c;";

        $queries[] = "CREATE TABLE " . self::table('tsf_photos') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT UNSIGNED NOT NULL,
            driver_user_id BIGINT UNSIGNED DEFAULT NULL,
            attachment_id BIGINT UNSIGNED NOT NULL,
            caption VARCHAR(255) DEFAULT '',
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY attachment_id (attachment_id),
            KEY status (status)
        ) $c;";

        $queries[] = "CREATE TABLE " . self::table('tsf_moderation_log') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            item_type VARCHAR(20) NOT NULL,
            item_id BIGINT UNSIGNED NOT NULL,
            action VARCHAR(40) NOT NULL,
            note TEXT,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY item_lookup (item_type, item_id)
        ) $c;";

        $queries[] = "CREATE TABLE " . self::table('tsf_notifications') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            driver_user_id BIGINT UNSIGNED DEFAULT NULL,
            email_to VARCHAR(190) DEFAULT '',
            subject VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            sent_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY driver_user_id (driver_user_id),
            KEY sent_at (sent_at)
        ) $c;";

        $queries[] = "CREATE TABLE " . self::table('tsf_operator_claims') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT UNSIGNED NOT NULL,
            operator_name VARCHAR(190) DEFAULT '',
            operator_email VARCHAR(190) NOT NULL,
            phone VARCHAR(60) DEFAULT '',
            notes TEXT,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY status (status)
        ) $c;";

                $queries[] = "CREATE TABLE " . self::table('tsf_ratings') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT UNSIGNED NOT NULL,
            wp_user_id BIGINT UNSIGNED NOT NULL,
            rating TINYINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY post_user (post_id, wp_user_id),
            KEY post_id (post_id),
            KEY wp_user_id (wp_user_id)
        ) $c;";
$queries[] = "CREATE TABLE " . self::table('tsf_feature_orders') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT UNSIGNED NOT NULL,
            operator_email VARCHAR(190) NOT NULL,
            plan_key VARCHAR(40) NOT NULL,
            amount_gbp DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            payment_reference VARCHAR(120) DEFAULT '',
            checkout_session_id VARCHAR(190) DEFAULT '',
            checkout_status VARCHAR(40) DEFAULT '',
            featured_until DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY status (status)
        ) $c;";
        $queries[] = "CREATE TABLE " . self::table('tsf_saved_searches') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            driver_user_id BIGINT UNSIGNED NOT NULL,
            label VARCHAR(190) NOT NULL,
            search_payload LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY driver_user_id (driver_user_id)
        ) $c;";

        foreach ($queries as $sql) {
            dbDelta($sql);
        }

        TSF_Post_Types::register();
        flush_rewrite_rules();

        if (!get_page_by_path('truckstop-finder')) {
            wp_insert_post([
                'post_title' => 'Truckstop Finder',
                'post_name' => 'truckstop-finder',
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_content' => '[truckstop_finder]'
            ]);
        }
    }

public static function deactivate() {
    if (wp_next_scheduled('tsf_send_notifications')) {
        wp_clear_scheduled_hook('tsf_send_notifications');
    }
    if (wp_next_scheduled('tsf_featured_maintenance')) {
        wp_clear_scheduled_hook('tsf_featured_maintenance');
    }
    flush_rewrite_rules();
}
}
