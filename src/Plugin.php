<?php

namespace Brenger\WooCommerce;

use Brenger\WooCommerce\Actions\TransportStatus;
use Brenger\WooCommerce\Orders\Order;
use Brenger\WooCommerce\Orders\TransportsPage;
use Brenger\WooCommerce\Orders\TransportWithOptionsPage;
use Brenger\WooCommerce\Product\DimensionFields;
use Brenger\WooCommerce\Shipping\BrengerMethod;
use Brenger\WooCommerce\Shipping\ShippingPackages;

class Plugin
{

    public function setup(): void
    {
        $requirements = new Requirements();

        if (! $requirements->pass()) {
            add_action('admin_notices', array( $this, 'requirementsAdminNotice'));
            return;
        }

        add_filter('woocommerce_shipping_methods', array( $this, 'registerShippingMethod'));

        // init shipping methods
        WC()->shipping()->get_shipping_methods();

        $transport_page = new TransportsPage();
        $transport_page->registerHooks();

        $transport_with_options_page = new TransportWithOptionsPage();
        $transport_with_options_page->registerHooks();

        $product_dimension_fields = new DimensionFields();
        $product_dimension_fields->registerHooks();

        $orders = new Order();
        $orders->registerHooks();

        $transport_status_action = new TransportStatus();
        $transport_status_action->registerHooks();
    }

    public function registerShippingMethod(array $methods): array
    {
        $methods['brenger'] = BrengerMethod::class;
        return $methods;
    }

    public function requirementsAdminNotice(): void
    {
        $class   = 'notice notice-error';
        $message = __('Brenger requires WooCommerce 5.2 and higher to be installed.', 'brenger-for-woocommerce');

        printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
    }
}
