<?php

namespace Brenger\WooCommerce\Orders;

use Automattic\WooCommerce\Admin\Overrides\Order as OverrideOrder;
use Brenger\WooCommerce\AdminNotices;
use Brenger\WooCommerce\API\CreateShipment;
use BrengerClient\ApiException;
use WC_Order;
use WC_Order_Item_Product;
use WC_Product;

class Order
{

    /**
     * Holds the status of the creation of the transport with the Brenger API.
     *
     * @var string
     */
    private $transport_created_status = '';

    /**
     * Holds the error code of the creation of the transport.
     *
     * @var string
     */
    private $transport_error_code = '';

    /**
     * Register WordPress hooks used in this class.
     */
    public function registerHooks(): void
    {
        add_action('init', array( $this, 'transportStatusMeta' ));
        add_filter('woocommerce_order_actions', array( $this, 'addOrderActions' ));
        add_action('woocommerce_admin_order_data_after_shipping_address', array( $this, 'transportStatus' ), 10, 1);
        add_action('woocommerce_new_order', array( $this, 'setNewOrderTransportStatus' ), 10, 2);
        add_action('woocommerce_process_shop_order_meta', array( $this, 'maybeCreateTransport'), 10, 1);
        add_action('woocommerce_process_shop_order_meta', array( $this, 'maybeCreateTransportWithOptions'), 10, 1);
        add_action('admin_notices', array( AdminNotices::class, 'maybeShowAdminNotices' ), 10);
    }

    /**
     * Set a meta value with default for the Brenger transport status.
     */
    public function transportStatusMeta(): void
    {
        register_meta(
            'post',
            '_brenger_transport_status',
            array(
                'object_subtype' => 'shop_order',
                'type'           => 'string',
                'single'         => true,
                'default'        => 'not_created',
                'show_in_rest'   => true,
            )
        );
    }

    /**
     * @param array $actions An array containing current registered actions for this order.
     *
     * @return array
     */
    public function addOrderActions($actions): array
    {
        global $theorder;

        if ($this->hasBrengerShippingClass($theorder)) {
            $actions['create_brenger_transport'] = __('Create Brenger transport', 'brenger-for-woocommerce');
            $actions['create_brenger_transport_with_options'] = __('Create Brenger transport with options',
                'brenger-for-woocommerce');
        }

        return $actions;
    }

    /**
     * Show the current transport status of the currently displayed order.
     *
     * @param OverrideOrder $order
     */
    public function transportStatus($order): void
    {
        if (!$this->hasBrengerShippingClass($order)) {
            return;
        }

        $status = new TransportStatus($order);
        $tracking_url = '';
        $transport_id = '';
        $shipping_date = '';
        $shipped_by = '';

        if ($status->getTransportStatus() !== 'not-created') {
            $tracking_url  = get_post_meta($order->get_id(), '_brenger_transport_tracking_url', true);
            $transport_id  = get_post_meta($order->get_id(), '_brenger_shipment_id', true);
            $shipping_date = get_post_meta($order->get_id(), '_brenger_transport_shipping_date', true);
            $shipped_by    = get_post_meta($order->get_id(), '_brenger_transport_shipped_by', true);
        }

        echo '<h3>' . esc_html__('Brenger transport status', 'brenger-for-woocommerce') . '</h3>';
        echo '<p><mark class="order-status status-' . esc_attr($status->getStatusClass()) . '"><span>';
        echo esc_html($status->getStatusMessage()) . '</span></mark></p>';

        if ($tracking_url) {
            echo '<p>';
            echo '<strong>' . esc_html__('Tracking URL', 'brenger-for-woocommerce') . '</strong><br>';
            printf('<a href="%1$s" target="_blank">%2$s</a>', esc_url($tracking_url), esc_url($tracking_url));
            echo '</p>';
        }

        if ($transport_id) {
            echo '<p>';
            echo '<strong>' . esc_html__('Transport ID', 'brenger-for-woocommerce') . '</strong><br>';
            echo esc_html($transport_id);
            echo '</p>';
        }

        if ($shipping_date) {
            echo '<p>';
            echo '<strong>' . esc_html__('Shipping date', 'brenger-for-woocommerce') . '</strong><br>';
            echo esc_html($shipping_date);
            echo '</p>';
        }

        if ($shipped_by) {
            echo '<p>';
            echo '<strong>' . esc_html__('Shipped by', 'brenger-for-woocommerce') . '</strong><br>';
            echo esc_html($this->getShippedByOutput($shipped_by));
            echo '</p>';
        }
    }

    /**
     * Returns a string of all the available shipping/transport details of the
     * 'shipped by' values of a registered transport.
     */
    private function getShippedByOutput(string $json_data): string
    {
        $shipped_by_data = json_decode($json_data);

        $output_array = array();

        foreach (array( 'name', 'phone', 'vehicle' ) as $key) {
            if (! isset($shipped_by_data->$key)) {
                continue;
            }

            $output_array[] = $shipped_by_data->$key;
        }

        return implode(', ', $output_array);
    }

    /**
     * Check whether the displayed order has Brenger as shipping method.
     */
    public function hasBrengerShippingMethod(WC_Order $order): bool
    {
        $shipping_methods = $order->get_shipping_methods();

        foreach ($shipping_methods as $shipping_method) {
            if ($shipping_method->get_method_id() === 'brenger') {
                return true;
            }
        }

        return false;
    }

    /**
     * Check whether the displayed order has at least one item with supported by Brenger shipping class.
     *
     * @param WC_Order $order
     * @return bool
     */
    public function hasBrengerShippingClass(WC_Order $order): bool
    {
        $shipping_methods = WC()->shipping()->get_shipping_methods();
        $brenger_shipping_method = array_key_exists('brenger', $shipping_methods)
            ? $shipping_methods['brenger']
            : false;

        if (!$brenger_shipping_method) {
            return false;
        }

        if (!is_array($brenger_shipping_method->settings['shipping_classes'])) {
			return false;
		}

        $shipping_classes = WC()->shipping()->get_shipping_classes();
        $selected_classes = array_map('intval', $brenger_shipping_method->settings['shipping_classes']);

        $matches = array_filter(array_map(function ($shipping_class) use ($selected_classes) {
            if (in_array($shipping_class->term_id, $selected_classes, true)) {
                return $shipping_class->slug;
            }
        }, $shipping_classes));

        /** @var WC_Order_Item_Product $item */
        $items = array_filter($order->get_items(), function ($item) use ($matches) {
            if (! $item instanceof WC_Order_Item_Product) {
                return false;
            }

            /** @var WC_Product $product */
            $product = $item->get_product();

            return in_array($product->get_shipping_class(), $matches, true);
        });

        return count($items) > 0;
    }

    /**
     * Set a default transport status for orders with Brenger as shipping method.
     */
    public function setNewOrderTransportStatus(int $order_id, WC_Order $order): void
    {
        if ($this->hasBrengerShippingMethod($order)) {
            add_post_meta($order_id, '_brenger_transport_status', 'not_created');
        }
    }

    /**
     * Upon saving an order check whether a transport should be created.
     * This functionality is triggered when Create Brenger Transport is selected under
     * WooCommerce Order Actions.
     * @param int $order_id
     */
    public function maybeCreateTransport($order_id): void
    {
        if (empty($_POST['wc_order_action'])) {
            return;
        }

        if (sanitize_text_field($_POST['wc_order_action']) !== 'create_brenger_transport') {
            return;
        }

        $transport_status = new TransportStatus(new OverrideOrder($order_id));

        if ($transport_status->getTransportStatus() !== 'not_created') {
            return;
        }

        $createShipment = new CreateShipment();
        try {
            $createShipment->createFromId($order_id);
            $this->transport_created_status = 'success';
        } catch (ApiException $e) {
            $this->transport_created_status = 'failed';
            $response_body = $e->getResponseBody();

            if (! is_string($response_body)) {
                exit;
            }

            $error_code = AdminNotices::getSupportedCodeFromResponse(json_decode($response_body, true)) ?: '';
            $this->transport_error_code = $error_code;
        }

        add_filter('redirect_post_location', array( $this, 'addQueryVar' ), 99);
    }

    /**
     * Upon saving an order check whether a create transport with options page should be displayed.
     * This functionality is triggered when Create Brenger Transport With Options is selected under
     * WooCommerce Order Actions.
     *
     * @param int $order_id
     */
    public function maybeCreateTransportWithOptions($order_id): void
    {
        if (empty($_POST['wc_order_action'])) {
            return;
        }

        if (sanitize_text_field($_POST['wc_order_action']) !== 'create_brenger_transport_with_options') {
            return;
        }

        $transport_status = new TransportStatus(new OverrideOrder($order_id));

        if ($transport_status->getTransportStatus() !== 'not_created') {
            return;
        }

        add_filter('redirect_post_location', array( $this, 'showShippingOptionsPage' ), 99, 2);
    }

    /**
     * Adds a status to the location from which we can derive if a transport was succesfully created in
     * order to show a neat admin notice.
     *
     * @param string $location The redirect location after saving an order.
     *
     * @return string The new location to redirect to, including the transport creation status.
     */
    public function addQueryVar($location)
    {
        remove_filter('redirect_post_location', array( $this, 'addQueryVar' ), 99);
        $arguments = array();
        if (! empty($this->transport_error_code)) {
            $arguments[AdminNotices::PARAM_CODE] = $this->transport_error_code;
        }
        return add_query_arg(AdminNotices::getArgs($this->transport_created_status, $arguments), $location);
    }

    /**
     * Redirect to transport options page for the current order.
     *
     *
     * @param string $location The redirect location after saving an order.
     * @param int $order_id The order ID.
     *
     * @return string The new location to redirect to.
     */
    public function showShippingOptionsPage($location, $order_id): string
    {
        remove_filter('redirect_post_location', array( $this, 'showShippingOptionsPage' ), 99);

        return add_query_arg([
            'page' => 'brenger-transport-with-options',
            'order_id' => $order_id
        ], admin_url());
    }
}
