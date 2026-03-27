<?php
if (!defined('ABSPATH')) exit;

class TSF_Post_Types {
    public static function register() {
        register_post_type('truckstop', [
            'labels' => ['name' => 'Truck Stops', 'singular_name' => 'Truck Stop'],
            'public' => true,
            'show_in_rest' => true,
            'menu_icon' => 'dashicons-location-alt',
            'supports' => ['title', 'editor', 'excerpt', 'thumbnail'],
            'has_archive' => true,
            'rewrite' => ['slug' => 'truckstops']
        ]);
    }
}
