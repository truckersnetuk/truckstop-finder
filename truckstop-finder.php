<?php
/**
 * Plugin Name: Truckers Net Finder
 * Description: HGV truck stop finder with map search, driver accounts, reviews, favourites, submissions, edit suggestions, photo uploads, moderation, duplicate checks, postcode/radius search, listing detail views, and custom single templates.
 * Version: 10.7.1
 * Author: Truckers Net
Author URI: https://truckersnet.co.uk
Update URI: truckersnet-finder-github-updater
 * Text Domain: truckstop-finder
 */

if (!defined('ABSPATH')) exit;

define('TSF_VERSION', '10.3.100');
define('TSF_PATH', plugin_dir_path(__FILE__));
define('TSF_URL', plugin_dir_url(__FILE__));

require_once TSF_PATH . 'includes/class-tsf-db.php';
require_once TSF_PATH . 'includes/class-tsf-post-types.php';
require_once TSF_PATH . 'includes/class-tsf-auth.php';
require_once TSF_PATH . 'includes/class-tsf-helpers.php';
require_once TSF_PATH . 'includes/class-tsf-rest.php';
require_once TSF_PATH . 'includes/class-tsf-admin.php';
require_once TSF_PATH . 'includes/class-tsf-shortcodes.php';
require_once TSF_PATH . 'includes/class-tsf-templates.php';
require_once TSF_PATH . 'includes/class-tsf-payments.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-tsf-github-updater.php';

register_activation_hook(__FILE__, ['TSF_DB', 'activate']);
register_deactivation_hook(__FILE__, ['TSF_DB', 'deactivate']);

add_action('init', ['TSF_Post_Types', 'register']);
add_action('rest_api_init', ['TSF_REST', 'register_routes']);
add_action('admin_menu', ['TSF_Admin', 'register_menu']);
add_action('admin_init', ['TSF_Admin', 'register_settings']);
add_action('admin_post_tsf_feature_checkout', ['TSF_Payments', 'handle_feature_checkout']);
add_action('wp_enqueue_scripts', ['TSF_Shortcodes', 'enqueue_assets']);
add_shortcode('truckstop_finder', ['TSF_Shortcodes', 'render_app']);
add_action('wp_head', ['TSF_Shortcodes', 'output_schema']);
add_filter('template_include', ['TSF_Templates', 'template_include']);
add_action('template_redirect', ['TSF_Shortcodes', 'maybe_render_app_page'], 0);
add_filter('show_admin_bar', ['TSF_Shortcodes', 'maybe_hide_admin_bar']);

add_action('init', function () {
    if (!wp_next_scheduled('tsf_send_notifications')) {
        wp_schedule_event(time() + 300, 'hourly', 'tsf_send_notifications');
    }
    if (!wp_next_scheduled('tsf_featured_maintenance')) {
        wp_schedule_event(time() + 600, 'hourly', 'tsf_featured_maintenance');
    }
});
add_action('tsf_send_notifications', ['TSF_Helpers', 'send_queued_notifications']);
add_action('tsf_featured_maintenance', ['TSF_Helpers', 'expire_featured_listings']);


add_action('admin_post_nopriv_tsf_stripe_webhook', ['TSF_Payments', 'handle_webhook']);
add_action('admin_post_tsf_stripe_webhook', ['TSF_Payments', 'handle_webhook']);

// Safety guards
if (!class_exists('TSF_Admin')) { error_log('TSF_Admin class missing'); }


if (class_exists('TSF_GitHub_Updater')) {
    TSF_GitHub_Updater::boot(__FILE__);
}
