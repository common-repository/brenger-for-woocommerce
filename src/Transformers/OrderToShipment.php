<?php

namespace Brenger\WooCommerce\Transformers;

use Automattic\WooCommerce\Admin\Overrides\Order;
use Brenger\WooCommerce\Exceptions\NoBrengerMethodInShipping;
use BrengerClient\Model\Address;
use BrengerClient\Model\Contact;
use BrengerClient\Model\Details;
use BrengerClient\Model\Item;
use BrengerClient\Model\ItemSetPart;
use BrengerClient\Model\Shipment;
use BrengerClient\Model\ShipmentPickup;
use WC_Order_Item_Product;
use WC_Order_Item_Shipping;
use WC_Product;
use WC_Shipping_Method;

class OrderToShipment
{
    /** @var WC_Shipping_Method|null */
    private $shipping_method;

    /**
     * Creates a shipment model class that can be used in the API call to
     * register the shipment and create a transport, based on an order.
     */
    public function createShipment(Order $order): Shipment
    {
        $shipping_methods = WC()->shipping->get_shipping_methods();
        $method = $shipping_methods['brenger'];
        $this->shipping_method = $method;


        $pickup = new ShipmentPickup([
            'contact' => new Contact([
                'first_name' => $method->settings['first_name'],
                'last_name' => $method->settings['last_name'],
                'phone' => $method->settings['phone'],
                'email' => $method->settings['email'],
            ]),
            'address' => new Address([
                'name' => $method->settings['first_name'] . ' ' . $method->settings['last_name'],
                'line1' => $method->settings['address'],
                'line2' => $method->settings['address_line_2'],
                'postal_code' => $method->settings['postal_code'],
                'locality' => $method->settings['locality'],
                'administrative_area' => $method->settings['province'],
                'country_code' => $method->settings['country'],
            ]),
            'details' => new Details([
                'situation' => $method->settings['situation'],
                'floor_level' => $method->settings['floor'],
                'elevator' => $method->settings['elevator'] === 'yes',
                'instruction' => $method->settings['instructions'],
            ])
        ]);

        $delivery = new ShipmentPickup([
            'contact' => new Contact([
                'first_name' => $order->get_shipping_first_name(),
                'last_name' => $order->get_shipping_last_name(),
                'phone' => $order->get_billing_phone(),
                'email' => $order->get_billing_email(),
            ]),
            'address' => new Address([
                'name' => $order->get_formatted_shipping_full_name(),
                'line1' => $order->get_shipping_address_1(),
                'line2' => $order->get_shipping_address_2(),
                'postal_code' => $order->get_shipping_postcode(),
                'locality' => $order->get_shipping_city(),
                'administrative_area' => $order->get_shipping_state(),
                'country_code' => $order->get_shipping_country(),
            ]),
            'details' => new Details([
                'situation' => 'home',
                'floor_level' => 0,
                'elevator' => false,
                'instruction' => '...',
            ])
        ]);

        $items = [];
        $item_titles = [];
        foreach($this->prepareLineItems($order) as $line_item){
            $items[] = new Item([
                        'count' => $line_item['count'],
                        'title' => $line_item['title'],
                        'length' => $line_item['length'],
                        'width' => $line_item['width'],
                        'height' => $line_item['height'],
                        'weight' => $line_item['weight'],
                        ]
                    );
            $item_titles[] = $line_item['count'].'x '.$line_item['title'];
        }

        $item_sets = new ItemSetPart([
            'title' => implode(', ', $item_titles),
            'client_reference' => get_bloginfo('name').' - #'.$order->get_id(),
            'items' => $items
        ]);

        return new Shipment([
            'item_sets' => array($item_sets),
            'pickup' => $pickup,
            'delivery' => $delivery,
        ]);
    }


    public static function prepareLineItems(Order $order): array
    {
        $line_items = $order->get_items();

        $items = [];

        /** @var WC_Order_Item_Product $product */
        foreach ($line_items as $line_item) {
            $product = $line_item->get_product();
            $product_id = $line_item->get_product_id();
            $variation_id = $line_item->get_variation_id();        
            

            if (! $product instanceof WC_Product) {
                continue;
            }
            $items[] = [
                'id' => $variation_id ?: $product_id,
                'line_item' => $line_item,
                'product' => $product,
                'variation_id' => $variation_id,
                'product_id' => $product_id,                
                'title' => $product->get_name(),
                'length' => get_post_meta($variation_id ?: $product_id, 'brenger_length', true) ?:
                    $product->get_length(),
                'height' => get_post_meta($variation_id ?: $product_id, 'brenger_height', true) ?:
                    $product->get_height(),
                'width' => get_post_meta($variation_id ?: $product_id, 'brenger_width', true) ?:
                    $product->get_width(),
                'count' => $line_item->get_quantity(),
                'weight' => get_post_meta($variation_id ?: $product_id, 'brenger_weight', true) ?:
                    $product->get_weight(),
                
            ];                                        
        }
        return $items;
    }

    /**
     * Get all the products from an order that have a valid Brenger shipping
     * class, based on the shipping method settings.
     */
    private function getBrengerShippingProducts(Order $order): array
    {
        if (! $this->shipping_method instanceof WC_Shipping_Method) {
            return array();
        }

        if (
            ! isset($this->shipping_method->settings['shipping_classes'])
            || ! is_array($this->shipping_method->settings['shipping_classes'])
        ) {
            return array();
        }

        /** @var WC_Order_Item_Product $item */
        return array_filter($order->get_items(), function ($item) {
            if (! $item instanceof WC_Order_Item_Product) {
                return false;
            }

            $selected_classes = array_map('intval', $this->shipping_method->settings['shipping_classes']);
            $shipping_classes = WC()->shipping()->get_shipping_classes();

            $matches = array_filter(array_map(function ($shipping_class) use ($selected_classes) {
                if (in_array($shipping_class->term_id, $selected_classes, true)) {
                    return $shipping_class->slug;
                }
            }, $shipping_classes));

            /** @var WC_Product $product */
            $product = $item->get_product();

            return in_array($product->get_shipping_class(), $matches, true);
        });
    }

    private function getBrengerShippingMethod(Order $order): WC_Order_Item_Shipping
    {
        $shipping_methods = $order->get_shipping_methods();

        foreach ($shipping_methods as $shipping_method) {
            if ($shipping_method->get_method_id() === 'brenger') {
                return $shipping_method;
            }
        }

        throw new NoBrengerMethodInShipping();
    }

    public function overrideShipmentItemSets(Shipment $shipment, array $custom_items): Shipment
    {
        $items = [];
        foreach($custom_items as $custom_item){
            $items[] = new Item([
                        'count' => $custom_item['qty'],
                        'title' => $custom_item['title'],
                        'length' => $custom_item['length'],
                        'width' => $custom_item['width'],
                        'height' => $custom_item['height'],
                        'weight' => $custom_item['weight'],
                        ]
                    );
        }
        
        $shipment['item_sets'][0]['items'] = $items;

        return $shipment;        
    }


    public function overrideShipmentDeliveryOptions(Shipment $shipment, array $delivery_options): Shipment
    {
        $shipment['delivery']['details']['floor'] = intval($delivery_options['floor']);
        $shipment['delivery']['details']['elevator'] = $delivery_options['elevator_available'] === "1";
        $shipment['delivery']['details']['extra_carrying_help'] = $delivery_options['extra_carrying_help'] === "1";
        return $shipment;
        
    }
}
