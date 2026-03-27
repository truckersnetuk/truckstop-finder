<?php
if (!defined('ABSPATH')) exit;

class TSF_GitHub_Updater {
    private static $plugin_file = '';
    private static $plugin_basename = '';
    private static $plugin_slug = '';
    private static $version = '';
    private static $config = [];

    public static function boot($plugin_file) {
        self::$plugin_file = $plugin_file;
        self::$plugin_basename = plugin_basename($plugin_file);
        self::$plugin_slug = dirname(self::$plugin_basename);

        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugin_data = get_plugin_data($plugin_file, false, false);
        self::$version = isset($plugin_data['Version']) ? (string) $plugin_data['Version'] : '0.0.0';

        self::$config = apply_filters('tsf_github_updater_config', [
            'owner' => '',
            'repo' => '',
            'tag_prefix' => 'v',
            'branch' => 'main',
            'token' => '',
            'asset_name' => 'truckstop-finder.zip',
            'homepage' => 'https://truckersnet.co.uk',
        ]);

        add_filter('pre_set_site_transient_update_plugins', [__CLASS__, 'check_for_update']);
        add_filter('plugins_api', [__CLASS__, 'plugins_api'], 10, 3);
    }

    private static function has_repo_config() {
        return !empty(self::$config['owner']) && !empty(self::$config['repo']);
    }

    private static function api_headers() {
        $headers = [
            'Accept' => 'application/vnd.github+json',
            'User-Agent' => 'Truckers-Net-Finder-Updater',
        ];
        if (!empty(self::$config['token'])) {
            $headers['Authorization'] = 'Bearer ' . self::$config['token'];
        }
        return $headers;
    }

    private static function latest_release() {
        if (!self::has_repo_config()) return null;

        $url = sprintf(
            'https://api.github.com/repos/%s/%s/releases/latest',
            rawurlencode(self::$config['owner']),
            rawurlencode(self::$config['repo'])
        );

        $response = wp_remote_get($url, [
            'timeout' => 20,
            'headers' => self::api_headers(),
        ]);

        if (is_wp_error($response)) return null;
        if (wp_remote_retrieve_response_code($response) !== 200) return null;

        $body = json_decode(wp_remote_retrieve_body($response), true);
        return is_array($body) ? $body : null;
    }

    private static function normalize_version($tag_name) {
        $tag_name = (string) $tag_name;
        $prefix = (string) (self::$config['tag_prefix'] ?? '');
        if ($prefix && strpos($tag_name, $prefix) === 0) {
            $tag_name = substr($tag_name, strlen($prefix));
        }
        return ltrim($tag_name, 'vV');
    }

    private static function package_url($release) {
        if (!is_array($release)) return '';

        $asset_name = (string) (self::$config['asset_name'] ?? '');
        if (!empty($release['assets']) && is_array($release['assets'])) {
            foreach ($release['assets'] as $asset) {
                if (!empty($asset['name']) && $asset['name'] === $asset_name && !empty($asset['browser_download_url'])) {
                    return (string) $asset['browser_download_url'];
                }
            }
            foreach ($release['assets'] as $asset) {
                if (!empty($asset['browser_download_url']) && preg_match('/\.zip$/i', (string) $asset['browser_download_url'])) {
                    return (string) $asset['browser_download_url'];
                }
            }
        }

        if (!empty($release['zipball_url'])) {
            return (string) $release['zipball_url'];
        }

        return '';
    }

    public static function check_for_update($transient) {
        if (!is_object($transient)) $transient = new stdClass();
        if (!self::has_repo_config()) return $transient;

        $release = self::latest_release();
        if (!$release || empty($release['tag_name'])) return $transient;

        $new_version = self::normalize_version($release['tag_name']);
        if (!$new_version || !version_compare($new_version, self::$version, '>')) return $transient;

        $package = self::package_url($release);
        if (!$package) return $transient;

        $obj = new stdClass();
        $obj->slug = self::$plugin_slug;
        $obj->plugin = self::$plugin_basename;
        $obj->new_version = $new_version;
        $obj->package = $package;
        $obj->url = !empty($release['html_url']) ? (string) $release['html_url'] : (string) (self::$config['homepage'] ?? '');
        $obj->icons = [];
        $obj->banners = [];

        if (!isset($transient->response) || !is_array($transient->response)) {
            $transient->response = [];
        }
        $transient->response[self::$plugin_basename] = $obj;

        return $transient;
    }

    public static function plugins_api($result, $action, $args) {
        if ($action !== 'plugin_information' || empty($args->slug) || $args->slug !== self::$plugin_slug) {
            return $result;
        }
        if (!self::has_repo_config()) {
            return $result;
        }

        $release = self::latest_release();
        if (!$release) return $result;

        $info = new stdClass();
        $info->name = 'Truckers Net Finder';
        $info->slug = self::$plugin_slug;
        $info->version = self::normalize_version($release['tag_name'] ?? self::$version);
        $info->author = '<a href="https://truckersnet.co.uk">Truckers Net</a>';
        $info->homepage = !empty($release['html_url']) ? (string) $release['html_url'] : (string) (self::$config['homepage'] ?? '');
        $info->download_link = self::package_url($release);
        $info->sections = [
            'description' => !empty($release['body']) ? wp_kses_post(wpautop((string) $release['body'])) : 'GitHub-managed release for Truckers Net Finder.',
            'changelog' => !empty($release['body']) ? wp_kses_post(wpautop((string) $release['body'])) : 'No changelog provided.',
        ];

        return $info;
    }
}
