<?php
/*
 * Plugin Name: Relay WordPress Localization
 * Plugin URI: https://relay.so
 * Description: Faster WordPress localization using Relay.
 * Version: 1.0.0
 * Author: CacheWerk
 * Author URI: https://cachewerk.com
 * License: MIT
 * Requires PHP: 7.4
 */

declare(strict_types=1);

if (! defined('RELAY_L10N_CONFIG') || ! is_array(RELAY_L10N_CONFIG)) {
    return;
}

require_once __DIR__ . '/src/plugin.php';
require_once __DIR__ . '/src/connector.php';
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

    RelayWordPressLocalization::boot(__FILE__, $config, $connection);
})(RELAY_L10N_CONFIG);
