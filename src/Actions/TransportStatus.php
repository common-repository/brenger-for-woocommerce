<?php

namespace Brenger\WooCommerce\Actions;

use Brenger\WooCommerce\API\CreatedShipment;
use Automattic\WooCommerce\Admin\Overrides\Order as OverrideOrder;
use Brenger\WooCommerce\Orders\TransportStatus as OrderTransportStatus;

/**
 *
 */
class TransportStatus
{

    /**
     * Register WordPress hooks used in this class.
     */
    public function registerHooks(): void
    {
        add_action('brenger_get_order_transport_status', [ $this, 'maybeUpdateTransportStatus' ]);
    }

    /**
     * Check and maybe update the transport status according to the status in Brenger API.
     * @throws \Exception
     */
    public function maybeUpdateTransportStatus(int $order_id): void
    {
        $order  = new OverrideOrder($order_id);

        $uuid = get_post_meta($order->get_id(), '_brenger_shipment_id', true);
        $shipment = new CreatedShipment($uuid);

        $brenger_state = $shipment->created_shipment->getState();
        $order_transport_status = new OrderTransportStatus($order);

        $shipping_date = $shipment->created_shipment->getShippingDate();

        if (! empty($shipping_date)) {
            update_post_meta($order_id, '_brenger_transport_shipping_date', $shipping_date);
        }

        $shipped_by = $shipment->created_shipment->getShippedBy();

        if (! empty($shipped_by)) {
            update_post_meta($order_id, '_brenger_transport_shipped_by', $shipped_by);
        }

        if (! $brenger_state) {
            return;
        }

        // If transport state is in the following array, this is considered a
        // 'final' state and this specific transport does not need to be
        // checked anymore. A 'final' state doesn't change anymore.
        $stop_check_statuses = array('not_created', 'delivered', 'cancelled');

        if (in_array($brenger_state, $stop_check_statuses, true)) {
            as_unschedule_all_actions('brenger_get_orders_transport_status', array('order_id' => $order_id));
        }

        // If the transport status is unchanged, don't update it.
        if ($brenger_state === $order_transport_status->getTransportStatus()) {
            return;
        }

        $order_transport_status->updateStatus($brenger_state);
    }
}
