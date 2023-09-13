<?php
/*
 * Plugin Name: Relay WordPress Localization
 * Plugin URI: https://relay.so
 * Description: Faster WordPress localization using Relay.
 * Version: 0.2.1-dev
 * Author: CacheWerk
 * Author URI: https://cachewerk.com
 * License: MIT
 * Requires PHP: 7.4
 */

declare(strict_types=1);

if (! defined('RELAY_L10N_CONFIG') || ! is_array(RELAY_L10N_CONFIG)) {
    return;
}

if (! extension_loaded('relay')) {
    error_log('relay-wp-l10n.warning: Relay extension not loaded');

    return;
}

if (version_compare(phpversion('relay'), '0.6.6', '<=')) {
    error_log('relay-wp-l10n.warning: Relay v0.6.7 required');

    return;
}

require_once __DIR__ . '/src/plugin.php';
require_once __DIR__ . '/src/connection.php';
require_once __DIR__ . '/src/translations.php';

(function ($config) {
    $config = (object) array_merge([
        'scheme' => 'tcp',
        'host' => '127.0.0.1',
        'port' => 6379,
        'database' => 0,
        'username' => null,
        'password' => null,
        'prefix' => null,
        'timeout' => 0.5,
        'read_timeout' => 0.5,
        'backoff' => 'smart',
        'retries' => 3,
        'retry_interval' => 20,
        'tls_options' => false,
        'persistent' => false,
        'footnote' => true,
    ], $config);

    $connection = RelayConnector::connectToInstance($config);

    if (! $connection->maxMemory()) {
        error_log('relay-wp-l10n.warning: Relay in client-only mode');
    }

    RelayWordPressLocalization::boot($config, $connection);
})(RELAY_L10N_CONFIG);
