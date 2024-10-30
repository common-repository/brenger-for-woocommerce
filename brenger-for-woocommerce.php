<?php

/**
 * Plugin Name: Brenger for WooCommerce
 * Description: Adds Brenger as a shipping plugin to WooCommerce.
 * Author: Brenger
 * Author URI: https://brenger.nl
 * Version: 1.0.0
 */

define('BRENGER_WOOCOMMERCE_FILE', __FILE__);
define('BRENGER_WOOCOMMERCE_PATH', dirname(BRENGER_WOOCOMMERCE_FILE));

if ( file_exists(BRENGER_WOOCOMMERCE_PATH . '/vendor/autoload.php')) {
    require_once(BRENGER_WOOCOMMERCE_PATH . '/vendor/autoload.php');
}

function brenger_woocommerce() {
    static $plugin;

    if ( is_object($plugin) ) {
        return $plugin;
    }

    $plugin = new \Brenger\WooCommerce\Plugin();
    $plugin->setup();
    return $plugin;
}

add_action('plugins_loaded', 'brenger_woocommerce');
