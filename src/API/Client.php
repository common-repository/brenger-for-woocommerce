<?php

namespace Brenger\WooCommerce\API;

use Automattic\WooCommerce\Admin\Overrides\Order;
use Brenger\WooCommerce\Logger;
use Brenger\WooCommerce\Transformers\OrderToShipment;
use BrengerClient\Api\DefaultApi;
use BrengerClient\ApiException;
use BrengerClient\Configuration;
use BrengerClient\Model\CreatedShipment;
use Exception;

class Client
{
    /** @var string */
    private $api_key;

    public function __construct(string $api_key)
    {
        $this->api_key = $api_key;
    }

    /**
     * Get the default API instance.
     */
    private function getApiInstance(): DefaultApi
    {
        $shipping_methods = WC()->shipping->get_shipping_methods();
        $brenger_method = $shipping_methods['brenger'];

        $config = Configuration::getDefaultConfiguration()->setApiKey('X-AUTH-TOKEN', $this->api_key);
        $config->setHost($brenger_method->settings['api_url']);

        return new DefaultApi(
            null,
            $config
        );
    }

    /**
     * Converts a given order id into a shipment that can be passed to the Brenger
     * api.
     *
     * @param Order WooCommerce order to transform
     *
     * @throws Exception
     * @throws ApiException
     */
    public function createShipmentFromOrderId(int $order_id): CreatedShipment
    {
        $order = wc_get_order($order_id);

        if (! $order instanceof Order) {
            throw new Exception('Invalid order id passed');
        }

        $transformer = new OrderToShipment();
        $shipment = $transformer->createShipment($order);

        $apiInstance = $this->getApiInstance();

        try {
            $result = $apiInstance->shipmentsPost($shipment);

            if (! $result instanceof CreatedShipment) {
                throw new Exception('Invalid order id passed');
            }

            return $result;
        } catch (ApiException $e) {
            Logger::apiError($e);
            throw $e;
        }
        
    }

    public function createShipmentFromOrderIdAndCustomInput(int $order_id, array $items, array $delivery_options): CreatedShipment
    {
        $order = wc_get_order($order_id);

        if (! $order instanceof Order) {
            throw new Exception('Invalid order id passed');
        }

        $transformer = new OrderToShipment();
        $shipment = $transformer->createShipment($order);
        $shipment = $transformer->overrideShipmentItemSets($shipment, $items);        
        $shipment = $transformer->overrideShipmentDeliveryOptions($shipment, $delivery_options);        
        
        $apiInstance = $this->getApiInstance();

        try {
            $result = $apiInstance->shipmentsPost($shipment);

            if (! $result instanceof CreatedShipment) {
                throw new Exception('Invalid order id passed');
            }

            return $result;
        } catch (ApiException $e) {
            Logger::apiError($e);
            throw $e;
        }
    }


    /**
     * Get the created shipment from Brenger API.
     *
     * @param string $uuid The unique ID of a transport as registered with Brenger.
     *
     * @throws ApiException
     */
    public function getCreatedShipment(string $uuid): CreatedShipment
    {
        if (empty($uuid)) {
            throw new Exception('invalid order id passed');
        }

        $apiInstance = $this->getApiInstance();
        try {
            $result = $apiInstance->shipmentsUuidGet($uuid);

            if (! $result instanceof CreatedShipment) {
                throw new Exception('Invalid order id passed');
            }

            return $result;
        } catch (ApiException $e) {
            throw $e;
        }
    }
}
