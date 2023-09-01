<?php

declare(strict_types=1);

use Relay\Relay;
use Relay\Table;

class RelayWordPressLocalization
{
    static object $config;

    static Relay $connection;

    static Table $cache;

    public static function boot(object $config, Relay $connection): void
    {
        global $wp_version;

        static::$config = $config;
        static::$connection = $connection;
        static::$cache = new Table;

        add_action('upgrader_process_complete', [__CLASS__, 'handleTranslationUpdates'], 10, 2);

        add_filter('pre_load_script_translations', [__CLASS__, 'loadScriptTranslations'], 1, 4);

        if (version_compare($wp_version, '6.3', '>=')) {
            add_filter('pre_load_textdomain', [__CLASS__, 'loadTextdomain'], 1, 4);
        } else {
            add_filter('override_load_textdomain', [__CLASS__, 'loadTextdomain'], 0, 3);
        }
    }

    public static function loadTextdomain($override, $domain, $mofile, $locale = null): bool
    {
        global $l10n, $l10n_unloaded, $wp_textdomain_registry;

        if ($override) {
            error_log('relay-wp-l10n.warning: A plugin is already overriding textdomain loading');

			return $override;
		}

        if (! $locale) {
            $locale = determine_locale();
        }

        $driver = new RelayTranslations(static::$config, static::$connection);

	    do_action('load_textdomain', $domain, $mofile);

	    $mofile = apply_filters('load_textdomain_mofile', $mofile, $domain);

        if (! static::isFileReadable($mofile)) {
            return false;
        }

        $hash = substr(base_convert(md5(str_replace(ABSPATH, '', $mofile)), 16, 32), 0, 12);
        $key = "{$domain}-{$locale}-{$hash}-mo";

        if (! $driver->load($domain, $mofile, $locale, $key)) {
            $wp_textdomain_registry->set($domain, $locale, false);

            return false;
        }

        if (isset($l10n[$domain])) {
            $driver->merge_with($l10n[$domain]);
        }

        unset($l10n_unloaded[$domain]);

        $l10n[$domain] = $driver;

        $wp_textdomain_registry->set($domain, $locale, dirname($mofile));

        return true;
    }

    public static function loadScriptTranslations($override, $file, $handle, $domain)
    {
        if ($override) {
            error_log('relay-wp-l10n.warning: A plugin is already overriding script textdomain loading');

			return $override;
		}

        $file = apply_filters('load_script_translation_file', $file, $handle, $domain);

        if (! $file) {
            return false;
        }

        if (! static::isFileReadable($file)) {
            return false;
        }

        $driver = new RelayTranslations(static::$config, static::$connection);

        $locale = strstr(substr(basename($file), $domain === 'default' ? 0 : strlen($domain) + 1), '-', true);
        $hash = substr(base_convert(md5(str_replace(ABSPATH, '', $file)), 16, 32), 0, 12);
        $key = "{$domain}-{$locale}-{$hash}-json";

        $translations = $driver->loadJson($file, $handle, $domain, $key);

        return apply_filters('load_script_translations', $translations, $file, $handle, $domain);
    }

    public static function handleTranslationUpdates($upgrader, $hook_extra)
    {
		if ($hook_extra['type'] !== 'translation') {
			return;
		}

        if (! empty($hook_extra['translations'])) {
            $driver = new RelayTranslations(static::$config, static::$connection);

            if (! $driver->flush()) {
                error_log('relay-wp-l10n.warning: Failed to invalidate translations');
            }
        }
	}

    public static function isFileReadable($file): bool
    {
        $hash = substr(base_convert(md5($file), 16, 32), 0, 12);

        $isReadable = static::$cache->get("readable:{$hash}");

        if (is_null($isReadable)) {
            $isReadable = is_readable($file);

            static::$cache->set("readable:{$hash}", $isReadable);
        }

        return $isReadable;
    }
}
