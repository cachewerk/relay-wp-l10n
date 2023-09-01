# Relay WordPress Localization

Use [Relay](https://relay.so) to store WordPress translation in PHP runtime memory.

## Installation

Install as plugin, or Must-Use plugin.

## Caveats

- Translations are invalidated using `FLUSHDB`, be sure to set a dedicated `database` for translations so it won't flush the regular object cache as well

## Configuration

Add the `RELAY_L10N_CONFIG` constant to your `wp-config.php`.

```php
define('RELAY_L10N_CONFIG', [
    'host' => $_SERVER['CACHE_HOST'],
    'port' => $_SERVER['CACHE_PORT'],
    'database' => $_SERVER['CACHE_DB'] + 1,
    'password' => $_SERVER['CACHE_PASSWORD'],
] );
```

The default values are:

```php
define('RELAY_L10N_CONFIG', [
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
] );
```
