<?php
if (!defined('ABSPATH')) exit;

class TSF_REST {
    public static function register_routes() {
        $routes = [
            ['/search', 'GET', 'search'],
            ['/listing/(?P<id>\d+)', 'GET', 'listing'],
            ['/register', 'POST', 'register_driver'],
            ['/login', 'POST', 'login_driver'],
            ['/submit-listing', 'POST', 'submit_listing'],
            ['/suggest-edit', 'POST', 'suggest_edit'],
            ['/review', 'POST', 'submit_review'],
            ['/favourite', 'POST', 'toggle_favourite'],
            ['/me', 'GET', 'me'],
            ['/my-favourites', 'GET', 'my_favourites'],
            ['/my-dashboard', 'GET', 'my_dashboard'],
            ['/save-search', 'POST', 'save_search'],
            ['/my-saved-searches', 'GET', 'my_saved_searches'],
            ['/delete-saved-search', 'POST', 'delete_saved_search'],
            ['/leaderboard', 'GET', 'leaderboard'],
            ['/rate', 'POST', 'rate_listing'],
            ['/submit-review', 'POST', 'submit_review'],
            ['/favourite', 'POST', 'toggle_favourite'],
            ['/save-search', 'POST', 'save_search'],
            ['/delete-saved-search', 'POST', 'delete_saved_search'],
            ['/my-dashboard', 'GET', 'my_dashboard'],
            ['/rate', 'POST', 'rate_listing'],
            ['/community-stats', 'GET', 'community_stats'],
            ['/community-feed', 'GET', 'community_feed'],
            ['/my-saved-summary', 'GET', 'my_saved_summary'],
            ['/upload-photo', 'POST', 'upload_photo'],
            ['/operator-claim', 'POST', 'operator_claim'],
            ['/operator-public-meta/(?P<id>\\d+)', 'GET', 'operator_public_meta'],
            ['/operator-login', 'POST', 'operator_login'],
            ['/operator-dashboard', 'GET', 'operator_dashboard'],
            ['/operator-upgrade', 'POST', 'operator_upgrade'],
            ['/operator-create-checkout', 'POST', 'operator_create_checkout'],
            ['/operator-update-listing', 'POST', 'operator_update_listing']
        ];

        foreach ($routes as $r) {
            register_rest_route('tsf/v1', $r[0], [
                'methods' => $r[1],
                'callback' => [__CLASS__, $r[2]],
                'permission_callback' => '__return_true'
            ]);
        }
    }

    public static function search($request) {
        $q = sanitize_text_field($request->get_param('q'));
        $postcode = sanitize_text_field($request->get_param('postcode'));
        $radius = min(200, max(0, (float)$request->get_param('radius')));
        $centerLat = $request->get_param('lat');
        $centerLng = $request->get_param('lng');
        $is_area_search = ($centerLat !== null && $centerLat !== '' && $centerLng !== null && $centerLng !== '');
        $filter_showers = $request->get_param('showers');
        $filter_secure = $request->get_param('secure');
        $filter_overnight = $request->get_param('overnight');
        $filter_fuel = $request->get_param('fuel');
        $filter_food = $request->get_param('food');
        $filter_featured = $request->get_param('featured');

        $tsf_norm = function($v) {
            $v = trim((string)$v);
            $v = function_exists('mb_strtolower') ? mb_strtolower($v) : strtolower($v);
            $v = preg_replace('/\s+/', ' ', $v);
            return trim($v);
        };

        $tsf_norm_postcode = function($v) {
            $v = strtoupper(trim((string)$v));
            $v = preg_replace('/\s+/', '', $v);
            return $v;
        };

        $tsf_search_matches_query = function($item, $q) use ($tsf_norm, $tsf_norm_postcode) {
            $q = trim((string)$q);
            if ($q === '') return true;

            $q_norm = $tsf_norm($q);
            $q_pc = $tsf_norm_postcode($q);

            $fields = [
                $item['title'] ?? '',
                $item['town_city'] ?? '',
                $item['town'] ?? '',
                $item['city'] ?? '',
                $item['postcode'] ?? '',
                $item['address'] ?? '',
                $item['address_line_1'] ?? '',
                $item['county'] ?? '',
                $item['location'] ?? '',
            ];

            foreach ($fields as $field) {
                $field = trim((string)$field);
                if ($field === '') continue;

                $hay = $tsf_norm($field);
                if ($q_norm !== '' && strpos($hay, $q_norm) !== false) return true;

                $hay_pc = $tsf_norm_postcode($field);
                if ($q_pc !== '' && $hay_pc !== '' && strpos($hay_pc, $q_pc) !== false) return true;
            }

            return false;
        };


        
        $posts = get_posts([
            'post_type' => 'truckstop',
            'post_status' => 'publish',
            'posts_per_page' => 200
        ]);

        $items = [];
        foreach ($posts as $p) {
            $item = TSF_Helpers::listing_payload($p);

            if (!$is_area_search && $q && !$tsf_search_matches_query($item, $q)) continue;

            if ($postcode && stripos((string)$item['postcode'], $postcode) === false) continue;
            if ($filter_showers !== null && $filter_showers !== '' && (int)$item['showers'] !== (int)$filter_showers) continue;
            if ($filter_secure !== null && $filter_secure !== '' && (int)$item['secure_parking'] !== (int)$filter_secure) continue;
            if ($filter_overnight !== null && $filter_overnight !== '' && (int)$item['overnight_parking'] !== (int)$filter_overnight) continue;
            if ($filter_fuel !== null && $filter_fuel !== '' && (int)$item['fuel'] !== (int)$filter_fuel) continue;
            if ($filter_food !== null && $filter_food !== '' && (int)$item['food'] !== (int)$filter_food) continue;
            if ($filter_featured !== null && $filter_featured !== '' && (int)$item['featured'] !== (int)$filter_featured) continue;

            if ($radius > 0 && $centerLat !== null && $centerLng !== null && $item['lat'] && $item['lng']) {
                $distance = TSF_Helpers::haversine_miles($centerLat, $centerLng, $item['lat'], $item['lng']);
                if ($distance > $radius) continue;
                $item['distance_miles'] = round($distance, 1);
            }

            $items[] = $item;
        }

        usort($items, function($a, $b) {
            if ($a['featured'] === $b['featured']) {
                $ad = isset($a['distance_miles']) ? $a['distance_miles'] : 99999;
                $bd = isset($b['distance_miles']) ? $b['distance_miles'] : 99999;
                if ($ad === $bd) return 0;
                return $ad < $bd ? -1 : 1;
            }
            return $a['featured'] > $b['featured'] ? -1 : 1;
        });

        return rest_ensure_response($items);
    }

    public static function listing($request) {
        global $wpdb;
        $post = get_post((int)$request['id']);
        if (!$post || $post->post_type !== 'truckstop') {
            return new WP_Error('not_found', 'Listing not found.', ['status' => 404]);
        }

        $reviews = TSF_Helpers::approved_reviews($post->ID);

        return rest_ensure_response([
            'listing' => TSF_Helpers::listing_payload($post),
            'content' => apply_filters('the_content', $post->post_content),
            'details' => TSF_Helpers::get_details($post->ID),
            'reviews' => $reviews,
            'photos' => TSF_Helpers::approved_photos($post->ID),
            'nearby' => TSF_Helpers::nearby_listings($post->ID),
            'user_rating' => ($u = TSF_Helpers::current_wp_user_from_request($request)) ? TSF_Helpers::user_rating_for_post($post->ID, $u->ID) : 0
        ]);
    }

    public static function register_driver($request) {
        $email = sanitize_email((string)$request->get_param('email'));
        $password = (string)$request->get_param('password');
        $display_name = sanitize_text_field((string)$request->get_param('display_name'));
        if (!$email || !$password) return new WP_Error('invalid', 'Email and password are required.', ['status' => 400]);
        if (email_exists($email)) return new WP_Error('exists', 'An account with that email already exists.', ['status' => 409]);

        $username_base = sanitize_user(current(explode('@', $email)), true);
        $username = $username_base ?: 'driver';
        $i = 1;
        while (username_exists($username)) { $username = $username_base . $i; $i++; }

        $user_id = wp_create_user($username, $password, $email);
        if (is_wp_error($user_id)) return $user_id;
        if ($display_name) wp_update_user(['ID' => $user_id, 'display_name' => $display_name, 'nickname' => $display_name]);
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);

        $token = wp_generate_password(32, false, false);
        update_user_meta($user_id, 'tsf_token', $token);
        $user = get_user_by('id', $user_id);
        return rest_ensure_response(['token' => $token, 'user' => ['id' => (int)$user_id, 'display_name' => $user->display_name, 'email' => $user->user_email]]);
    }

    public static function login_driver($request) {
        $email = sanitize_email((string)$request->get_param('email'));
        $password = (string)$request->get_param('password');
        if (!$email || !$password) return new WP_Error('invalid', 'Email and password are required.', ['status' => 400]);
        $user = get_user_by('email', $email);
        if (!$user || !wp_check_password($password, $user->user_pass, $user->ID)) {
            return new WP_Error('invalid_login', 'Invalid email or password.', ['status' => 401]);
        }
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, true);
        $token = get_user_meta($user->ID, 'tsf_token', true);
        if (!$token) {
            $token = wp_generate_password(32, false, false);
            update_user_meta($user->ID, 'tsf_token', $token);
        }
        return rest_ensure_response(['token' => $token, 'user' => ['id' => (int)$user->ID, 'display_name' => $user->display_name, 'email' => $user->user_email]]);
    }

    public static function submit_listing($request) {
        global $wpdb;
        $user = TSF_Helpers::auth_user_from_request($request);
        if (!$user) return new WP_Error('forbidden', 'Login required.', ['status' => 401]);

        $payload = [
            'title' => sanitize_text_field($request->get_param('title')),
            'description' => wp_kses_post($request->get_param('description')),
            'address_line_1' => sanitize_text_field($request->get_param('address_line_1')),
            'town_city' => sanitize_text_field($request->get_param('town_city')),
            'postcode' => sanitize_text_field($request->get_param('postcode')),
            'latitude' => sanitize_text_field($request->get_param('lat')),
            'longitude' => sanitize_text_field($request->get_param('lng')),
            'parking_type' => sanitize_text_field($request->get_param('parking_type')),
            'opening_hours' => sanitize_text_field($request->get_param('opening_hours')),
            'showers' => (int)$request->get_param('showers'),
            'secure_parking' => (int)$request->get_param('secure_parking'),
            'overnight_parking' => (int)$request->get_param('overnight_parking'),
            'fuel' => (int)$request->get_param('fuel'),
            'food' => (int)$request->get_param('food'),
            'toilets' => (int)$request->get_param('toilets'),
            'price_night' => sanitize_text_field($request->get_param('price_night'))
        ];

        if (!$payload['title']) return new WP_Error('invalid', 'Title required.', ['status' => 400]);

        $duplicate_id = TSF_Helpers::find_duplicate_listing(
            $payload['title'],
            $payload['postcode'],
            ($payload['latitude'] !== '') ? (float)$payload['latitude'] : null,
            ($payload['longitude'] !== '') ? (float)$payload['longitude'] : null
        );

        if ($duplicate_id) {
            $duplicate_post = get_post($duplicate_id);
            return new WP_Error('duplicate', 'This truck stop already exists.', [
                'status' => 409,
                'duplicate_post_id' => $duplicate_id,
                'duplicate_title' => $duplicate_post ? $duplicate_post->post_title : '',
                'duplicate_url' => $duplicate_post ? get_permalink($duplicate_id) : '',
            ]);
        }

        $wpdb->insert(TSF_DB::table('tsf_submissions'), [
            'driver_user_id' => (int)$user['id'],
            'submission_type' => 'listing',
            'payload' => wp_json_encode($payload),
            'status' => 'pending'
        ]);

        return rest_ensure_response(['ok' => true]);
    }

    public static function suggest_edit($request) {
        global $wpdb;
        $user = TSF_Helpers::auth_user_from_request($request);
        if (!$user) return new WP_Error('forbidden', 'Login required.', ['status' => 401]);

        $post_id = (int)$request->get_param('post_id');
        if (!$post_id) return new WP_Error('invalid', 'post_id required.', ['status' => 400]);

        $payload = [
            'field' => sanitize_text_field($request->get_param('field')),
            'value' => sanitize_text_field($request->get_param('value')),
            'notes' => sanitize_textarea_field($request->get_param('notes'))
        ];

        $wpdb->insert(TSF_DB::table('tsf_submissions'), [
            'driver_user_id' => (int)$user['id'],
            'submission_type' => 'edit',
            'target_post_id' => $post_id,
            'payload' => wp_json_encode($payload),
            'status' => 'pending'
        ]);

        return rest_ensure_response(['ok' => true]);
    }

    
public static function submit_review($request) {
        global $wpdb;
        $wp_user = TSF_Helpers::current_wp_user_from_request($request);
        if (!$wp_user) return new WP_Error('forbidden', 'Login required.', ['status' => 401]);

        $post_id = (int)$request->get_param('post_id');
        $rating = max(1, min(5, (int)$request->get_param('rating')));
        $review_text = sanitize_textarea_field((string)$request->get_param('review_text'));
        $review_tags = [];
        $review_tags = is_array($review_tags) ? array_values(array_slice(array_filter(array_map('sanitize_text_field', $review_tags)), 0, 5)) : [];

        if ($post_id <= 0 || !$review_text) {
            return new WP_Error('invalid', 'post_id and review_text are required.', ['status' => 400]);
        }

        $table = TSF_DB::table('tsf_reviews');
        $columns = $wpdb->get_col("SHOW COLUMNS FROM {$table}", 0);
        $columns = is_array($columns) ? $columns : [];

        $user_col = in_array('wp_user_id', $columns, true) ? 'wp_user_id' : (in_array('driver_user_id', $columns, true) ? 'driver_user_id' : '');
        if (!$user_col) return new WP_Error('config', 'Review table user column missing.', ['status' => 500]);

        $has_tags = in_array('review_tags', $columns, true);
        $has_status = in_array('status', $columns, true);
        $has_updated = in_array('updated_at', $columns, true);

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE post_id = %d AND {$user_col} = %d",
            $post_id,
            (int)$wp_user->ID
        ));

        $data = [
            'post_id' => $post_id,
            $user_col => (int)$wp_user->ID,
            'rating' => $rating,
            'review_text' => $review_text,
        ];
        $formats = ['%d','%d','%d','%s'];

        if ($has_tags) {
            $data['review_tags'] = wp_json_encode($review_tags);
            $formats[] = '%s';
        }
        if ($has_status) {
            $data['status'] = 'published';
            $formats[] = '%s';
        }
        if ($has_updated) {
            $data['updated_at'] = current_time('mysql');
            $formats[] = '%s';
        }

        if ($existing) {
            $wpdb->update($table, $data, ['id' => (int)$existing], $formats, ['%d']);
        } else {
            $wpdb->insert($table, $data, $formats);
        }

        if (method_exists('TSF_Helpers', 'save_rating')) {
            TSF_Helpers::save_rating($post_id, $wp_user->ID, $rating);
        }

        $reviews = TSF_Helpers::approved_reviews($post_id);
        $summary = method_exists('TSF_Helpers', 'ratings_summary') ? TSF_Helpers::ratings_summary($post_id) : ['rating' => $rating, 'rating_count' => count($reviews)];
        return rest_ensure_response([
            'ok' => true,
            'status' => 'published',
            'reviews' => $reviews,
            'rating' => $summary['rating'],
            'rating_count' => $summary['rating_count'],
        ]);
    }

    public static function toggle_favourite($request) {
        global $wpdb;
        $user = TSF_Helpers::auth_user_from_request($request);
        if (!$user) return new WP_Error('forbidden', 'Login required.', ['status' => 401]);

        $post_id = (int)$request->get_param('post_id');
        if (!$post_id) return new WP_Error('invalid', 'post_id required.', ['status' => 400]);

        $t = TSF_DB::table('tsf_favourites');
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $t WHERE post_id = %d AND driver_user_id = %d",
            $post_id,
            (int)$user['id']
        ));

        if ($exists) {
            $wpdb->delete($t, ['id' => (int)$exists]);
            return rest_ensure_response(['ok' => true, 'saved' => false]);
        }

        $wpdb->insert($t, [
            'post_id' => $post_id,
            'driver_user_id' => (int)$user['id']
        ]);

        return rest_ensure_response(['ok' => true, 'saved' => true]);
    }

    public static function me($request) {
        $wp_user = TSF_Helpers::current_wp_user_from_request($request);
        if (!$wp_user) return new WP_Error('forbidden', 'Login required.', ['status' => 401]);
        return rest_ensure_response([
            'id' => (int)$wp_user->ID,
            'display_name' => $wp_user->display_name,
            'email' => $wp_user->user_email,
        ]);
    }

    public static function my_favourites($request) {
        global $wpdb;
        $u = TSF_Helpers::auth_user_from_request($request);
        if (!$u) return new WP_Error('forbidden', 'Login required.', ['status' => 401]);

        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT post_id FROM " . TSF_DB::table('tsf_favourites') . " WHERE driver_user_id = %d",
            (int)$u['id']
        ));

        $items = [];
        foreach ($ids as $id) {
            $post = get_post((int)$id);
            if ($post && $post->post_type === 'truckstop' && $post->post_status === 'publish') {
                $items[] = TSF_Helpers::listing_payload($post);
            }
        }

        return rest_ensure_response($items);
    }

    public static function my_dashboard($request) {
        $u = TSF_Helpers::auth_user_from_request($request);
        if (!$u) return new WP_Error('forbidden', 'Login required.', ['status' => 401]);

        return rest_ensure_response([
            'submissions' => TSF_Helpers::my_submissions((int)$u['id']),
            'reviews' => TSF_Helpers::my_reviews((int)$u['id']),
            'favourites' => TSF_Helpers::my_favourites((int)$u['id']),
            'saved_searches' => TSF_Helpers::saved_searches((int)$u['id']),
            'reputation' => TSF_Helpers::driver_reputation((int)$u['id']),
            'progress' => TSF_Helpers::contributor_progress((int)$u['id']),
        ]);
    }

    public static function upload_photo($request) {
        global $wpdb;
        $user = TSF_Helpers::auth_user_from_request($request);
        if (!$user) return new WP_Error('forbidden', 'Login required.', ['status' => 401]);

        $post_id = (int)($request->get_param('post_id'));
        if (!$post_id) return new WP_Error('invalid', 'post_id required.', ['status' => 400]);
        if (empty($_FILES['photo']['name'])) return new WP_Error('invalid', 'Photo file required.', ['status' => 400]);

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $attachment_id = media_handle_upload('photo', $post_id);
        if (is_wp_error($attachment_id)) return $attachment_id;

        $caption = sanitize_text_field($request->get_param('caption'));
        if ($caption) {
            wp_update_post([
                'ID' => $attachment_id,
                'post_excerpt' => $caption
            ]);
        }

        $wpdb->insert(TSF_DB::table('tsf_photos'), [
            'post_id' => $post_id,
            'driver_user_id' => (int)$user['id'],
            'attachment_id' => (int)$attachment_id,
            'caption' => $caption,
            'status' => 'pending'
        ]);

        return rest_ensure_response(['ok' => true]);
    }

public static function my_saved_summary($request) {
    $u = TSF_Helpers::auth_user_from_request($request);
    if (!$u) return new WP_Error('forbidden', 'Login required.', ['status' => 401]);
    $favs = TSF_Helpers::my_favourites((int)$u['id']);
    return rest_ensure_response([
        'count' => count($favs),
        'items' => array_slice($favs, 0, 10),
    ]);
}


public static function operator_claim($request) {
    global $wpdb;
    $post_id = (int)$request->get_param('post_id');
    $operator_email = sanitize_email($request->get_param('operator_email'));
    $operator_name = sanitize_text_field($request->get_param('operator_name'));
    $phone = sanitize_text_field($request->get_param('phone'));
    $notes = sanitize_textarea_field($request->get_param('notes'));

    if (!$post_id || !$operator_email) {
        return new WP_Error('invalid', 'post_id and operator_email required.', ['status' => 400]);
    }

    $wpdb->insert(TSF_DB::table('tsf_operator_claims'), [
        'post_id' => $post_id,
        'operator_name' => $operator_name,
        'operator_email' => $operator_email,
        'phone' => $phone,
        'notes' => $notes,
        'status' => 'pending',
    ]);

    TSF_Helpers::queue_notification(null, get_option('admin_email'), 'New operator claim submitted', 'A new operator claim has been submitted for listing ID ' . $post_id . '.');
    return rest_ensure_response(['ok' => true]);
}



public static function operator_public_meta($request) {
    $post_id = (int)$request['id'];
    $details = TSF_Helpers::get_details($post_id);
    if (!$details) return new WP_Error('not_found', 'Listing not found.', ['status' => 404]);

    return rest_ensure_response([
        'owner_email' => $details['owner_email'] ?? '',
        'is_featured_paid' => (int)($details['is_featured_paid'] ?? 0),
        'feature_style' => $details['feature_style'] ?? 'standard',
        'featured_until' => $details['featured_until'] ?? '',
        'gateway_mode' => TSF_Payments::gateway_mode(),
        'plans' => TSF_Payments::plans(),
    ]);
}



public static function operator_login($request) {
    $result = TSF_Auth::operator_login(
        $request->get_param('email'),
        $request->get_param('password')
    );
    if (is_wp_error($result)) return $result;
    return rest_ensure_response($result);
}

public static function operator_dashboard($request) {
    $operator = TSF_Helpers::operator_auth_from_request($request);
    if (!$operator) return new WP_Error('forbidden', 'Operator login required.', ['status' => 401]);

    return rest_ensure_response([
        'operator' => [
            'id' => (int)$operator['id'],
            'email' => $operator['email'],
            'display_name' => $operator['display_name'],
        ],
        'listings' => TSF_Helpers::operator_dashboard_payload((int)$operator['id']),
        'gateway_mode' => TSF_Payments::gateway_mode(),
        'plans' => TSF_Payments::plans(),
    ]);
}

public static function operator_upgrade($request) {
    global $wpdb;
    $operator = TSF_Helpers::operator_auth_from_request($request);
    if (!$operator) return new WP_Error('forbidden', 'Operator login required.', ['status' => 401]);

    $post_id = (int)$request->get_param('post_id');
    $plan_key = sanitize_text_field($request->get_param('plan_key'));
    $feature_style = sanitize_text_field($request->get_param('feature_style') ?: 'standard');
    $allowed_ids = TSF_Helpers::operator_listing_ids((int)$operator['id']);
    if (!$post_id || !in_array($post_id, array_map('intval', $allowed_ids), true)) {
        return new WP_Error('forbidden', 'You do not manage this listing.', ['status' => 403]);
    }

    $plans = TSF_Payments::plans();
    if (empty($plans[$plan_key])) {
        return new WP_Error('invalid', 'Invalid plan.', ['status' => 400]);
    }

    $plan = $plans[$plan_key];
    $wpdb->insert(TSF_DB::table('tsf_feature_orders'), [
        'post_id' => $post_id,
        'operator_email' => $operator['email'],
        'plan_key' => $plan_key,
        'amount_gbp' => $plan['amount'],
        'status' => TSF_Payments::gateway_mode() === 'manual' ? 'pending' : 'checkout_ready',
        'payment_reference' => '',
        'featured_until' => null,
    ]);

    return rest_ensure_response([
        'ok' => true,
        'message' => TSF_Payments::gateway_mode() === 'manual'
            ? 'Upgrade request recorded. Admin will confirm payment and activate featured status.'
            : 'Stripe-ready mode enabled. Complete payment integration next to activate checkout.',
    ]);
}

public static function operator_update_listing($request) {
    $operator = TSF_Helpers::operator_auth_from_request($request);
    if (!$operator) return new WP_Error('forbidden', 'Operator login required.', ['status' => 401]);

    $post_id = (int)$request->get_param('post_id');
    $allowed_ids = TSF_Helpers::operator_listing_ids((int)$operator['id']);
    if (!$post_id || !in_array($post_id, array_map('intval', $allowed_ids), true)) {
        return new WP_Error('forbidden', 'You do not manage this listing.', ['status' => 403]);
    }

    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'truckstop') {
        return new WP_Error('not_found', 'Listing not found.', ['status' => 404]);
    }

    wp_update_post([
        'ID' => $post_id,
        'post_title' => sanitize_text_field($request->get_param('title') ?: $post->post_title),
        'post_content' => wp_kses_post($request->get_param('description') ?: $post->post_content),
    ]);

    $details = TSF_Helpers::get_details($post_id);
    if ($details) {
        TSF_Helpers::upsert_details($post_id, array_merge($details, [
            'opening_hours' => sanitize_text_field($request->get_param('opening_hours') ?: ($details['opening_hours'] ?? '')),
            'parking_type' => sanitize_text_field($request->get_param('parking_type') ?: ($details['parking_type'] ?? '')),
            'price_night' => sanitize_text_field($request->get_param('price_night') ?: ($details['price_night'] ?? '')),
            'owner_email' => $operator['email'],
        ]));
    }

    TSF_Helpers::log_moderation('listing', $post_id, 'operator_updated', 'Self-service operator update');
    return rest_ensure_response(['ok' => true]);
}



public static function operator_create_checkout($request) {
    global $wpdb;
    $operator = TSF_Helpers::operator_auth_from_request($request);
    if (!$operator) return new WP_Error('forbidden', 'Operator login required.', ['status' => 401]);

    $order_id = (int)$request->get_param('order_id');
    $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . TSF_DB::table('tsf_feature_orders') . " WHERE id = %d", $order_id), ARRAY_A);
    if (!$order || strtolower($order['operator_email']) !== strtolower($operator['email'])) {
        return new WP_Error('forbidden', 'You do not own this order.', ['status' => 403]);
    }

    return rest_ensure_response(TSF_Payments::create_checkout_session($order_id));
}



    public static function nearby_listings($request) {
        $post_id = (int)$request['id'];
        return rest_ensure_response(TSF_Helpers::nearby_listings($post_id));
    }


    public static function save_search($request) {
        global $wpdb;
        $u = TSF_Helpers::auth_user_from_request($request);
        if (!$u) return new WP_Error('forbidden', 'Login required.', ['status' => 401]);

        $label = sanitize_text_field($request->get_param('label'));
        $payload = $request->get_param('search_payload');

        if (!$label || !is_array($payload)) {
            return new WP_Error('invalid', 'label and search_payload are required.', ['status' => 400]);
        }

        $wpdb->insert(TSF_DB::table('tsf_saved_searches'), [
            'driver_user_id' => (int)$u['id'],
            'label' => $label,
            'search_payload' => wp_json_encode($payload),
        ]);

        return rest_ensure_response(['ok' => true]);
    }

    public static function my_saved_searches($request) {
        $u = TSF_Helpers::auth_user_from_request($request);
        if (!$u) return new WP_Error('forbidden', 'Login required.', ['status' => 401]);
        return rest_ensure_response(TSF_Helpers::saved_searches((int)$u['id']));
    }


    public static function leaderboard($request) {
        $limit = (int)$request->get_param('limit');
        if ($limit <= 0 || $limit > 25) $limit = 10;
        return rest_ensure_response(TSF_Helpers::top_contributors($limit));
    }


    public static function community_stats($request) {
        return rest_ensure_response(TSF_Helpers::community_stats());
    }


    public static function community_feed($request) {
        $limit = (int)$request->get_param('limit');
        if ($limit <= 0 || $limit > 30) $limit = 10;
        return rest_ensure_response(TSF_Helpers::recent_community_activity($limit));
    }


    public static function delete_saved_search($request) {
        $u = TSF_Helpers::auth_user_from_request($request);
        if (!$u) return new WP_Error('forbidden', 'Login required.', ['status' => 401]);

        $saved_search_id = (int)$request->get_param('id');
        if ($saved_search_id <= 0) {
            return new WP_Error('invalid', 'Saved search id is required.', ['status' => 400]);
        }

        $deleted = TSF_Helpers::delete_saved_search((int)$u['id'], $saved_search_id);
        return rest_ensure_response(['ok' => (bool)$deleted]);
    }


    public static function rate_listing($request) {
        $u = TSF_Helpers::current_wp_user_from_request($request);
        if (!$u) return new WP_Error('forbidden', 'Login required to rate.', ['status' => 401]);

        $post_id = (int)$request->get_param('post_id');
        $rating = (int)$request->get_param('rating');

        if ($post_id <= 0 || $rating < 1 || $rating > 5) {
            return new WP_Error('invalid', 'Valid post_id and rating are required.', ['status' => 400]);
        }

        $summary = TSF_Helpers::save_rating($post_id, $u->ID, $rating);
        return rest_ensure_response([
            'ok' => true,
            'rating' => $summary['rating'],
            'rating_count' => $summary['rating_count'],
            'user_rating' => $rating,
        ]);
    }
}
