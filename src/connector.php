<?php

declare(strict_types=1);

use Relay\Relay;
use Relay\Exception as RelayException;

class RelayConnector
{
    public static function connectToInstance(object $config): Relay
    {
        $client = new Relay;

        $persistent = $config->persistent;
        $persistentId = '';

        $host = $config->host;

        if ($config->scheme) {
            $host = "{$config->scheme}://{$config->host}";
        }

        $host = \str_replace('unix://', '', $host);

        $method = $persistent ? 'pconnect' : 'connect';

        $context = [];

        if ($config->tls_options) {
            $context['stream'] = $config->tls_options;
        }

        $arguments = [
            $host,
            (int) ($config->port ?? 0),
            (float) $config->timeout,
            $persistentId,
            (int) $config->retry_interval,
            (float) $config->read_timeout,
            $context,
        ];

        $retries = 0;

        CONNECTION_RETRY: {
            $delay = self::nextDelay($config, $retries);

            try {
                $client->{$method}(...$arguments);
            } catch (RelayException $exception) {
                if (++$retries >= $config->retries) {
                    throw $exception;
                }

                \usleep($delay * 1000);
                goto CONNECTION_RETRY;
            }
        }

        if ($config->username && $config->password) {
            $client->auth([$config->username, $config->password]);
        } elseif ($config->password) {
            $client->auth($config->password);
        }

        if ($config->database) {
            if (! $client->select($config->database)) {
                error_log("relay-wp-l10n.warning: The Redis database index `{$config->database}` does not exist.");
            }
        }

        $client->setOption(Relay::OPT_PHPREDIS_COMPATIBILITY, false);
        $client->setOption(Relay::OPT_SERIALIZER, Relay::SERIALIZER_IGBINARY);

        if ($config->retries) {
            $client->setOption(Relay::OPT_MAX_RETRIES, $config->retries);
        }

        if ($config->backoff === 'smart') {
            $client->setOption(Relay::OPT_BACKOFF_ALGORITHM, Relay::BACKOFF_ALGORITHM_DECORRELATED_JITTER);
            $client->setOption(Relay::OPT_BACKOFF_BASE, 500);
            $client->setOption(Relay::OPT_BACKOFF_CAP, 750);
        }

        return $client;
    }

    public static function nextDelay(object $config, int $retries): int
    {
        if ($config->backoff === 'none') {
            return $retries;
        }

        $retryInterval = $config->retry_interval;
        $jitter = $retryInterval * 0.1;

        return $retries * \mt_rand(
            (int) \floor($retryInterval - $jitter),
            (int) \ceil($retryInterval + $jitter)
        );
    }
}
