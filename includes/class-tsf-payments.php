<?php
if (!defined('ABSPATH')) exit;

class TSF_Payments {
    public static function gateway_mode() {
        return get_option('tsf_payment_gateway_mode', 'manual');
    }

    public static function stripe_publishable_key() {
        return get_option('tsf_stripe_publishable_key', '');
    }

    public static function stripe_secret_key() {
        return get_option('tsf_stripe_secret_key', '');
    }

    public static function webhook_url() {
        return admin_url('admin-post.php?action=tsf_stripe_webhook');
    }

    public static function sponsored_styles() {
        return [
            'standard' => ['label' => 'Standard featured', 'class' => 'tsf-badge'],
            'gold' => ['label' => 'Gold sponsor', 'class' => 'tsf-badge tsf-badge-gold'],
            'premium' => ['label' => 'Premium sponsor', 'class' => 'tsf-badge tsf-badge-premium'],
        ];
    }

    public static function plans() {
        return [
            'featured_30' => ['label' => 'Featured listing - 30 days', 'amount' => 29.00, 'days' => 30],
            'featured_90' => ['label' => 'Featured listing - 90 days', 'amount' => 69.00, 'days' => 90],
        ];
    }

    public static function checkout_url($order_id) {
        if (self::gateway_mode() === 'stripe_ready') {
            return admin_url('admin.php?page=tsf-operators&tsf_checkout_ready=' . (int)$order_id);
        }
        return admin_url('admin.php?page=tsf-operators&tsf_manual_order=' . (int)$order_id);
    }

    public static function handle_feature_checkout() {
        if (!current_user_can('manage_options')) wp_die('Permission denied.');
        check_admin_referer('tsf_feature_checkout');

        global $wpdb;
        $post_id = (int)($_POST['post_id'] ?? 0);
        $operator_email = sanitize_email($_POST['operator_email'] ?? '');
        $plan_key = sanitize_text_field($_POST['plan_key'] ?? '');
        $reference = sanitize_text_field($_POST['payment_reference'] ?? '');
        $feature_style = sanitize_text_field($_POST['feature_style'] ?? 'standard');

        $plans = self::plans();
        if (!$post_id || !$operator_email || empty($plans[$plan_key])) {
            wp_safe_redirect(admin_url('admin.php?page=tsf-operators&tsf_error=1'));
            exit;
        }

        $plan = $plans[$plan_key];
        $featured_until = gmdate('Y-m-d H:i:s', strtotime('+' . (int)$plan['days'] . ' days'));

        $wpdb->insert(TSF_DB::table('tsf_feature_orders'), [
            'post_id' => $post_id,
            'operator_email' => $operator_email,
            'plan_key' => $plan_key,
            'amount_gbp' => $plan['amount'],
            'status' => 'paid',
            'payment_reference' => $reference,
            'checkout_status' => 'manual_paid',
            'featured_until' => $featured_until,
        ]);

        $details = TSF_Helpers::get_details($post_id);
        if ($details) {
            TSF_Helpers::upsert_details($post_id, array_merge($details, [
                'featured' => 1,
                'owner_email' => $operator_email,
                'feature_style' => $feature_style,
                'is_featured_paid' => 1,
                'featured_until' => $featured_until,
            ]));
        }

        TSF_Helpers::queue_notification(null, $operator_email, 'Truckstop featured upgrade confirmed', 'Your listing has been upgraded to featured status until ' . $featured_until . '.');
        wp_safe_redirect(admin_url('admin.php?page=tsf-operators&tsf_success=1'));
        exit;
    }

    public static function activate_feature_order($order_id, $payment_reference = '') {
        global $wpdb;
        $table = TSF_DB::table('tsf_feature_orders');
        $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", (int)$order_id), ARRAY_A);
        if (!$order) return false;

        $plans = self::plans();
        if (empty($plans[$order['plan_key']])) return false;
        $plan = $plans[$order['plan_key']];
        $featured_until = gmdate('Y-m-d H:i:s', strtotime('+' . (int)$plan['days'] . ' days'));

        $wpdb->update($table, [
            'status' => 'paid',
            'payment_reference' => sanitize_text_field($payment_reference),
            'checkout_status' => 'completed',
            'featured_until' => $featured_until,
        ], ['id' => (int)$order_id]);

        $details = TSF_Helpers::get_details((int)$order['post_id']);
        if ($details) {
            TSF_Helpers::upsert_details((int)$order['post_id'], array_merge($details, [
                'featured' => 1,
                'owner_email' => $order['operator_email'],
                'is_featured_paid' => 1,
                'featured_until' => $featured_until,
            ]));
        }

        TSF_Helpers::queue_notification(null, $order['operator_email'], 'Featured upgrade activated', 'Your featured upgrade is now active until ' . $featured_until . '.');
        TSF_Helpers::log_moderation('feature_order', (int)$order_id, 'activated', 'Payment completed');
        return true;
    }

    public static function create_checkout_session($order_id) {
        global $wpdb;
        $table = TSF_DB::table('tsf_feature_orders');
        $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", (int)$order_id), ARRAY_A);
        if (!$order) return ['ok' => false, 'message' => 'Order not found.'];

        if (self::gateway_mode() !== 'stripe_ready') {
            return [
                'ok' => true,
                'checkout_url' => self::checkout_url($order_id),
                'session_id' => '',
                'message' => 'Manual mode enabled. Admin confirmation required.',
            ];
        }

        $session_id = 'tsf_cs_' . wp_generate_password(18, false, false);
        $checkout_url = admin_url('admin.php?page=tsf-operators&tsf_checkout_ready=' . (int)$order_id . '&session=' . rawurlencode($session_id));

        $wpdb->update($table, [
            'checkout_session_id' => $session_id,
            'checkout_status' => 'created',
            'status' => 'checkout_ready',
        ], ['id' => (int)$order_id]);

        return [
            'ok' => true,
            'checkout_url' => $checkout_url,
            'session_id' => $session_id,
            'message' => 'Checkout-ready session created.',
        ];
    }

    public static function handle_webhook() {
        $payload = file_get_contents('php://input');
        $data = json_decode($payload, true);
        if (!is_array($data)) {
            status_header(400);
            echo 'invalid';
            exit;
        }

        $order_id = isset($data['order_id']) ? (int)$data['order_id'] : 0;
        $event = sanitize_text_field($data['event'] ?? '');
        $payment_reference = sanitize_text_field($data['payment_reference'] ?? '');

        if (!$order_id || !$event) {
            status_header(400);
            echo 'missing_fields';
            exit;
        }

        if ($event === 'checkout.session.completed' || $event === 'payment.succeeded') {
            self::activate_feature_order($order_id, $payment_reference ?: ('webhook_' . $order_id));
            status_header(200);
            echo 'ok';
            exit;
        }

        status_header(200);
        echo 'ignored';
        exit;
    }
}
