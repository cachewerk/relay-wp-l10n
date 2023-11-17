# WordPress Localization cache using Relay

Use [Relay](https://relay.so) to store WordPress translation in PHP runtime memory.

## Installation

Install as plugin, or Must-Use plugin.

### Composer installation

To install the plugin using Composer, add the repository to your `composer.json`:

```json
{
  "repositories": [
    { "type": "vcs", "url": "git@github.com:cachewerk/relay-wp-l10n.git" }
  ],
}
```

Then simply run:

```bash
composer require cachewerk/relay-wp-l10n
```

## Caveats

1. If `RELAY_L10N_CONFIG` is not set, the plugin will do nothing
2. Translations are invalidated using `FLUSHDB`, be sure to set a dedicated `database` for translations so it won't flush the regular object cache as well
3. Relay's `Table` class currently caches data on a per-worker basis, so the cache needs to warm up for all workers in a FPM pool

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
    'footnote' => true,
] );
```
