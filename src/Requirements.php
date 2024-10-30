<?php

namespace Brenger\WooCommerce;

class Requirements
{
    public function pass(): bool
    {
        if (! function_exists('WC')) {
            return false;
        }

        if (version_compare(WC()->version, '5.2', '<')) {
            return false;
        }

        return true;
    }
}
