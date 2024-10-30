<?php

namespace Brenger\WooCommerce\Orders;

use Automattic\WooCommerce\Admin\Overrides\Order as OverrideOrder;

/**
 * This class handles the transport statuses that go with Brenger.
 */
class TransportStatus
{
    /**
     * A list of possible statussen for Brenger transports. This list also contains the CSS class and message.
     * @var array[]
     */
    private $statuses;

    /**
     * @var OverrideOrder
     */
    private $order;

    /**
     * The current Brenger transport status of the order.
     *
     * @var mixed
     */
    private $order_status;

    /**
     * OrderStatus constructor.
     */
    public function __construct(OverrideOrder $order)
    {
        $this->order        = $order;
        $this->order_status = get_post_meta($this->order->get_id(), '_brenger_transport_status', true);
        $this->statuses     = self::getStatuses();
    }

    /**
     * @return mixed
     */
    public function getTransportStatus(): string
    {
        return $this->order_status;
    }

    /**
     * Get the message for the order status.
     *
     * @return mixed
     */
    public function getStatusMessage(): string
    {
        return $this->statuses[ $this->order_status ]['message'];
    }

    /**
     * Get the CSS class for the status of this order. This corresponds to CSS classes used by WooCommerce.
     *
     * @return mixed
     */
    public function getStatusClass(): string
    {
        return $this->statuses[ $this->order_status ]['class'];
    }

    /**
     * Method to update the status in the database
     *
     * @param string $new_status The status to update to.
     */
    public function updateStatus(string $new_status): void
    {
        if (! array_key_exists($new_status, $this->statuses)) {
            throw new \Exception(sprintf(
                esc_html__(
                    'The status code %s is invalid. Please use a valid status code',
                    'brenger-for-woocommerce'
                ),
                $new_status
            ));
        }

        update_post_meta($this->order->get_id(), '_brenger_transport_status', $new_status);
    }

    /**
     * Get the current transport status of an order.
     */
    public function getCurrentTransportStatus(): string
    {
        return get_post_meta($this->order->get_id(), '_brenger_transport_status', true);
    }

    /**
     * Get an array of the available Brenger transport statuses.
     */
    public static function getStatuses(): array
    {
        return array(
            'not_created' => array(
                'class'   => 'on-hold',
                'message' => esc_html__('Not created', 'brenger-for-woocommerce'),
            ),
            'ready_for_pickup' => array(
                'class'   => 'processing',
                'message' => esc_html__('Ready for pickup', 'brenger-for-woocommerce'),
            ),
            'picked_up' => array(
                'class'   => 'processing',
                'message' => esc_html__('Picked up', 'brenger-for-woocommerce'),
            ),
            'delivered' => array(
                'class'   => 'completed',
                'message' => esc_html__('Delivered', 'brenger-for-woocommerce'),
            ),
            'cancelled' => array(
                'class'   => 'cancelled',
                'message' => esc_html__('Cancelled', 'brenger-for-woocommerce'),
            ),
            'failed_to_pickup' => array(
                'class'   => 'cancelled',
                'message' => esc_html__('Failed to pickup', 'brenger-for-woocommerce'),
            ),
            'failed_to_deliver' => array(
                'class'   => 'cancelled',
                'message' => esc_html__('Failed to deliver', 'brenger-for-woocommerce'),
            ),
            'not_available' => array(
                'class'   => 'cancelled',
                'message' => esc_html__('Not available', 'brenger-for-woocommerce'),
            ),
        );
    }
}
