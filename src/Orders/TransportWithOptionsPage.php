<?php

namespace Brenger\WooCommerce\Orders;

use Automattic\WooCommerce\Admin\Overrides\Order as OverrideOrder;
use Brenger\WooCommerce\AdminNotices;
use Brenger\WooCommerce\API\CreateShipment;
use BrengerClient\ApiException;

class TransportWithOptionsPage
{
    /**
     * Holds current order.
     *
     * @var OverrideOrder
     */
    private $order;

    /**
     * Register WordPress hooks used in this class.
     */
    public function registerHooks(): void
    {
        add_action('init', array( $this, 'init'), 10);
        add_action('admin_menu', array( $this, 'menu' ), 10);
    }

    /**
     * Setup the structure of the page and check for potential actions to perform.
     */
    public function init(): void
    {
        $page = filter_input(INPUT_GET, 'page');

        if ($page !== 'brenger-transport-with-options') {
            return;
        }

        $action   = filter_input(INPUT_POST, 'action');
        $order_id = filter_input(INPUT_GET, 'order_id', FILTER_SANITIZE_NUMBER_INT);

        // invalid order exception will be automatically handled
        $this->order = new OverrideOrder($order_id);

        //@todo: check nonce
        switch ($action) {
            case 'create':
                $createShipment = new CreateShipment();
                $redirect_url = admin_url('admin.php?page=brenger-transports'); //@todo: what destination?

                try {
                    
                    $raw_items = filter_input(INPUT_POST, 'items', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
                    $raw_delivery_options = filter_input(INPUT_POST, 'delivery', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
                    $createShipment->createFromIdAndCustomInput($order_id, $raw_items, $raw_delivery_options);

                    wp_safe_redirect(add_query_arg(AdminNotices::getArgs(
                        'success',
                        array( 'order_id' => $order_id)
                    ), $redirect_url));

                    exit;
                } catch (ApiException $e) {
                    wp_safe_redirect(add_query_arg(AdminNotices::getArgsFromApiException(
                        $e,
                        array('order_id' => $order_id)
                    ), $redirect_url));
                    exit;
                }
        }
    }

    /**
     * Register hidden submenu item for create transport with option page.
     */
    public function menu(): void
    {
        add_submenu_page(
            null,
            esc_html__('Create Brenger transport', 'brenger-for-woocommerce'),
            esc_html__('Transport with Options', 'brenger-for-woocommerce'),
            'manage_product_terms',
            'brenger-transport-with-options',
            [
                $this,
                'page',
            ]
        );
    }

    /**
     * Output the HTML for the create transport with options page.
     */
    public function page(): void
    {
        $edit_order_url = add_query_arg([
            'action' => 'edit',
            'post' => $this->order->get_id(),
        ], admin_url('post.php'));

        $form_action_url = add_query_arg([
            'page' => 'brenger-transport-with-options',
            'order_id' => $this->order->get_id()
        ], admin_url());

        ?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e('Create Transport', 'brenger-for-woocommerce'); ?></h1>
    <a href="<?php echo esc_url($edit_order_url); ?>" class="page-title-action">
        <?php esc_html_e('Back to Order', 'brenger-for-woocommerce'); ?>
    </a>
    <hr class="wp-header-end">

    <form action="<?php echo esc_url($form_action_url); ?>" method="POST">
        <input type="hidden" name="action" value="create">
      <?php wp_nonce_field('create_brenger_transport_with_options'); ?>

        <?php
            $items = new OrderItemsWithOptions($this->order);
            $items->prepare_items();
            $items->display();
        ?>

        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="floor"><?php esc_html_e('Floor', 'brenger-for-woocommerce'); ?></label>
                    </th>
                    <td>
                        <select id="floor" name="delivery[floor]">
                            <option value="0" selected>
                                <?php esc_html_e('Ground Floor', 'brenger-for-woocommerce'); ?>
                            </option>
                            <option value="1">1</option>
                            <option value="2">2</option>
                            <option value="3">3</option>
                            <option value="4">4</option>
                            <option value="5">5</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="elevator_available">
                            <?php esc_html_e('Elevator Available', 'brenger-for-woocommerce'); ?>
                        </label>
                    </th>
                    <td>
                        <input name="delivery[elevator_available]" type="checkbox" id="elevator_available" value="1">
                    </td>
                </tr>
                <!-- <tr>
                    <th scope="row">
                        <label for="extra_carrying_help">
                            <?php esc_html_e('Extra Carrying Help', 'brenger-for-woocommerce'); ?>
                        </label>
                    </th>
                    <td>
                        <input name="delivery[extra_carrying_help]" type="checkbox" id="extra_carrying_help" value="1">
                    </td>
                </tr> -->
            </tbody>
        </table>

        <?php submit_button(esc_html__('Create Transport', 'brenger-for-woocommerce')); ?>
    </form>
</div>

        <?php
    }
}