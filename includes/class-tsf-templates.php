<?php
if (!defined('ABSPATH')) exit;

class TSF_Templates {
    public static function template_include($template) {
        if (is_singular('truckstop')) {
            $plugin_template = TSF_PATH . 'templates/single-truckstop.php';
            if (file_exists($plugin_template)) return $plugin_template;
        }
        return $template;
    }
}
