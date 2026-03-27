<?php
if (!defined('ABSPATH')) exit;

class TSF_Admin {
    private static function counts() {
        global $wpdb;
        return [
            'pending_submissions' => (int)$wpdb->get_var("SELECT COUNT(*) FROM " . TSF_DB::table('tsf_submissions') . " WHERE status='pending'"),
            'pending_reviews' => (int)$wpdb->get_var("SELECT COUNT(*) FROM " . TSF_DB::table('tsf_reviews') . " WHERE status='pending'"),
            'pending_photos' => (int)$wpdb->get_var("SELECT COUNT(*) FROM " . TSF_DB::table('tsf_photos') . " WHERE status='pending'"),
            'live_listings' => (int)wp_count_posts('truckstop')->publish,
        ];
    }

    public static function register_menu() {
        add_menu_page('Truckstop Finder', 'Truckstop Finder', 'manage_options', 'tsf-admin', [__CLASS__, 'settings_page'], 'dashicons-location-alt', 26);
        add_submenu_page('tsf-admin', 'Moderation', 'Moderation', 'manage_options', 'tsf-moderation', [__CLASS__, 'moderation_page']);
        add_submenu_page('tsf-admin', 'Import', 'Import', 'manage_options', 'tsf-import', [__CLASS__, 'import_page']);
        add_submenu_page('tsf-admin', 'Analytics', 'Analytics', 'manage_options', 'tsf-analytics', [__CLASS__, 'analytics_page']);
        add_submenu_page('tsf-admin', 'Operators', 'Operators', 'manage_options', 'tsf-operators', [__CLASS__, 'operators_page']);
    }

    public static function register_settings() {
        register_setting('tsf_settings', 'tsf_google_maps_key');
        register_setting('tsf_settings', 'tsf_payment_gateway_mode');
        register_setting('tsf_settings', 'tsf_stripe_publishable_key');
        register_setting('tsf_settings', 'tsf_stripe_secret_key');
    }

    public static function settings_page() { ?>
        <div class="wrap">
            <h1>Truckstop Finder Settings</h1>
            <form method="post" action="options.php">
                <?php settings_fields('tsf_settings'); ?>
                <table class="form-table">
                    <tr><th>Google Maps API Key</th><td><input type="text" name="tsf_google_maps_key" value="<?php echo esc_attr(get_option('tsf_google_maps_key', '')); ?>" class="regular-text"></td></tr>
                    <tr><th>Payment gateway mode</th><td>
                        <select name="tsf_payment_gateway_mode">
                            <option value="manual" <?php selected(get_option('tsf_payment_gateway_mode', 'manual'), 'manual'); ?>>Manual admin confirmation</option>
                            <option value="stripe_ready" <?php selected(get_option('tsf_payment_gateway_mode', 'manual'), 'stripe_ready'); ?>>Stripe-ready configuration</option>
                        </select>
                    </td></tr>
                    <tr><th>Stripe publishable key</th><td><input type="text" name="tsf_stripe_publishable_key" value="<?php echo esc_attr(get_option('tsf_stripe_publishable_key', '')); ?>" class="regular-text"></td></tr>
                    <tr><th>Stripe secret key</th><td><input type="text" name="tsf_stripe_secret_key" value="<?php echo esc_attr(get_option('tsf_stripe_secret_key', '')); ?>" class="regular-text"></td></tr>
                </table>
                <?php submit_button(); ?>
            </form>
            <p>Use the shortcode <code>[truckstop_finder]</code> on any page.</p>
            <p>Webhook URL: <code><?php echo esc_html(TSF_Payments::webhook_url()); ?></code></p>
        </div>
    <?php }

    public static function moderation_page() {
        if (!TSF_Helpers::can_manage_truckstop()) wp_die('Permission denied.');
        global $wpdb;
        $subT = TSF_DB::table('tsf_submissions');
        $revT = TSF_DB::table('tsf_reviews');
        $photoT = TSF_DB::table('tsf_photos');

        if (!empty($_POST['tsf_save_submission']) && check_admin_referer('tsf_save_submission_' . (int)$_POST['submission_id'])) {
            self::save_submission_edits((int)$_POST['submission_id'], $_POST);
            TSF_Helpers::log_moderation('submission', (int)$_POST['submission_id'], 'edited', $_POST['moderation_note'] ?? '');
            echo '<div class="updated"><p>Submission updated.</p></div>';
        }

        if (isset($_GET['approve_submission'])) {
            self::approve_submission((int)$_GET['approve_submission']);
            echo '<div class="updated"><p>Submission approved.</p></div>';
        }

        if (isset($_GET['reject_submission'])) {
            $id = (int)$_GET['reject_submission'];
            $wpdb->update($subT, ['status' => 'rejected'], ['id' => $id]);
            TSF_Helpers::log_moderation('submission', $id, 'rejected');
            $driver_id = (int)$wpdb->get_var($wpdb->prepare("SELECT driver_user_id FROM $subT WHERE id = %d", $id));
            if ($driver_id) {
                TSF_Helpers::queue_notification($driver_id, '', 'Truckstop submission rejected', 'Your submission was reviewed and was not approved this time.');
            }
            echo '<div class="updated"><p>Submission rejected.</p></div>';
        }

        if (isset($_GET['approve_review'])) {
            $id = (int)$_GET['approve_review'];
            $wpdb->update($revT, ['status' => 'approved'], ['id' => $id]);
            TSF_Helpers::log_moderation('review', $id, 'approved');
            $driver_id = (int)$wpdb->get_var($wpdb->prepare("SELECT driver_user_id FROM $revT WHERE id = %d", $id));
            if ($driver_id) {
                TSF_Helpers::queue_notification($driver_id, '', 'Review approved', 'Your review has been approved and is now visible.');
            }
            echo '<div class="updated"><p>Review approved.</p></div>';
        }

        if (isset($_GET['reject_review'])) {
            $id = (int)$_GET['reject_review'];
            $wpdb->update($revT, ['status' => 'rejected'], ['id' => $id]);
            TSF_Helpers::log_moderation('review', $id, 'rejected');
            $driver_id = (int)$wpdb->get_var($wpdb->prepare("SELECT driver_user_id FROM $revT WHERE id = %d", $id));
            if ($driver_id) {
                TSF_Helpers::queue_notification($driver_id, '', 'Review rejected', 'Your review was reviewed and was not approved this time.');
            }
            echo '<div class="updated"><p>Review rejected.</p></div>';
        }

        if (isset($_GET['approve_photo'])) {
            $id = (int)$_GET['approve_photo'];
            $wpdb->update($photoT, ['status' => 'approved'], ['id' => $id]);
            TSF_Helpers::log_moderation('photo', $id, 'approved');
            echo '<div class="updated"><p>Photo approved.</p></div>';
        }

        if (isset($_GET['reject_photo'])) {
            $id = (int)$_GET['reject_photo'];
            $wpdb->update($photoT, ['status' => 'rejected'], ['id' => $id]);
            TSF_Helpers::log_moderation('photo', $id, 'rejected');
            echo '<div class="updated"><p>Photo rejected.</p></div>';
        }

        $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'submissions';
        $status_filter = isset($_GET['status_filter']) ? sanitize_key($_GET['status_filter']) : 'pending';
        $moderation_search = isset($_GET['moderation_search']) ? sanitize_text_field(wp_unslash($_GET['moderation_search'])) : '';
        $sub_where = $status_filter === 'all' ? '1=1' : "status='pending'";
        if ($moderation_search) {
            $search_like = '%' . $wpdb->esc_like($moderation_search) . '%';
            $sub_where .= $wpdb->prepare(" AND (payload LIKE %s OR submission_type LIKE %s)", $search_like, $search_like);
        }
        $review_where = $status_filter === 'all' ? '1=1' : "status='pending'";
        if ($moderation_search) {
            $search_like = '%' . $wpdb->esc_like($moderation_search) . '%';
            $review_where .= $wpdb->prepare(" AND (review_text LIKE %s)", $search_like);
        }
        $photo_where = $status_filter === 'all' ? '1=1' : "status='pending'";
        if ($moderation_search) {
            $search_like = '%' . $wpdb->esc_like($moderation_search) . '%';
            $photo_where .= $wpdb->prepare(" AND (caption LIKE %s)", $search_like);
        }
        $subs = $wpdb->get_results("SELECT * FROM $subT WHERE $sub_where ORDER BY created_at DESC LIMIT 100", ARRAY_A);
        $reviews = $wpdb->get_results("SELECT * FROM $revT WHERE $review_where ORDER BY created_at DESC LIMIT 100", ARRAY_A);
        $photos = $wpdb->get_results("SELECT * FROM $photoT WHERE $photo_where ORDER BY created_at DESC LIMIT 100", ARRAY_A);
        ?>
        <div class="wrap">
            <h1>Moderation</h1><p><strong>Strict moderation mode:</strong> all new stops, edits, reviews and photos require admin approval before going live.</p>
            <?php $counts = self::counts(); ?>
            <div style="display:grid;grid-template-columns:repeat(4,minmax(120px,1fr));gap:12px;margin:16px 0 16px;">
                <a href="<?php echo esc_url(admin_url('admin.php?page=tsf-moderation&tab=submissions')); ?>" style="text-decoration:none;background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px;color:#111;"><strong><?php echo esc_html($counts['pending_submissions']); ?></strong><br>Pending submissions</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=tsf-moderation&tab=reviews')); ?>" style="text-decoration:none;background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px;color:#111;"><strong><?php echo esc_html($counts['pending_reviews']); ?></strong><br>Pending reviews</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=tsf-moderation&tab=photos')); ?>" style="text-decoration:none;background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px;color:#111;"><strong><?php echo esc_html($counts['pending_photos']); ?></strong><br>Pending photos</a>
                <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px;"><strong><?php echo esc_html($counts['live_listings']); ?></strong><br>Live listings</div>
            </div>
            <div style="margin:0 0 16px;display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                <a class="button <?php echo $status_filter === 'pending' ? 'button-primary' : ''; ?>" href="<?php echo esc_url(admin_url('admin.php?page=tsf-moderation&tab=' . $tab . '&status_filter=pending')); ?>">Pending</a>
                <a class="button <?php echo $status_filter === 'all' ? 'button-primary' : ''; ?>" href="<?php echo esc_url(admin_url('admin.php?page=tsf-moderation&tab=' . $tab . '&status_filter=all')); ?>">All recent</a>
                <form method="get" style="display:flex;gap:8px;align-items:center;">
                    <input type="hidden" name="page" value="tsf-moderation">
                    <input type="hidden" name="tab" value="<?php echo esc_attr($tab); ?>">
                    <input type="hidden" name="status_filter" value="<?php echo esc_attr($status_filter); ?>">
                    <input type="search" name="moderation_search" value="<?php echo esc_attr($moderation_search); ?>" placeholder="Search moderation queue">
                    <button class="button">Search</button>
                </form>
            </div>

            <?php if ($tab === 'submissions'): ?>
                <h2>Listing submissions and edit suggestions</h2><p>Review new places and corrections before they go live.</p>
                <table class="widefat striped">
                    <thead><tr><th>ID</th><th>Type</th><th>Target</th><th>Editable payload</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($subs as $s): ?>
                        <tr>
                            <td><?php echo esc_html($s['id']); ?></td>
                            <td><?php echo esc_html($s['submission_type']); ?></td>
                            <td><?php echo esc_html($s['target_post_id']); ?></td>
                            <td>
                                <?php $payload = json_decode($s['payload'], true); ?>
                                <form method="post" style="display:grid;gap:6px;max-width:700px;">
                                    <?php wp_nonce_field('tsf_save_submission_' . (int)$s['id']); ?>
                                    <input type="hidden" name="submission_id" value="<?php echo (int)$s['id']; ?>">
                                    <input type="hidden" name="tsf_save_submission" value="1">
                                    <input type="text" name="title" value="<?php echo esc_attr($payload['title'] ?? ''); ?>" placeholder="Title">
                                    <input type="text" name="town_city" value="<?php echo esc_attr($payload['town_city'] ?? ''); ?>" placeholder="Town / city">
                                    <input type="text" name="postcode" value="<?php echo esc_attr($payload['postcode'] ?? ''); ?>" placeholder="Postcode">
                                    <input type="text" name="opening_hours" value="<?php echo esc_attr($payload['opening_hours'] ?? ''); ?>" placeholder="Opening hours">
                                    <input type="text" name="parking_type" value="<?php echo esc_attr($payload['parking_type'] ?? ''); ?>" placeholder="Parking type">
                                    <input type="text" name="price_night" value="<?php echo esc_attr($payload['price_night'] ?? ''); ?>" placeholder="Night price">
                                    <textarea name="description" placeholder="Description"><?php echo esc_textarea($payload['description'] ?? ''); ?></textarea>
                                    <textarea name="moderation_note" placeholder="Moderation note (optional)"></textarea>
                                    <button class="button">Save changes</button>
                                </form>
                            </td>
                            <td>
                                <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=tsf-moderation&approve_submission=' . (int)$s['id'])); ?>">Approve</a>
                                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=tsf-moderation&reject_submission=' . (int)$s['id'])); ?>">Reject</a>
                                <?php $history = TSF_Helpers::moderation_history('submission', (int)$s['id']); ?>
                                <?php if (!empty($history)): ?>
                                    <div style="margin-top:8px;font-size:12px;color:#6b7280;">
                                        <?php foreach ($history as $entry): ?>
                                            <div><?php echo esc_html($entry['action']); ?> — <?php echo esc_html($entry['created_at']); ?></div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (empty($subs)) echo '<p>No pending submissions.</p>'; ?>
            <?php endif; ?>

            <?php if ($tab === 'reviews'): ?>
                <h2 style="margin-top:24px;">Driver reviews</h2><p>Approve useful reviews and keep low-quality content out.</p>
                <table class="widefat striped">
                    <thead><tr><th>ID</th><th>Post</th><th>Rating</th><th>Review</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($reviews as $r): ?>
                        <tr>
                            <td><?php echo esc_html($r['id']); ?></td>
                            <td><?php echo esc_html($r['post_id']); ?></td>
                            <td><?php echo esc_html($r['rating']); ?></td>
                            <td><?php echo esc_html($r['review_text']); ?></td>
                            <td>
                                <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=tsf-moderation&approve_review=' . (int)$r['id'])); ?>">Approve</a>
                                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=tsf-moderation&reject_review=' . (int)$r['id'])); ?>">Reject</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (empty($reviews)) echo '<p>No pending reviews.</p>'; ?>
            <?php endif; ?>

            <?php if ($tab === 'photos'): ?>
                <h2 style="margin-top:24px;">Driver photos</h2><p>Approve clear, useful photos that help drivers trust the listing.</p>
                <table class="widefat striped">
                    <thead><tr><th>ID</th><th>Post</th><th>Attachment</th><th>Caption</th><th>Preview</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($photos as $p): ?>
                        <tr>
                            <td><?php echo esc_html($p['id']); ?></td>
                            <td><?php echo esc_html($p['post_id']); ?></td>
                            <td><?php echo esc_html($p['attachment_id']); ?></td>
                            <td><?php echo esc_html($p['caption']); ?></td>
                            <td><?php echo wp_get_attachment_image((int)$p['attachment_id'], [120, 120]); ?></td>
                            <td>
                                <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=tsf-moderation&approve_photo=' . (int)$p['id'])); ?>">Approve</a>
                                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=tsf-moderation&reject_photo=' . (int)$p['id'])); ?>">Reject</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (empty($photos)) echo '<p>No pending photos.</p>'; ?>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function save_submission_edits($id, $data) {
        global $wpdb;
        $t = TSF_DB::table('tsf_submissions');
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id=%d", $id), ARRAY_A);
        if (!$row) return;

        $payload = json_decode($row['payload'], true);
        if (!is_array($payload)) $payload = [];

        foreach (['title', 'town_city', 'postcode', 'opening_hours', 'parking_type', 'price_night', 'description'] as $field) {
            if (isset($data[$field])) {
                $payload[$field] = $field === 'description' ? wp_kses_post($data[$field]) : sanitize_text_field($data[$field]);
            }
        }

        $wpdb->update($t, ['payload' => wp_json_encode($payload)], ['id' => $id]);
    }

    private static function approve_submission($id) {
        global $wpdb;
        $t = TSF_DB::table('tsf_submissions');
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id=%d", $id), ARRAY_A);
        if (!$row || $row['status'] !== 'pending') return;

        $payload = json_decode($row['payload'], true);
        if ($row['submission_type'] === 'listing') {
            $post_id = wp_insert_post([
                'post_type' => 'truckstop',
                'post_status' => 'publish',
                'post_title' => sanitize_text_field($payload['title'] ?? 'Untitled Truck Stop'),
                'post_content' => wp_kses_post($payload['description'] ?? ''),
            ]);

            if ($post_id && !is_wp_error($post_id)) {
                TSF_Helpers::upsert_details($post_id, [
                    'address_line_1' => $payload['address_line_1'] ?? '',
                    'town_city' => $payload['town_city'] ?? '',
                    'postcode' => $payload['postcode'] ?? '',
                    'latitude' => $payload['latitude'] ?? '',
                    'longitude' => $payload['longitude'] ?? '',
                    'parking_type' => $payload['parking_type'] ?? '',
                    'opening_hours' => $payload['opening_hours'] ?? '',
                    'showers' => $payload['showers'] ?? 0,
                    'secure_parking' => $payload['secure_parking'] ?? 0,
                    'overnight_parking' => $payload['overnight_parking'] ?? 0,
                    'fuel' => $payload['fuel'] ?? 0,
                    'food' => $payload['food'] ?? 0,
                    'toilets' => $payload['toilets'] ?? 0,
                    'price_night' => $payload['price_night'] ?? '',
                ]);
            }
        } elseif ($row['submission_type'] === 'edit' && !empty($row['target_post_id'])) {
            $details = TSF_Helpers::get_details((int)$row['target_post_id']);
            if ($details && !empty($payload['field'])) {
                $details[$payload['field']] = $payload['value'] ?? '';
                TSF_Helpers::upsert_details((int)$row['target_post_id'], $details);
            }
        }

        $wpdb->update($t, ['status' => 'approved'], ['id' => $id]);
        TSF_Helpers::log_moderation('submission', $id, 'approved');
        if (!empty($row['driver_user_id'])) {
            TSF_Helpers::queue_notification((int)$row['driver_user_id'], '', 'Truckstop submission approved', 'Your submission has been approved and is now live.');
        }
    }

    public static function import_page() {
        if (!empty($_POST['tsf_import_nonce']) && wp_verify_nonce($_POST['tsf_import_nonce'], 'tsf_import') && !empty($_FILES['csv_file']['tmp_name'])) {
            $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
            $count = 0;
            if ($handle) {
                $header = fgetcsv($handle);
                while (($row = fgetcsv($handle)) !== false) {
                    $data = array_combine($header, $row);
                    if (empty($data['title'])) continue;

                    $post_id = wp_insert_post([
                        'post_type' => 'truckstop',
                        'post_status' => 'publish',
                        'post_title' => sanitize_text_field($data['title']),
                        'post_content' => sanitize_textarea_field($data['description'] ?? '')
                    ]);

                    if ($post_id && !is_wp_error($post_id)) {
                        TSF_Helpers::upsert_details($post_id, [
                            'town_city' => $data['town_city'] ?? '',
                            'postcode' => $data['postcode'] ?? '',
                            'latitude' => $data['lat'] ?? '',
                            'longitude' => $data['lng'] ?? '',
                            'showers' => $data['showers'] ?? 0,
                            'secure_parking' => $data['secure_parking'] ?? 0,
                            'overnight_parking' => $data['overnight_parking'] ?? 0,
                            'fuel' => $data['fuel'] ?? 0,
                            'food' => $data['food'] ?? 0,
                            'toilets' => $data['toilets'] ?? 0,
                            'price_night' => $data['price_night'] ?? '',
                            'featured' => $data['featured'] ?? 0,
                            'opening_hours' => $data['opening_hours'] ?? '',
                            'parking_type' => $data['parking_type'] ?? '',
                        ]);
                        $count++;
                    }
                }
                fclose($handle);
                echo '<div class="updated"><p>Imported ' . esc_html($count) . ' listings.</p></div>';
            }
        }
        ?>
        <div class="wrap">
            <h1>Import Listings CSV</h1>
            <p>CSV columns: title,description,town_city,postcode,lat,lng,showers,secure_parking,overnight_parking,fuel,food,toilets,price_night,featured,opening_hours,parking_type</p>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('tsf_import', 'tsf_import_nonce'); ?>
                <input type="file" name="csv_file" accept=".csv">
                <?php submit_button('Import CSV'); ?>
            </form>
        </div>
        <?php
    }

    public static function operators_page() {
        if (!TSF_Helpers::can_manage_truckstop()) wp_die('Permission denied.');
        global $wpdb;

        if (isset($_GET['approve_claim'])) {
            $id = (int)$_GET['approve_claim'];
            $claims_table = TSF_DB::table('tsf_operator_claims');
            $claim = $wpdb->get_row($wpdb->prepare("SELECT * FROM $claims_table WHERE id = %d", $id), ARRAY_A);
            if ($claim) {
                $wpdb->update($claims_table, ['status' => 'approved'], ['id' => $id]);
                $details = TSF_Helpers::get_details((int)$claim['post_id']);
                if ($details) {
                    TSF_Helpers::upsert_details((int)$claim['post_id'], array_merge($details, [
                        'owner_email' => $claim['operator_email'],
                    ]));
                }
                $operator_user_id = TSF_Helpers::create_operator_user($claim['operator_email'], $claim['operator_name']);
                TSF_Helpers::grant_operator_listing_access($operator_user_id, (int)$claim['post_id']);
                TSF_Helpers::queue_notification(null, $claim['operator_email'], 'Operator claim approved', 'Your operator claim has been approved.');
            }
        }

        $claims = TSF_Helpers::operator_claims();
        $orders = TSF_Helpers::feature_orders();
        $plans = TSF_Payments::plans();
        $posts = get_posts(['post_type' => 'truckstop', 'post_status' => 'publish', 'posts_per_page' => 200]);
        ?>
        <div class="wrap">
            <h1>Operators</h1>
            <div style="display:grid;grid-template-columns:repeat(3,minmax(160px,1fr));gap:12px;margin:16px 0 24px;">
                <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px;"><strong><?php echo esc_html(count($claims)); ?></strong><br>Total claims</div>
                <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px;"><strong><?php echo esc_html(count($orders)); ?></strong><br>Feature orders</div>
                <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px;"><strong><?php echo esc_html(count(array_filter($orders, function($o){ return !empty($o['featured_until']) && strtotime($o['featured_until']) >= time(); }))); ?></strong><br>Active paid features</div>
            </div>

            <h2>Pending / recent claims</h2>
            <table class="widefat striped">
                <thead><tr><th>Listing</th><th>Operator</th><th>Email</th><th>Phone</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>
                <?php foreach ($claims as $claim): $post = get_post((int)$claim['post_id']); ?>
                    <tr>
                        <td><?php echo esc_html($post ? $post->post_title : ('#' . $claim['post_id'])); ?></td>
                        <td><?php echo esc_html($claim['operator_name']); ?></td>
                        <td><?php echo esc_html($claim['operator_email']); ?></td>
                        <td><?php echo esc_html($claim['phone']); ?></td>
                        <td><?php echo esc_html($claim['status']); ?></td>
                        <td>
                            <?php if ($claim['status'] === 'pending'): ?>
                                <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=tsf-operators&approve_claim=' . (int)$claim['id'])); ?>">Approve claim</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <h2 style="margin-top:24px;">Record featured listing payment</h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px;max-width:900px;">
                <?php wp_nonce_field('tsf_feature_checkout'); ?>
                <input type="hidden" name="action" value="tsf_feature_checkout">
                <table class="form-table">
                    <tr>
                        <th>Listing</th>
                        <td><select name="post_id"><?php foreach ($posts as $post): ?><option value="<?php echo (int)$post->ID; ?>"><?php echo esc_html($post->post_title); ?></option><?php endforeach; ?></select></td>
                    </tr>
                    <tr><th>Operator email</th><td><input type="email" name="operator_email" class="regular-text" required></td></tr>
                    <tr>
                        <th>Plan</th>
                        <td><select name="plan_key"><?php foreach ($plans as $key => $plan): ?><option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($plan['label'] . ' - £' . number_format($plan['amount'], 2)); ?></option><?php endforeach; ?></select></td>
                    </tr>
                    <tr>
                        <th>Style</th>
                        <td><select name="feature_style"><?php foreach (TSF_Payments::sponsored_styles() as $key => $style): ?><option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($style['label']); ?></option><?php endforeach; ?></select></td>
                    </tr>
                    <tr><th>Payment reference</th><td><input type="text" name="payment_reference" class="regular-text"></td></tr>
                </table>
                <?php submit_button('Mark paid and feature listing'); ?>
            </form>

            <h2 style="margin-top:24px;">Feature orders / billing history</h2>
            <table class="widefat striped">
                <thead><tr><th>Listing ID</th><th>Operator email</th><th>Plan</th><th>Amount</th><th>Status</th><th>Checkout</th><th>Webhook test</th><th>Featured until</th><th>Expiry state</th></tr></thead>
                <tbody>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><?php echo esc_html($order['post_id']); ?></td>
                        <td><?php echo esc_html($order['operator_email']); ?></td>
                        <td><?php echo esc_html($order['plan_key']); ?></td>
                        <td>£<?php echo esc_html(number_format((float)$order['amount_gbp'], 2)); ?></td>
                        <td><?php echo esc_html($order['status']); ?></td>
                        <td><a href="<?php echo esc_url(TSF_Payments::checkout_url((int)$order['id'])); ?>">Open</a></td>
                        <td><code>{"order_id":<?php echo (int)$order['id']; ?>,"event":"checkout.session.completed","payment_reference":"demo_<?php echo (int)$order['id']; ?>"}</code></td>
                        <td><?php echo esc_html($order['featured_until']); ?></td><td><?php echo (!empty($order['featured_until']) && strtotime($order['featured_until']) < time()) ? 'Expired' : 'Active / pending'; ?></td>
                        <td><?php echo (!empty($order['featured_until']) && strtotime($order['featured_until']) < time()) ? 'Expired' : 'Active / pending'; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public static function analytics_page() {
        if (!TSF_Helpers::can_manage_truckstop()) wp_die('Permission denied.');
        $posts = get_posts(['post_type' => 'truckstop', 'post_status' => 'publish', 'posts_per_page' => 100]);
        ?>
        <div class="wrap">
            <h1>Listing analytics</h1>
            <div style="display:grid;grid-template-columns:repeat(4,minmax(140px,1fr));gap:12px;margin:16px 0 24px;">
                <?php $community = TSF_Helpers::community_stats(); ?>
                <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px;"><strong><?php echo esc_html($community['listings']); ?></strong><br>Listings</div>
                <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px;"><strong><?php echo esc_html($community['reviews']); ?></strong><br>Approved reviews</div>
                <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px;"><strong><?php echo esc_html($community['photos']); ?></strong><br>Approved photos</div>
                <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px;"><strong><?php echo esc_html($community['contributors']); ?></strong><br>Active contributors</div>
            </div>
            <h2>Top contributors</h2>
            <table class="widefat striped" style="margin-bottom:24px;">
                <thead><tr><th>Driver</th><th>Level</th><th>Score</th><th>Submissions</th><th>Reviews</th><th>Photos</th></tr></thead>
                <tbody>
                <?php foreach (TSF_Helpers::top_contributors(10) as $driver): ?>
                    <tr>
                        <td><?php echo esc_html($driver['name']); ?></td>
                        <td><?php echo esc_html($driver['label']); ?></td>
                        <td><?php echo esc_html($driver['score']); ?></td>
                        <td><?php echo esc_html($driver['submissions']); ?></td>
                        <td><?php echo esc_html($driver['reviews']); ?></td>
                        <td><?php echo esc_html($driver['photos']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <h2>Recent community activity</h2>
            <table class="widefat striped" style="margin-bottom:24px;">
                <thead><tr><th>Type</th><th>Listing</th><th>Detail</th><th>Date</th></tr></thead>
                <tbody>
                <?php foreach (TSF_Helpers::recent_community_activity(12) as $item): ?>
                    <tr>
                        <td><?php echo esc_html(ucfirst($item['type'])); ?></td>
                        <td><?php if (!empty($item['url'])): ?><a href="<?php echo esc_url($item['url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($item['title']); ?></a><?php else: ?><?php echo esc_html($item['title']); ?><?php endif; ?></td>
                        <td><?php echo esc_html($item['meta']); ?></td>
                        <td><?php echo esc_html($item['created_at']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <h2>Listings</h2>
            <table class="widefat striped">
                <thead><tr><th>Listing</th><th>Saved</th><th>Reviews</th><th>Photos</th></tr></thead>
                <tbody>
                <?php foreach ($posts as $post): $a = TSF_Helpers::listing_analytics($post->ID); ?>
                    <tr>
                        <td><a href="<?php echo esc_url(get_permalink($post->ID)); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($post->post_title); ?></a></td>
                        <td><?php echo esc_html($a['saved_count']); ?></td>
                        <td><?php echo esc_html($a['review_count']); ?></td>
                        <td><?php echo esc_html($a['photo_count']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
