<?php

namespace Brenger\WooCommerce\Orders;

use Automattic\WooCommerce\Admin\Overrides\Order as OverrideOrder;
use WC_Order_Item_Product;
use WC_Product;
use Brenger\WooCommerce\Transformers\OrderToShipment;

//phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps

/**
 * Display an order items list with Brenger shipping options
 */
class OrderItemsWithOptions extends \WP_List_Table
{
    /**
     * Holds current order.
     *
     * @var OverrideOrder
     */
    private $order;

    /**
     * @var array Contains an array of columns to show
     */
    private $columns;

    /**
     * @param OverrideOrder $order
     */
    public function __construct($order)
    {
        parent::__construct(
            [
                'singular' => esc_html__('Item', 'brenger-for-woocommerce'),
                'plural'   => esc_html__('Items', 'brenger-for-woocommerce'),
                'ajax'     => false,
            ]
        );

        $this->order = $order;

        $this->columns = [
            'item' => esc_html__('Item', 'brenger-for-woocommerce'),
            'cost' => esc_html__('Cost', 'brenger-for-woocommerce'),
            'qty' => esc_html__('Qty', 'brenger-for-woocommerce'),
            'total' => esc_html__('Total', 'brenger-for-woocommerce'),

            // item overrides
            'ship' => esc_html__('Ship', 'brenger-for-woocommerce'),
            'custom_qty' => esc_html__('Qty', 'brenger-for-woocommerce'),
            'weight' => esc_html__('Weight (kg)', 'brenger-for-woocommerce'),
            'dimensions' => esc_html__('Dimensions, LxWxH (cm)', 'brenger-for-woocommerce'),
        ];

        $this->_column_headers = [];
        $this->items = [];
    }

    /**
     * @return array
     */
    public function get_columns(): array
    {
        return $this->columns;
    }

    /**
     * Output the default column, not including the checkbox.
     *
     * @param object|array $item
     * @param string $column_name
     *
     * @return mixed
     */
    public function column_default($item, $column_name)
    {
        if (! is_array($item)) {
            $item = (array) $item;
        }

        return $item[ $column_name ];
    }

    /**
     * Prepares the list of items for displaying.
     */
    public function prepare_items(): void
    {
        $this->display_header();

        $columns               = $this->get_columns();
        $hidden                = [];
        $this->_column_headers = [$columns, $hidden];
        $this->items           = $this->get_items();
    }

    /**
     * Render the item field.
     *
     * @param object|array $item
     */
    public function column_item($item): string
    {
        $product_link = admin_url( 'post.php?post=' . $item['product_id'] . '&action=edit' );

        return '<a href="' . esc_url( $product_link ) . '">' . wp_kses_post( $item['line_item']->get_name() ) . sprintf('</a><input type="hidden" name="items[%s][title]" value="%s")', $item['id'], $item['title']);
    }

    /**
     * Render the cost field.
     *
     * @param object|array $item
     */
    public function column_cost($item): string
    {
        return wc_price( $this->order->get_item_subtotal( $item['line_item'], false, true ),
            [ 'currency' => $this->order->get_currency() ] );
    }

    /**
     * Render the quantity field.
     *
     * @param object|array $item
     */
    public function column_qty($item): string
    {
        return '<small class="times">&times;</small> ' . esc_html( $item['line_item']->get_quantity() );
    }

    /**
     * Render the total field.
     *
     * @param object|array $item
     */
    public function column_total($item): string
    {
        return wc_price( $item['line_item']->get_total(), array( 'currency' => $this->order->get_currency() ) );
    }

    /**
     * Render the Ship field with checkbox.
     *
     * @param object|array $item
     */
    public function column_ship($item): string
    {
        return sprintf('<input type="checkbox" name="items[%s][ship]" checked />', $item['id']);
    }

    /**
     * Render item quantity override field.
     *
     * @param object|array $item
     */
    public function column_custom_qty($item): string
    {
        $qty = $item['line_item']->get_quantity();

        ob_start();

        echo '<select name="items[' . esc_html($item['id']) . '][qty]">';

        for ($i = 1; $i <= $qty; $i++) {
            $selected = $i === $qty ? 'selected' : ''; // select all by default
            echo '<option value="' . $i . '" ' . esc_html($selected) . '>' . $i . '</option>';
        }

        echo '</select>';

        return ob_get_clean();
    }

    /**
     * Render item weight override field.
     *
     * @param object|array $item
     */
    public function column_weight($item): string
    {
        return sprintf('<input type="text" name="items[%s][weight]" value="%s" class="small-text" />',
            $item['id'], $item['weight']);
    }

    /**
     * Render item dimension override fields.
     *
     * @param object|array $item
     */
    public function column_dimensions($item): string
    {
        ob_start();
        echo sprintf('<input type="text" name="items[%s][length]" value="%s" class="small-text" />',
            $item['id'], $item['length']);
        echo sprintf('<input type="text" name="items[%s][width]" value="%s" class="small-text" />',
            $item['id'], $item['width']);
        echo sprintf('<input type="text" name="items[%s][height]" value="%s" class="small-text" />',
            $item['id'], $item['height']);

        return ob_get_clean();
    }

    /**
     * Get all order items that needs shipping.
     *
     * @return array
     */
    private function get_items(): array
    {
        $line_items = $this->order->get_items();

        $items = OrderToShipment::prepareLineItems($this->order);
        
        return $items;                
    }
}
