<?php

namespace Brenger\WooCommerce\Orders;

use Brenger\WooCommerce\AdminNotices;
use Brenger\WooCommerce\API\CreateShipment;
use BrengerClient\ApiException;

class TransportsPage
{
    /**
     * Register WordPress hooks used in this class.
     */
    public function registerHooks(): void
    {
        add_action('init', array( $this, 'init'), 10);
        add_action('admin_menu', array( $this, 'menu' ), 10);
        add_filter('woocommerce_screen_ids', array( $this, 'enqueueWcScripts' ), 10, 1);
        add_action('admin_notices', array( AdminNotices::class, 'maybeShowAdminNotices' ), 10, 0);
    }

    /**
     * Setup the structure of the page and check for potential actions to perform.
     */
    public function init(): void
    {
        $page = filter_input(INPUT_GET, 'page');

        if ($page !== 'brenger-transports') {
            return;
        }

        $action   = filter_input(INPUT_GET, 'action');
        $order_id = filter_input(INPUT_GET, 'order_id', FILTER_SANITIZE_NUMBER_INT);

        if (empty($action) || empty($order_id)) {
            return;
        }

        switch ($action) {
            case 'create':
                $createShipment = new CreateShipment();
                $redirect_url = admin_url('admin.php?page=brenger-transports');
                try {
                    $createShipment->createFromId($order_id);

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
     * Add a submenu item to WooCommerce.
     */
    public function menu(): void
    {
        add_submenu_page(
            'woocommerce',
            esc_html__('Brenger transports', 'brenger-for-woocommerce'),
            esc_html__('Transports', 'brenger-for-woocommerce'),
            'manage_product_terms',
            'brenger-transports',
            array(
                $this,
                'page',
            )
        );
    }

    /**
     * Output the HTML for the Brenger transport overview page.
     */
    public function page(): void
    {
        echo '<div class="wrap post-type-shop_order">';
        echo '<h2>Transports</h2>';
        $transports = new Transports();
        $transports->prepare_items();
        $transports->display();
        echo '</div>';
    }

    /**
     * Include WooCommerce styles on our page.
     *
     * @param array $screens
     *
     * @return array
     */
    public function enqueueWcScripts($screens): array
    {
        $screen    = get_current_screen();

        if (empty($screen)) {
            return [];
        }

        $screens[] = $screen->id;

        return $screens;
    }
}
