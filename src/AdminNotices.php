<?php

namespace Brenger\WooCommerce;

use BrengerClient\ApiException;

class AdminNotices
{
    public const SUPPORTED_ERRORS = array(
        'validation_error',
        'delivery_address_lookup_error'
    );

    public const SUPPORTED_VALIDATION_ERRORS = array(
        'pickup',
        'delivery',
        'item_sets'
    );

    public const PARAM_STATUS = 'brenger-status';
    public const PARAM_CODE   = 'code';

    /**
     * Creates an associative array of arguments that can be used to create a
     * new redirect url.
     */
    public static function getArgs(string $status, array $args): array
    {
        return array_merge(
            array( self::PARAM_STATUS => $status ),
            $args
        );
    }

    /**
     * Creates an associative array of arguments that can be used to create a
     * new redirect url, based of an ApiException.
     */
    public static function getArgsFromApiException(ApiException $e, array $args): array
    {
        $response_body = $e->getResponseBody();
        $error_code    = array();

        if (is_string($response_body)) {
            $supported_error = self::getSupportedCodeFromResponse(json_decode($response_body, true));

            if (is_string($supported_error)) {
                $error_code = array(self::PARAM_CODE => $supported_error);
            }
        }

        return self::getArgs(
            'failed',
            array_merge($args, $error_code)
        );
    }

    /**
     * Check if the response contains an error code that has a supported
     * message, and return it.
     */
    public static function getSupportedCodeFromResponse(array $response): ?string
    {
        foreach (self::SUPPORTED_VALIDATION_ERRORS as $validation_error) {
            if (isset($response['validation_errors'][ $validation_error ])) {
                return json_encode($response['validation_errors'][ $validation_error ]);
            }
        }

        foreach (self::SUPPORTED_ERRORS as $error) {
            if ($response['error_code'] === $error) {
                return $error;
            }
        }

        return null;
    }

    /**
     * Handler for showing admin notices, enqueue this with the admin_notices
     * action on any page that may need it.
     */
    public static function maybeShowAdminNotices(): void
    {
        $status = filter_input(INPUT_GET, self::PARAM_STATUS);

        switch ($status) {
            case 'success':
                $class = 'notice notice-success';
                $message = __('The transport has been created.', 'brenger-for-woocommerce');
                break;
            case 'failed':
                $class = 'notice notice-error';
                $message = __('An error has occurred when creating the transport.', 'brenger-for-woocommerce');

                $code = filter_input(INPUT_GET, 'code');

                if (in_array($code, self::SUPPORTED_ERRORS)) {
                    switch ($code) {
                        case 'pickup':
                            $message .= ' ' . sprintf(
                                esc_html__('Pickup details have not been entered correctly in ' .
                                '<a href="%s">the Brenger settings</a>.', 'brenger-for-woocommerce'),
                                admin_url('admin.php?page=wc-settings&tab=shipping&section=brenger')
                            );
                            break;
                        case 'delivery':
                            $message .= ' ' . __(
                                'Delivery details have not been entered correctly in the order.',
                                'brenger-for-woocommerce'
                            );
                            break;
                        case 'item_sets':
                            $message .= ' ' . __(
                                'The products in this order have not been correctly setup. Make ' .
                                ' sure they all have Brenger specific dimensions configured.',
                                'brenger-for-woocommerce'
                            );
                            break;
                        case 'delivery_address_lookup_error':
                            $message .= ' ' . __(
                                'The given delivery address is not a valid address.',
                                'brenger-for-woocommerce'
                            );
                            break;
                        default:
                            break;
                    }
                } else {
                  $message .= ' ' . __(
                      'Error message: '.$code,
                      'brenger-for-woocommerce'
                  );
                }
                break;
            default:
                return;
        }

        printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), $message);
    }
}
