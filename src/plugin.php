<?php

declare(strict_types=1);

use Relay\Relay;
use Relay\Table;

class RelayWordPressLocalization
{
    static string $file;

    static object $config;

    static Relay $connection;

    static Table $cache;

    static bool $shouldPrintFootnote = false;

    static ?string $error = null;

    static int $statsMoLoaded = 0;
    static int $statsJsonLoaded = 0;
    static int $statsLoadFailed = 0;
    static int $statsMoNotReadable = 0;
    static int $statsJsonNotReadable = 0;
    static int $statsIsReadableCall = 0;
    static int $statsIsReadableCached = 0;

    public static function boot(string $file, object $config): void
    {
        global $wp_version;

        static::$file = $file;
        static::$config = $config;

        add_action('admin_notices', [__CLASS__, 'maybePrintAdminNotice']);

        add_action('wp_footer', [__CLASS__, 'shouldPrintMetricsFootnote']);
        add_action('wp_body_open', [__CLASS__, 'shouldPrintMetricsFootnote']);
        add_action('login_head', [__CLASS__, 'shouldPrintMetricsFootnote']);
        add_action('in_admin_header', [__CLASS__, 'shouldPrintMetricsFootnote']);

        add_action('shutdown', [__CLASS__, 'maybePrintMetricsFootnote'], PHP_INT_MAX);

        if (! static::relayExtensionIsLoaded()) {
            return;
        }

        if (! static::relayCacheIsEnabled()) {
            return;
        }

        try {
            static::$cache = new Table;
            static::$connection = RelayConnector::connectToInstance($config);
        } catch (Throwable $th) {
            static::$error = $th->getMessage();
            error_log("relay-wp-l10n.warning: {$th->getMessage()}");

            return;
        }

        add_action('upgrader_process_complete', [__CLASS__, 'handleTranslationUpdates'], 10, 2);

        if (static::shouldBypassCache()) {
            return;
        }

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
            static::$statsMoNotReadable++;

            return false;
        }

        $hash = substr(base_convert(md5(str_replace(ABSPATH, '', $mofile)), 16, 32), 0, 12);
        $key = "{$domain}-{$locale}-{$hash}-mo";

        if (! $driver->load($domain, $mofile, $locale, $key)) {
            $wp_textdomain_registry->set($domain, $locale, false);
            static::$statsLoadFailed++;

            return false;
        }

        if (isset($l10n[$domain])) {
            $driver->merge_with($l10n[$domain]);
        }

        unset($l10n_unloaded[$domain]);

        $l10n[$domain] = $driver;

        $wp_textdomain_registry->set($domain, $locale, dirname($mofile));

        static::$statsMoLoaded++;

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
            static::$statsJsonNotReadable++;

            return false;
        }

        $driver = new RelayTranslations(static::$config, static::$connection);

        $locale = strstr(substr(basename($file), $domain === 'default' ? 0 : strlen($domain) + 1), '-', true);
        $hash = substr(base_convert(md5(str_replace(ABSPATH, '', $file)), 16, 32), 0, 12);
        $key = "{$domain}-{$locale}-{$hash}-json";

        $translations = $driver->loadJson($file, $handle, $domain, $key);

        static::$statsJsonLoaded++;

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
            static::$statsIsReadableCall++;

            static::$cache->set("readable:{$hash}", $isReadable);
        } else {
            static::$statsIsReadableCached++;
        }

        return $isReadable;
    }

    public static function shouldBypassCache(): bool
    {
        return ! empty($_GET['bypass-relay'])
            || ! empty($_COOKIE['bypass-relay'])
            || ! empty($_SERVER['HTTP_X_BYPASS_RELAY']);
    }

    public static function shouldPrintMetricsFootnote()
    {
        if (! static::$config->footnote) {
            return;
        }

        static::$shouldPrintFootnote = true;
    }

    protected static function relayExtensionIsLoaded(): bool
    {
        if (! extension_loaded('relay')) {
            static::$error = 'Relay extension not loaded in ' . PHP_SAPI . ' environment';
            error_log('relay-wp-l10n.warning: ' . static::$error);

            return false;
        }

        if (version_compare(phpversion('relay'), '0.6.6', '<=')) {
            static::$error = 'Relay version v0.6.7 required, found ' . phpversion('relay');
            error_log('relay-wp-l10n.warning: ' . static::$error);

            return false;
        }

        return true;
    }

    protected static function relayCacheIsEnabled(): bool
    {
        if (Relay::maxMemory() > 0) {
            return true;
        }

        static::$error = 'Relay is in client-only mode';
        error_log('relay-wp-l10n.warning: ' . static::$error);

        return false;
    }

    public static function maybePrintAdminNotice(): void
    {
        if (! static::$error) {
            return;
        }

        printf(
            '<div class="notice notice-warning"><p><b>Relay Localizations</b>: %s.</p></div>',
            esc_html(static::$error)
        );
    }

    public static function maybePrintMetricsFootnote(): void
    {
        if (! static::$shouldPrintFootnote) {
            return;
        }

        if (
            (defined('\WP_CLI') && constant('\WP_CLI')) ||
            (defined('\REST_REQUEST') && constant('\REST_REQUEST')) ||
            (defined('\XMLRPC_REQUEST') && constant('\XMLRPC_REQUEST')) ||
            (defined('\DOING_AJAX') && constant('\DOING_AJAX')) ||
            (defined('\DOING_CRON') && constant('\DOING_CRON')) ||
            (defined('\DOING_AUTOSAVE') && constant('\DOING_AUTOSAVE'))
        ) {
            return;
        }

        if (is_robots() || is_trackback() || wp_is_json_request() || wp_is_jsonp_request()) {
            return;
        }

        $plugin = get_file_data(static::$file, ['Version' => 'Version']);

        printf(
            "\n<!-- plugin=%s version=%s %s -->\n",
            'relay-wp-l10n',
            $plugin['Version'],
            static::$error
                ? "error=" . strtolower(static::$error)
                : static::metrics(),
        );
    }

    protected static function metrics(): string
    {
        $relay = static::$connection->stats();
        $endpointId = static::$connection->endpointId();
        $endpoint = $relay['endpoints'][$endpointId] ?? null;
        $redis = $endpoint['redis']['redis_version'] ?? null;

        $memory = $relay['memory'];
        $memoryThreshold = (int) ini_get('relay.maxmemory_pct') ?: 100;

        $metrics = implode(' ', [
            'metric#mo-cached=%d',
            'metric#json-cached=%d',
            'metric#mo-not-loaded=%d',
            'metric#mo-not-readable=%d',
            'metric#json-not-readable=%d',
            'metric#readable-called=%d',
            'metric#readable-cached=%d',
        ]);

        return sprintf(
            "relay=%s redis=%s db=%d usage=%s/%s;%s bypass=%d {$metrics}",
            phpversion('relay'),
            $redis,
            static::$config->database,
            str_replace(' ', '', size_format($memory['used'])),
            str_replace(' ', '', size_format($memory['total'])),
            "{$memoryThreshold}%",
            static::shouldBypassCache(),
            static::$statsMoLoaded,
            static::$statsJsonLoaded,
            static::$statsLoadFailed,
            static::$statsMoNotReadable,
            static::$statsJsonNotReadable,
            static::$statsIsReadableCall,
            static::$statsIsReadableCached
        );
    }
}
