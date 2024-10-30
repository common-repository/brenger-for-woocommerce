<?php

namespace Brenger\WooCommerce;

use BrengerClient\ApiException;

class Logger
{
    public const SOURCE_API_ERROR    = 'brenger-api-error';
    public const SOURCE_PLUGIN_ERROR = 'brenger-plugin-error';
    /**
     * Log an API error with data to the WooCommerce logs. Messages are
     * truncated by Guzzle, so we remove the response body from it and
     * log it separately.
     */
    public static function apiError(ApiException $e): void
    {
        $message  = $e->getMessage();

        $short_message = strstr($message, 'response:', true) ?: $message;

        self::warning($short_message, self::SOURCE_API_ERROR);

        $response = $e->getResponseBody();

        if (! is_string($response)) {
            return;
        }

        self::warning($response, self::SOURCE_API_ERROR);
    }

    /**
     * Log a warning under the given source.
     */
    public static function warning(string $message, string $source): void
    {
        $logger = wc_get_logger();
        $logger->warning($message, array( 'source' => $source ));
    }
}
