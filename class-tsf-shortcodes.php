<?php
if (!defined('ABSPATH')) exit;

class TSF_Shortcodes {
    public static function maybe_hide_admin_bar($show) {
        return $show;
    }

    public static function is_app_page() {
        return false;
    }

    public static function is_shortcode_page() {
        if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) return false;
        if (!is_singular()) return false;

        global $post;
        if (!$post instanceof WP_Post) return false;

        return has_shortcode((string)$post->post_content, 'truckstop_finder');
    }

    public static function enqueue_assets() {
        if (self::is_shortcode_page()) {
            wp_register_script('tsf-app', TSF_URL . 'assets/app.js', [], TSF_VERSION, true);
            wp_register_style('tsf-style', TSF_URL . 'assets/style.css', [], TSF_VERSION);
            wp_localize_script('tsf-app', 'TSF_CONFIG', [
                'apiBase' => esc_url_raw(rest_url('tsf/v1')),
                'googleMapsKey' => get_option('tsf_google_maps_key', ''),
            ]);
            wp_enqueue_script('tsf-app');
            wp_enqueue_style('tsf-style');
        }
    }

    public static function maybe_render_app_page() {
        return;
    }

    public static function render_app() {
        return '<div id="tsf-app"></div>';
    }

    public static function output_schema() {
        if (!is_singular('truckstop')) return;
        $post = get_post();
        $d = TSF_Helpers::get_details($post->ID);
        if (!$d) return;

        $summary = TSF_Helpers::review_summary($post->ID);
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'LocalBusiness',
            'name' => get_the_title($post),
            'url' => get_permalink($post),
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => $d['address_line_1'] ?? '',
                'addressLocality' => $d['town_city'] ?? '',
                'addressRegion' => $d['county'] ?? '',
                'postalCode' => $d['postcode'] ?? '',
                'addressCountry' => $d['country'] ?? 'United Kingdom'
            ]
        ];
        if (!empty($d['latitude']) && !empty($d['longitude'])) {
            $schema['geo'] = [
                '@type' => 'GeoCoordinates',
                'latitude' => (float)$d['latitude'],
                'longitude' => (float)$d['longitude']
            ];
        }
        if ($summary['count'] > 0) {
            $schema['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => $summary['avg'],
                'reviewCount' => $summary['count']
            ];
        }

        echo '<script type="application/ld+json">' . wp_json_encode($schema) . '</script>';
    }
}
