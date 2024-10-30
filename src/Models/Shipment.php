<?php

namespace Brenger\WooCommerce\Models;

use stdClass;

class Shipment
{
    /** @var stdClass */
    public $data;

    public function __construct()
    {
        $this->data = new stdClass();
    }

    public static function createFromResponseClass(stdClass $object): Shipment
    {
        $instance       = new self();
        $instance->data = $object;
        return $instance;
    }

    public function getStatus(): string
    {
        return $this->data->state;
    }
}
