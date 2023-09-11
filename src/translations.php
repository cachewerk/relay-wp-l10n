<?php

declare(strict_types=1);

use Relay\Relay;

class RelayTranslations
{
    public array $entries = [];

    public array $headers = [];

    protected int $pluralsCount = 2;

    protected array $pluralCallback;

    protected object $config;

    protected Relay $connection;

    public function __construct(object $config, Relay $connection)
    {
        $this->config = $config;
        $this->connection = $connection;
    }

    public function load($domain, $mofile, $locale, $key): bool
    {
        $data = $this->connection->get($this->id($key));

        if (is_array($data)) {
            $this->headers = $data['headers'];
            $this->entries = $data['entries'];
            $this->parsePluralForms();

            unset($data);

            return true;
        }

        $mo = new MO;

        if (! is_readable($mofile)) {
            return false;
        }

        if (! $mo->import_from_file($mofile)) {
            return false;
        }

        $this->headers = $mo->headers;
        $this->entries = $this->extractEntries($mo);
        $this->parsePluralForms();

        $mo = $this;

        $this->connection->set($this->id($key), [
            'headers' => $mo->headers,
            'entries' => $mo->entries,
        ]);

        return true;
    }

    public function loadJson($file, $handle, $domain, $key)
    {
        $json = $this->connection->get($this->id($key));

        if ($json) {
            return $json;
        }

        if (! is_readable($file)) {
            return false;
        }

		$json = file_get_contents($file);

        $this->connection->set($this->id($key), $json);

        return $json;
    }

    public function flush(): bool
    {
        return $this->connection->flushdb(true);
    }

    public function merge_with($other): void
    {
        foreach ($other->entries as $key => $entry) {
            $this->entries[$key] = is_object($entry) ? get_object_vars($entry) : $entry;
        }
    }

    public function translate($singular, $context = null): string
    {
        $translations = $this->lookupEntry($singular, $context);

		return isset($translations[0]) ? $translations[0] : $singular;
    }

    public function translate_plural($singular, $plural, $count, $context = null): string
    {
        $translations = $this->lookupEntry($singular, $context);

        $index = $this->pluralsCount === 2
            ? ((int) $count === 1 ? 0 : 1)
            : call_user_func($this->pluralCallback, $count);

        if ($index >= 0 && $index < $this->pluralsCount && isset($translations[$index])) {
            return $translations[$index];
        }

        return (int) $count === 1 ? $singular : $plural;
    }

    protected function lookupEntry($singular, $context = null)
    {
        $key = $context ? "{$context}\4{$singular}" : $singular;

        return $this->entries[$key] ?? null;
    }

    protected function id($key): string
    {
        $key = str_replace([':', ' '], '-', (string) $key);
        $prefix = ! empty($this->config->prefix) ? '' : "{$this->config->prefix}:";

        return trim(strtolower("{$prefix}translations:{$key}"), ':');
    }

    protected function extractEntries(MO $mo): array
    {
        return array_combine(
            array_map(function (Translation_Entry $entry) {
                return $entry->key();
            }, $mo->entries),
            array_map(function (Translation_Entry $entry) {
                return $entry->translations;
            }, $mo->entries)
        );
    }

    protected function parsePluralForms(): void
    {
        $helper = new MO;

        if (empty($this->headers['Plural-Forms'])) {
            return;
        }

        [$count, $expression] = $helper->nplurals_and_expression_from_header(
            $this->headers['Plural-Forms']
        );

        $this->pluralsCount = $count;
        $this->pluralCallback = $helper->make_plural_form_function($count, $expression);
    }
}
