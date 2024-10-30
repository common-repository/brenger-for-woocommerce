<?php

namespace Brenger\WooCommerce\API;

use BrengerClient\ApiException;

class CreateShipment
{
    /**
     * Create a shipment/transport in the Brenger API, for the order based
     * on the provided id of the order.
     */
    public function createFromId(int $order_id): bool
    {
        $shipping_methods = WC()->shipping->get_shipping_methods();
        $brenger_method = $shipping_methods['brenger'];
        $client = new Client($brenger_method->settings['api_key']);

        try {
            $result = $client->createShipmentFromOrderId($order_id);

            update_post_meta($order_id, '_brenger_shipment_id', $result->getId());
            update_post_meta($order_id, '_brenger_transport_tracking_url', $result->getTrackingUrl());
            update_post_meta($order_id, '_brenger_transport_status', $result->getState());
            update_post_meta($order_id, '_brenger_transport_shipping_date', $result->getShippingDate());
            update_post_meta($order_id, '_brenger_transport_shipped_by', $result->getShippedBy());

            $this->registerTransportStatusAction($order_id);
            return true;
        } catch (ApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function createFromIdAndCustomInput(int $order_id, array $raw_items, array $delivery_items): bool
    {
        $shipping_methods = WC()->shipping->get_shipping_methods();
        $brenger_method = $shipping_methods['brenger'];
        $client = new Client($brenger_method->settings['api_key']);

        try {
            $result = $client->createShipmentFromOrderIdAndCustomInput($order_id, $raw_items, $delivery_items);

            update_post_meta($order_id, '_brenger_shipment_id', $result->getId());
            update_post_meta($order_id, '_brenger_transport_tracking_url', $result->getTrackingUrl());
            update_post_meta($order_id, '_brenger_transport_status', $result->getState());
            update_post_meta($order_id, '_brenger_transport_shipping_date', $result->getShippingDate());
            update_post_meta($order_id, '_brenger_transport_shipped_by', $result->getShippedBy());

            $this->registerTransportStatusAction($order_id);
            return true;
        } catch (ApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function registerTransportStatusAction(int $order_id): void
    {
        if (
            false !== as_next_scheduled_action(
                'brenger_get_order_transport_status',
                array( 'order_id' => $order_id )
            )
        ) {
            return;
        }

        as_schedule_recurring_action(
            strtotime('+1 hour'),
            HOUR_IN_SECONDS,
            'brenger_get_order_transport_status',
            array( 'order_id' => $order_id ),
            'Brenger'
        );
    }
}
