<?php

namespace Brenger\WooCommerce\API;

use Exception;

/**
 * Get a created shipment from the Brenger API.
 */
class CreatedShipment
{

    /**
     * @var string the unique identifying number of the shipment.
     */
    private $uuid;

    /**
     * @var \BrengerClient\Model\CreatedShipment
     */
    public $created_shipment;

    public function __construct(string $uuid)
    {
        $this->uuid = $uuid;
        $this->setCreatedShipment();
    }

    /**
     * Set the created shipment in property.
     * @throws Exception
     */
    private function setCreatedShipment(): void
    {
        $shipping_methods = WC()->shipping->get_shipping_methods();
        $brenger_method = $shipping_methods['brenger'];
        $client = new Client($brenger_method->settings['api_key']);
        $this->created_shipment = $client->getCreatedShipment($this->uuid);
    }
}
