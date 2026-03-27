<?php
if (!defined('ABSPATH')) exit;

class TSF_Helpers {
    public static function bool($v) { return !empty($v) ? 1 : 0; }

    public static function tsf_log($msg) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[TSF] ' . print_r($msg, true));
        }
    }

    public static function get_details($post_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . TSF_DB::table('tsf_listing_details') . " WHERE post_id = %d",
            $post_id
        ), ARRAY_A);
    }

    public static function upsert_details($post_id, $data) {
        global $wpdb;
        $table = TSF_DB::table('tsf_listing_details');
        $existing = self::get_details($post_id);

        $clean = [
            'post_id' => (int)$post_id,
            'address_line_1' => sanitize_text_field($data['address_line_1'] ?? ''),
            'town_city' => sanitize_text_field($data['town_city'] ?? ''),
            'county' => sanitize_text_field($data['county'] ?? ''),
            'postcode' => sanitize_text_field($data['postcode'] ?? ''),
            'country' => sanitize_text_field($data['country'] ?? 'United Kingdom'),
            'latitude' => ($data['latitude'] ?? '') !== '' ? (float)$data['latitude'] : null,
            'longitude' => ($data['longitude'] ?? '') !== '' ? (float)$data['longitude'] : null,
            'opening_hours' => sanitize_text_field($data['opening_hours'] ?? ''),
            'parking_type' => sanitize_text_field($data['parking_type'] ?? ''),
            'price_night' => ($data['price_night'] ?? '') !== '' ? (float)$data['price_night'] : null,
            'secure_parking' => self::bool($data['secure_parking'] ?? 0),
            'showers' => self::bool($data['showers'] ?? 0),
            'overnight_parking' => self::bool($data['overnight_parking'] ?? 0),
            'fuel' => self::bool($data['fuel'] ?? 0),
            'food' => self::bool($data['food'] ?? 0),
            'toilets' => self::bool($data['toilets'] ?? 0),
            'featured' => self::bool($data['featured'] ?? 0),
            'owner_email' => sanitize_email($data['owner_email'] ?? ''),
            'feature_style' => sanitize_text_field($data['feature_style'] ?? 'standard'),
            'is_featured_paid' => self::bool($data['is_featured_paid'] ?? 0),
            'featured_until' => !empty($data['featured_until']) ? sanitize_text_field($data['featured_until']) : null,
            'updated_at' => current_time('mysql'),
        ];

        if ($existing) {
            $wpdb->update($table, $clean, ['post_id' => $post_id]);
        } else {
            $wpdb->insert($table, $clean);
        }
    }

    public static function review_summary($post_id) {
        global $wpdb;
        $t = TSF_DB::table('tsf_reviews');
        $columns = $wpdb->get_col("SHOW COLUMNS FROM {$t}", 0);
        $columns = is_array($columns) ? $columns : [];
        $status_filter = in_array('status', $columns, true) ? " AND status IN ('published','approved')" : "";
        $avg = $wpdb->get_var($wpdb->prepare("SELECT AVG(rating) FROM {$t} WHERE post_id = %d{$status_filter}", $post_id));
        $count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$t} WHERE post_id = %d{$status_filter}", $post_id));
        return ['avg' => $avg ? round((float)$avg, 1) : 0, 'count' => (int)$count];
    }

    public static function current_wp_user_from_request($request = null) {
        if (is_user_logged_in()) {
            return wp_get_current_user();
        }

        $auth = '';
        if ($request && method_exists($request, 'get_header')) {
            $auth = (string)$request->get_header('Authorization');
        }
        if (!$auth && isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $auth = (string)$_SERVER['HTTP_AUTHORIZATION'];
        }
        if ($auth && preg_match('/Bearer\s+(.*)$/i', $auth, $m)) {
            $token = sanitize_text_field($m[1]);
            $legacy = get_users([
                'meta_key' => 'tsf_token',
                'meta_value' => $token,
                'number' => 1,
                'count_total' => false,
            ]);
            if (!empty($legacy) && $legacy[0] instanceof WP_User) {
                return $legacy[0];
            }
        }

        return null;
    }

    public static function auth_user_from_request($request) {
        $wp_user = self::current_wp_user_from_request($request);
        if (!$wp_user) return null;
        return [
            'id' => (int)$wp_user->ID,
            'display_name' => $wp_user->display_name,
            'email' => $wp_user->user_email,
        ];
    }

    public static function operator_auth_from_request($request) {
        global $wpdb;
        $auth = $request->get_header('x-operator-authorization');
        if (!$auth || stripos($auth, 'Bearer ') !== 0) return null;
        $token = trim(substr($auth, 7));
        if (!$token) return null;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT u.* FROM " . TSF_DB::table('tsf_operator_sessions') . " s
             INNER JOIN " . TSF_DB::table('tsf_operator_users') . " u ON u.id = s.operator_user_id
             WHERE s.token = %s AND s.expires_at > %s",
            $token,
            current_time('mysql')
        ), ARRAY_A);
    }

public static function feature_state($details) {
    $is_paid = !empty($details['is_featured_paid']);
    $featured_until = !empty($details['featured_until']) ? strtotime($details['featured_until']) : false;
    if ($is_paid && $featured_until && $featured_until < time()) return 'expired';
    if ($is_paid && $featured_until && $featured_until >= time()) return 'active';
    if (!empty($details['featured'])) return 'manual';
    return 'none';
}

public static function trust_label($review_count, $featured = 0) {
        if (!empty($featured)) return 'Verified operator';
        if ((int)$review_count >= 10) return 'Driver trusted';
        if ((int)$review_count >= 3) return 'Reviewed';
        return 'New listing';
    }

    public static function listing_payload($post) {
        $d = self::get_details($post->ID);
        if (!$d) {
            $d = [
                'latitude' => null, 'longitude' => null, 'town_city' => '', 'postcode' => '',
                'showers' => 0, 'secure_parking' => 0, 'overnight_parking' => 0,
                'fuel' => 0, 'food' => 0, 'toilets' => 0, 'price_night' => null, 'featured' => 0,
                'opening_hours' => '', 'parking_type' => '', 'owner_email' => '', 'feature_style' => 'standard',
                'is_featured_paid' => 0, 'featured_until' => ''
            ];
        }
        $s = self::review_summary($post->ID);

        return [
            'id' => (int)$post->ID,
            'title' => $post->post_title,
            'url' => get_permalink($post->ID),
            'excerpt' => wp_trim_words($post->post_excerpt ?: $post->post_content, 18),
            'lat' => $d['latitude'],
            'lng' => $d['longitude'],
            'town_city' => $d['town_city'],
            'postcode' => $d['postcode'],
            'opening_hours' => $d['opening_hours'],
            'parking_type' => $d['parking_type'],
            'showers' => (int)$d['showers'],
            'secure_parking' => (int)$d['secure_parking'],
            'overnight_parking' => (int)$d['overnight_parking'],
            'fuel' => (int)$d['fuel'],
            'food' => (int)$d['food'],
            'toilets' => (int)$d['toilets'],
            'price_night' => $d['price_night'],
            'featured' => (int)$d['featured'],
            'owner_email' => $d['owner_email'] ?? '',
            'feature_style' => $d['feature_style'] ?? 'standard',
            'is_featured_paid' => (int)($d['is_featured_paid'] ?? 0),
            'featured_until' => $d['featured_until'] ?? '',
            'rating' => $s['avg'],
            'review_count' => $s['count'],
            'trust_label' => self::trust_label($s['count'], (int)$d['featured']),
            'feature_state' => self::feature_state($d),
            'photo_count' => self::approved_photo_count($post->ID),
            'rating' => self::ratings_summary($post->ID)['rating'],
            'rating_count' => self::ratings_summary($post->ID)['rating_count'],
            'has_photos' => self::approved_photo_count($post->ID) > 0 ? 1 : 0,
        ];
    }

    public static function haversine_miles($lat1, $lon1, $lat2, $lon2) {
        $earth = 3958.8;
        $dLat = deg2rad((float)$lat2 - (float)$lat1);
        $dLon = deg2rad((float)$lon2 - (float)$lon1);
        $a = sin($dLat / 2) * sin($dLat / 2) + cos(deg2rad((float)$lat1)) * cos(deg2rad((float)$lat2)) * sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earth * $c;
    }

    public static function title_similarity($a, $b) {
        similar_text(strtolower(trim((string)$a)), strtolower(trim((string)$b)), $percent);
        return (float)$percent;
    }

    public static function find_duplicate_listing($title, $postcode, $lat = null, $lng = null) {
        $title = trim((string)$title);
        $postcode = trim((string)$postcode);
        $posts = get_posts([
            'post_type' => 'truckstop',
            'post_status' => 'publish',
            'posts_per_page' => 300,
            's' => $title ?: $postcode,
        ]);

        foreach ($posts as $p) {
            $d = self::get_details($p->ID);
            $same_postcode = $postcode && !empty($d['postcode']) && strtolower(trim($d['postcode'])) === strtolower($postcode);
            $title_match = self::title_similarity($p->post_title, $title) >= 88;
            $nearby = false;
            if ($lat !== null && $lng !== null && !empty($d['latitude']) && !empty($d['longitude'])) {
                $nearby = self::haversine_miles($lat, $lng, $d['latitude'], $d['longitude']) <= 0.5;
            }
            if ($same_postcode || ($title_match && $nearby) || ($title_match && $same_postcode)) return (int)$p->ID;
        }
        return 0;
    }

    public static function nearby_listings($post_id, $limit = 6) {
        $post = get_post($post_id);
        if (!$post) return [];
        $base = self::listing_payload($post);
        $lat = isset($base['lat']) && $base['lat'] !== '' ? (float)$base['lat'] : null;
        $lng = isset($base['lng']) && $base['lng'] !== '' ? (float)$base['lng'] : null;
        if ($lat === null || $lng === null) return [];

        $posts = get_posts([
            'post_type' => 'truckstop',
            'post_status' => 'publish',
            'posts_per_page' => 50,
            'post__not_in' => [(int)$post_id],
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        $items = [];
        foreach ($posts as $p) {
            $payload = self::listing_payload($p);
            if ($payload['lat'] === null || $payload['lng'] === null || $payload['lat'] === '' || $payload['lng'] === '') continue;
            $distance = self::haversine_miles($lat, $lng, (float)$payload['lat'], (float)$payload['lng']);
            if ($distance > 40) continue;
            $payload['distance_miles'] = round($distance, 1);
            $items[] = $payload;
        }

        usort($items, function($a, $b) {
            return ($a['distance_miles'] ?? 99999) <=> ($b['distance_miles'] ?? 99999);
        });

        return array_slice($items, 0, max(1, (int)$limit));
    }

    public static function approved_photos($post_id) {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT attachment_id, caption FROM " . TSF_DB::table('tsf_photos') . " WHERE post_id = %d AND status = 'approved' ORDER BY created_at DESC",
            $post_id
        ), ARRAY_A);
        $items = [];
        foreach ($rows as $r) {
            $url = wp_get_attachment_image_url((int)$r['attachment_id'], 'large');
            $thumb = wp_get_attachment_image_url((int)$r['attachment_id'], 'medium');
            if ($url) $items[] = ['url' => $url, 'thumb' => $thumb ?: $url, 'caption' => $r['caption']];
        }
        return $items;
    }

    public static function queue_notification($driver_user_id = null, $email_to = '', $subject = '', $message = '') {
        global $wpdb;
        $wpdb->insert(TSF_DB::table('tsf_notifications'), [
            'driver_user_id' => $driver_user_id ? (int)$driver_user_id : null,
            'email_to' => sanitize_email($email_to),
            'subject' => sanitize_text_field($subject),
            'message' => sanitize_textarea_field($message),
        ]);
    }

    public static function send_queued_notifications() {
        global $wpdb;
        $rows = $wpdb->get_results("SELECT * FROM " . TSF_DB::table('tsf_notifications') . " WHERE sent_at IS NULL ORDER BY created_at ASC LIMIT 25", ARRAY_A);
        foreach ($rows as $row) {
            $email = $row['email_to'];
            if (!$email && !empty($row['driver_user_id'])) {
                $email = $wpdb->get_var($wpdb->prepare("SELECT email FROM " . TSF_DB::table('tsf_driver_users') . " WHERE id = %d", (int)$row['driver_user_id']));
            }
            if ($email) {
                wp_mail($email, $row['subject'], $row['message']);
            }
            $wpdb->update(TSF_DB::table('tsf_notifications'), ['sent_at' => current_time('mysql')], ['id' => (int)$row['id']]);
        }
    }

    public static function can_manage_truckstop() {
        return current_user_can('manage_options');
    }

    public static function log_moderation($item_type, $item_id, $action, $note = '') {
        global $wpdb;
        $wpdb->insert(TSF_DB::table('tsf_moderation_log'), [
            'item_type' => sanitize_text_field($item_type),
            'item_id' => (int)$item_id,
            'action' => sanitize_text_field($action),
            'note' => sanitize_textarea_field($note),
        ]);
    }

    public static function operator_update_history($post_id) {
        return self::moderation_history('listing', $post_id);
    }

    public static function moderation_history($item_type, $item_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT action, note, created_at FROM " . TSF_DB::table('tsf_moderation_log') . " WHERE item_type = %s AND item_id = %d ORDER BY created_at DESC LIMIT 20",
            $item_type,
            $item_id
        ), ARRAY_A);
    }

public static function approved_photo_count($post_id) {
    global $wpdb;
    return (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM " . TSF_DB::table('tsf_photos') . " WHERE post_id = %d AND status = 'approved'",
        $post_id
    ));
}

public static function listing_analytics($post_id) {
        global $wpdb;
        $review_count = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM " . TSF_DB::table('tsf_reviews') . " WHERE post_id = %d AND status = 'approved'", $post_id));
        $photo_count = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM " . TSF_DB::table('tsf_photos') . " WHERE post_id = %d AND status = 'approved'", $post_id));
        $saved_count = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM " . TSF_DB::table('tsf_favourites') . " WHERE post_id = %d", $post_id));
        return ['review_count' => $review_count, 'photo_count' => $photo_count, 'saved_count' => $saved_count];
    }

    public static function operator_user_by_email($email) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM " . TSF_DB::table('tsf_operator_users') . " WHERE email = %s", sanitize_email($email)), ARRAY_A);
    }

    public static function create_operator_user($email, $display_name = '') {
        global $wpdb;
        $email = sanitize_email($email);
        if (!$email) return 0;
        $existing = self::operator_user_by_email($email);
        if ($existing) return (int)$existing['id'];

        $password = wp_generate_password(16, true, true);
        $wpdb->insert(TSF_DB::table('tsf_operator_users'), [
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'display_name' => sanitize_text_field($display_name),
        ]);

        self::queue_notification(null, $email, 'Operator account created', 'An operator account has been created for you. Temporary password: ' . $password);
        return (int)$wpdb->insert_id;
    }

    public static function grant_operator_listing_access($operator_user_id, $post_id) {
        global $wpdb;
        if (!$operator_user_id || !$post_id) return;
        $wpdb->replace(TSF_DB::table('tsf_operator_listing_access'), [
            'operator_user_id' => (int)$operator_user_id,
            'post_id' => (int)$post_id,
        ]);
    }

public static function sanitize_operator_listing_update($data) {
    $title = sanitize_text_field($data['title'] ?? '');
    if (strlen($title) > 160) {
        $title = substr($title, 0, 160);
    }

    $opening_hours = sanitize_text_field($data['opening_hours'] ?? '');
    if (strlen($opening_hours) > 190) {
        $opening_hours = substr($opening_hours, 0, 190);
    }

    $parking_type = sanitize_text_field($data['parking_type'] ?? '');
    if (strlen($parking_type) > 80) {
        $parking_type = substr($parking_type, 0, 80);
    }

    $price_night = sanitize_text_field($data['price_night'] ?? '');
    if (strlen($price_night) > 20) {
        $price_night = substr($price_night, 0, 20);
    }

    return [
        'title' => $title,
        'description' => wp_kses_post($data['description'] ?? ''),
        'opening_hours' => $opening_hours,
        'parking_type' => $parking_type,
        'price_night' => $price_night,
    ];
}

public static function expire_featured_listings() {
    global $wpdb;
    $table = TSF_DB::table('tsf_listing_details');
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT post_id FROM $table WHERE is_featured_paid = %d AND featured_until IS NOT NULL AND featured_until < %s",
            1,
            current_time('mysql')
        ),
        ARRAY_A
    );

    if (empty($rows)) {
        return;
    }

    foreach ($rows as $row) {
        $post_id = (int)$row['post_id'];
        $details = self::get_details($post_id);
        if (!$details) {
            continue;
        }
        self::upsert_details($post_id, array_merge($details, [
            'featured' => 0,
            'is_featured_paid' => 0,
            'feature_style' => 'standard',
        ]));
        self::log_moderation('listing', $post_id, 'featured_expired', 'Featured upgrade expired automatically');
    }
}

    public static function operator_listing_ids($operator_user_id) {
        global $wpdb;
        return $wpdb->get_col($wpdb->prepare(
            "SELECT post_id FROM " . TSF_DB::table('tsf_operator_listing_access') . " WHERE operator_user_id = %d ORDER BY created_at DESC",
            (int)$operator_user_id
        ));
    }

    public static function operator_dashboard_payload($operator_user_id) {
        $ids = self::operator_listing_ids($operator_user_id);
        $items = [];
        foreach ($ids as $id) {
            $post = get_post((int)$id);
            if ($post && $post->post_type === 'truckstop' && $post->post_status === 'publish') {
                $payload = self::listing_payload($post);
                $payload['analytics'] = self::listing_analytics($post->ID);
                $items[] = $payload;
            }
        }
        return $items;
    }

    public static function operator_billing_history($operator_email) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . TSF_DB::table('tsf_feature_orders') . " WHERE operator_email = %s ORDER BY created_at DESC LIMIT 50",
            sanitize_email($operator_email)
        ), ARRAY_A);
    }

    public static function operator_claims() {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM " . TSF_DB::table('tsf_operator_claims') . " ORDER BY created_at DESC LIMIT 200", ARRAY_A);
    }

    public static function feature_orders() {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM " . TSF_DB::table('tsf_feature_orders') . " ORDER BY created_at DESC LIMIT 200", ARRAY_A);
    }

    public static function my_submissions($driver_user_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, submission_type, target_post_id, status, created_at FROM " . TSF_DB::table('tsf_submissions') . " WHERE driver_user_id = %d ORDER BY created_at DESC LIMIT 50",
            $driver_user_id
        ), ARRAY_A);
    }

    public static function my_reviews($driver_user_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, post_id, rating, review_text, status, created_at FROM " . TSF_DB::table('tsf_reviews') . " WHERE driver_user_id = %d ORDER BY created_at DESC LIMIT 50",
            $driver_user_id
        ), ARRAY_A);
    }

    public static function my_favourites($driver_user_id) {
        global $wpdb;
        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT post_id FROM " . TSF_DB::table('tsf_favourites') . " WHERE driver_user_id = %d",
            $driver_user_id
        ));
        $items = [];
        foreach ($ids as $id) {
            $post = get_post((int)$id);
            if ($post && $post->post_type === 'truckstop' && $post->post_status === 'publish') {
                $items[] = self::listing_payload($post);
            }
        }
        return $items;
    }

    public static function saved_searches($driver_user_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, label, search_payload, created_at FROM " . TSF_DB::table('tsf_saved_searches') . " WHERE driver_user_id = %d ORDER BY created_at DESC LIMIT 50",
            (int)$driver_user_id
        ), ARRAY_A);
    }


public static function driver_reputation($user_id) {
    global $wpdb;
    $reviews = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM " . TSF_DB::table('tsf_reviews') . " WHERE driver_user_id = %d AND status='approved'",
        (int)$user_id
    ));
    $photos = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM " . TSF_DB::table('tsf_photos') . " WHERE driver_user_id = %d AND status='approved'",
        (int)$user_id
    ));
    $submissions = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM " . TSF_DB::table('tsf_submissions') . " WHERE driver_user_id = %d AND status='approved'",
        (int)$user_id
    ));

    $score = ($reviews * 2) + ($photos * 3) + ($submissions * 5);

    $label = 'New';
    if ($score >= 50) {
        $label = 'Trusted Driver';
    } elseif ($score >= 20) {
        $label = 'Active Contributor';
    }

    return [
        'score' => $score,
        'label' => $label,
        'reviews' => $reviews,
        'photos' => $photos,
        'submissions' => $submissions,
    ];
}

public static function top_contributors($limit = 10) {
    global $wpdb;
    $users = $wpdb->get_results("SELECT id, display_name, email FROM " . TSF_DB::table('tsf_driver_users') . " ORDER BY created_at ASC LIMIT 500", ARRAY_A);
    $rows = [];

    foreach ($users as $user) {
        $rep = self::driver_reputation((int)$user['id']);
        if ((int)$rep['score'] <= 0) {
            continue;
        }

        $name = !empty($user['display_name']) ? $user['display_name'] : $user['email'];
        $rows[] = [
            'id' => (int)$user['id'],
            'name' => $name,
            'score' => (int)$rep['score'],
            'label' => $rep['label'],
            'reviews' => (int)$rep['reviews'],
            'photos' => (int)$rep['photos'],
            'submissions' => (int)$rep['submissions'],
        ];
    }

    usort($rows, function($a, $b) {
        if ($b['score'] !== $a['score']) return $b['score'] <=> $a['score'];
        if ($b['submissions'] !== $a['submissions']) return $b['submissions'] <=> $a['submissions'];
        return $b['reviews'] <=> $a['reviews'];
    });

    return array_slice($rows, 0, max(1, (int)$limit));
}


    public static function community_stats() {
        global $wpdb;

        $listing_count = (int)wp_count_posts('truckstop')->publish;
        $review_count = (int)$wpdb->get_var("SELECT COUNT(*) FROM " . TSF_DB::table('tsf_reviews') . " WHERE status='approved'");
        $photo_count = (int)$wpdb->get_var("SELECT COUNT(*) FROM " . TSF_DB::table('tsf_photos') . " WHERE status='approved'");
        $contributor_count = (int)$wpdb->get_var(
            "SELECT COUNT(DISTINCT driver_user_id) FROM " . TSF_DB::table('tsf_reviews') . " WHERE status='approved' AND driver_user_id IS NOT NULL"
        );

        return [
            'listings' => $listing_count,
            'reviews' => $review_count,
            'photos' => $photo_count,
            'contributors' => $contributor_count,
        ];
    }

    public static function contributor_progress($user_id) {
        $rep = self::driver_reputation((int)$user_id);
        $next_target = 20;
        $next_label = 'Active Contributor';

        if ($rep['score'] >= 20 && $rep['score'] < 50) {
            $next_target = 50;
            $next_label = 'Trusted Driver';
        } elseif ($rep['score'] >= 50) {
            $next_target = $rep['score'];
            $next_label = 'Top contributor';
        }

        return [
            'current_score' => (int)$rep['score'],
            'current_label' => $rep['label'],
            'next_target' => (int)$next_target,
            'next_label' => $next_label,
            'remaining' => max(0, (int)$next_target - (int)$rep['score']),
        ];
    }

public static function recent_community_activity($limit = 12) {
    global $wpdb;
    $limit = max(1, min(50, (int)$limit));
    $items = [];

    $reviews = $wpdb->get_results(
        "SELECT id, post_id, created_at, rating FROM " . TSF_DB::table('tsf_reviews') . " WHERE status='approved' ORDER BY created_at DESC LIMIT " . $limit,
        ARRAY_A
    );
    foreach ($reviews as $row) {
        $post = get_post((int)$row['post_id']);
        if (!$post) continue;
        $items[] = [
            'type' => 'review',
            'created_at' => $row['created_at'],
            'title' => $post->post_title,
            'meta' => 'Rated ' . (int)$row['rating'] . '/5',
            'url' => get_permalink((int)$row['post_id']),
        ];
    }

    $photos = $wpdb->get_results(
        "SELECT id, post_id, created_at, caption FROM " . TSF_DB::table('tsf_photos') . " WHERE status='approved' ORDER BY created_at DESC LIMIT " . $limit,
        ARRAY_A
    );
    foreach ($photos as $row) {
        $post = get_post((int)$row['post_id']);
        if (!$post) continue;
        $items[] = [
            'type' => 'photo',
            'created_at' => $row['created_at'],
            'title' => $post->post_title,
            'meta' => !empty($row['caption']) ? $row['caption'] : 'Photo approved',
            'url' => get_permalink((int)$row['post_id']),
        ];
    }

    $subs = $wpdb->get_results(
        "SELECT id, target_post_id, submission_type, created_at FROM " . TSF_DB::table('tsf_submissions') . " WHERE status='approved' ORDER BY created_at DESC LIMIT " . $limit,
        ARRAY_A
    );
    foreach ($subs as $row) {
        $post_id = !empty($row['target_post_id']) ? (int)$row['target_post_id'] : 0;
        $post = $post_id ? get_post($post_id) : null;
        $items[] = [
            'type' => 'submission',
            'created_at' => $row['created_at'],
            'title' => $post ? $post->post_title : 'Approved contribution',
            'meta' => ucfirst((string)$row['submission_type']) . ' approved',
            'url' => $post ? get_permalink($post_id) : '',
        ];
    }

    usort($items, function($a, $b) {
        return strcmp((string)$b['created_at'], (string)$a['created_at']);
    });

    return array_slice($items, 0, $limit);
}


public static function contributor_badge_class($label) {
    $label = strtolower((string)$label);
    if (strpos($label, 'trusted') !== false) return 'tsf-badge-trusted';
    if (strpos($label, 'active') !== false) return 'tsf-badge-active';
    return 'tsf-badge-new';
}

public static function delete_saved_search($driver_user_id, $saved_search_id) {
    global $wpdb;
    return (bool)$wpdb->delete(
        TSF_DB::table('tsf_saved_searches'),
        [
            'id' => (int)$saved_search_id,
            'driver_user_id' => (int)$driver_user_id,
        ],
        ['%d', '%d']
    );
}


    public static function ratings_summary($post_id) {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT COUNT(*) AS rating_count, AVG(rating) AS avg_rating FROM " . TSF_DB::table('tsf_ratings') . " WHERE post_id = %d",
            (int)$post_id
        ), ARRAY_A);

        return [
            'rating_count' => isset($row['rating_count']) ? (int)$row['rating_count'] : 0,
            'rating' => isset($row['avg_rating']) && $row['avg_rating'] !== null ? round((float)$row['avg_rating'], 1) : 0,
        ];
    }

    public static function user_rating_for_post($post_id, $wp_user_id) {
        global $wpdb;
        $rating = $wpdb->get_var($wpdb->prepare(
            "SELECT rating FROM " . TSF_DB::table('tsf_ratings') . " WHERE post_id = %d AND wp_user_id = %d",
            (int)$post_id,
            (int)$wp_user_id
        ));
        return $rating !== null ? (int)$rating : 0;
    }

    public static function save_rating($post_id, $wp_user_id, $rating) {
        global $wpdb;
        $post_id = (int)$post_id;
        $wp_user_id = (int)$wp_user_id;
        $rating = max(1, min(5, (int)$rating));

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM " . TSF_DB::table('tsf_ratings') . " WHERE post_id = %d AND wp_user_id = %d",
            $post_id,
            $wp_user_id
        ));

        if ($existing) {
            $wpdb->update(
                TSF_DB::table('tsf_ratings'),
                ['rating' => $rating],
                ['id' => (int)$existing],
                ['%d'],
                ['%d']
            );
        } else {
            $wpdb->insert(
                TSF_DB::table('tsf_ratings'),
                ['post_id' => $post_id, 'wp_user_id' => $wp_user_id, 'rating' => $rating],
                ['%d', '%d', '%d']
            );
        }

        return self::ratings_summary($post_id);
    }

    
public static function approved_reviews($post_id, $limit = 20) {
        if (!class_exists('TSF_DB')) return [];
        global $wpdb;
        $table = TSF_DB::table('tsf_reviews');
        $columns = $wpdb->get_col("SHOW COLUMNS FROM {$table}", 0);
        $columns = is_array($columns) ? $columns : [];

        $user_col = in_array('wp_user_id', $columns, true) ? 'wp_user_id' : (in_array('driver_user_id', $columns, true) ? 'driver_user_id' : '');
        $status_filter = in_array('status', $columns, true) ? " AND status IN ('published','approved')" : "";
        $review_text_col = in_array('review_text', $columns, true) ? 'review_text' : (in_array('comment', $columns, true) ? 'comment' : "''");
        $created_col = in_array('updated_at', $columns, true) ? 'updated_at' : (in_array('created_at', $columns, true) ? 'created_at' : "NOW()");
        $select_user = $user_col ? ", {$user_col} AS review_user_id" : ", 0 AS review_user_id";

        $sql = "SELECT rating, {$review_text_col} AS review_text{$select_user}, {$created_col} AS created_at FROM {$table} WHERE post_id = %d{$status_filter} ORDER BY {$created_col} DESC LIMIT %d";
        $rows = $wpdb->get_results($wpdb->prepare($sql, (int)$post_id, max(1, (int)$limit)), ARRAY_A);
        $rows = is_array($rows) ? $rows : [];

        foreach ($rows as &$row) {
            $uid = isset($row['review_user_id']) ? (int)$row['review_user_id'] : 0;
            $user = $uid ? get_user_by('id', $uid) : false;
            $name = $user ? trim((string) $user->display_name) : '';
            if ($name === '') $name = 'Driver';
            $row['author_name'] = $name;
        }
        return $rows;
    }

    public static function user_favourites($wp_user_id) {
        $ids = get_user_meta((int)$wp_user_id, 'tsf_favourites', true);
        return is_array($ids) ? array_values(array_map('intval', $ids)) : [];
    }

    public static function toggle_favourite($wp_user_id, $post_id) {
        $ids = self::user_favourites($wp_user_id);
        $post_id = (int)$post_id;
        $saved = true;
        if (in_array($post_id, $ids, true)) {
            $ids = array_values(array_diff($ids, [$post_id]));
            $saved = false;
        } else {
            $ids[] = $post_id;
            $ids = array_values(array_unique(array_map('intval', $ids)));
        }
        update_user_meta((int)$wp_user_id, 'tsf_favourites', $ids);
        return $saved;
    }

    public static function favourite_payloads($wp_user_id) {
        $ids = self::user_favourites($wp_user_id);
        if (!$ids) return [];
        $posts = get_posts([
            'post_type' => 'truckstop',
            'post_status' => 'publish',
            'post__in' => $ids,
            'posts_per_page' => count($ids),
            'orderby' => 'post__in',
        ]);
        return array_values(array_map([__CLASS__, 'listing_payload'], $posts));
    }

    public static function saved_searches_for_user($wp_user_id) {
        $rows = get_user_meta((int)$wp_user_id, 'tsf_saved_searches', true);
        return is_array($rows) ? $rows : [];
    }

    public static function save_search_for_user($wp_user_id, $label, $payload) {
        $rows = self::saved_searches_for_user($wp_user_id);
        $rows[] = [
            'id' => wp_generate_password(12, false, false),
            'label' => sanitize_text_field($label),
            'search_payload' => is_array($payload) ? $payload : [],
            'created_at' => current_time('mysql'),
        ];
        update_user_meta((int)$wp_user_id, 'tsf_saved_searches', $rows);
        return $rows;
    }

    public static function delete_saved_search_for_user($wp_user_id, $id) {
        $rows = self::saved_searches_for_user($wp_user_id);
        $rows = array_values(array_filter($rows, function($row) use ($id) {
            return !isset($row['id']) || $row['id'] !== $id;
        }));
        update_user_meta((int)$wp_user_id, 'tsf_saved_searches', $rows);
        return $rows;
    }

    public static function my_submissions_for_user($wp_user_id) {
        if (!class_exists('TSF_DB')) return [];
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . TSF_DB::table('tsf_submissions') . " WHERE user_id = %d ORDER BY created_at DESC LIMIT 50",
            (int)$wp_user_id
        ), ARRAY_A);
        $rows = is_array($rows) ? $rows : [];

        foreach ($rows as &$row) {
            $payload = [];
            if (!empty($row['payload_json'])) {
                $decoded = json_decode((string)$row['payload_json'], true);
                if (is_array($decoded)) $payload = $decoded;
            }

            $fallback_name = !empty($payload['name']) ? sanitize_text_field($payload['name']) : '';
            $row_title = !empty($row['title']) ? sanitize_text_field($row['title']) : '';
            if (strtolower($row_title) === 'listing') $row_title = '';
            $row['name'] = $row_title ?: $fallback_name;
            $row['town'] = !empty($payload['town']) ? sanitize_text_field($payload['town']) : '';
            $row['postcode'] = !empty($payload['postcode']) ? sanitize_text_field($payload['postcode']) : '';
            $row['post_id'] = 0;
            $row['post_url'] = '';

            if (($row['status'] ?? '') === 'approved') {
                $existing = [];
                if (!empty($row['name'])) {
                    $existing = get_posts([
                        'post_type' => 'truckstop',
                        'post_status' => 'publish',
                        'title' => $row['name'],
                        'posts_per_page' => 1,
                        'orderby' => 'date',
                        'order' => 'DESC',
                    ]);
                }
                if (empty($existing) && (!empty($row['postcode']) || !empty($row['town']))) {
                    $search = trim(($row['name'] ?: '') . ' ' . ($row['town'] ?: '') . ' ' . ($row['postcode'] ?: ''));
                    $existing = get_posts([
                        'post_type' => 'truckstop',
                        'post_status' => 'publish',
                        's' => $search,
                        'posts_per_page' => 1,
                        'orderby' => 'date',
                        'order' => 'DESC',
                    ]);
                }
                if (!empty($existing) && !empty($existing[0]->ID)) {
                    $row['post_id'] = (int)$existing[0]->ID;
                    $row['post_url'] = get_permalink($existing[0]->ID) ?: '';
                    if (empty($row['name'])) $row['name'] = get_the_title($existing[0]->ID);
                }
            }
        }

        return $rows;
    }
}
