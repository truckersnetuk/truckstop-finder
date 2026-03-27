# GitHub updater setup for Truckers Net Finder

This build includes a lightweight GitHub updater scaffold.

## Add your repo config
Put this in your theme `functions.php` or a small mu-plugin:

```php
add_filter('tsf_github_updater_config', function($config){
    $config['owner'] = 'YOUR_GITHUB_USERNAME_OR_ORG';
    $config['repo'] = 'YOUR_REPO_NAME';
    $config['tag_prefix'] = 'v';
    $config['branch'] = 'main';
    $config['asset_name'] = 'truckstop-finder.zip';
    $config['token'] = ''; // only needed for private repos
    $config['homepage'] = 'https://truckersnet.co.uk';
    return $config;
});
```

## Recommended release flow
1. Put the plugin in a GitHub repo.
2. Tag releases like `v10.7.1`.
3. Attach a ZIP asset named `truckstop-finder.zip`.
4. WordPress will detect newer versions from GitHub releases.
