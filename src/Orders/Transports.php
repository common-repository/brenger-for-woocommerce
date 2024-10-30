<?php

namespace Brenger\WooCommerce\Orders;

use Automattic\WooCommerce\Admin\Overrides\Order as OverrideOrder;
use wpdb;

//phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps

/**
 * Display a list of all transports for Brenger
 */
class Transports extends \WP_List_Table
{
    /**
     * @var array Contains an array of columns to show in the transport overview
     */
    private $columns;

    /**
     * @var array Contins an array of available columns to sort the order overview by
     */
    private $sort_by;

    /**
     * @var array Contains a list of (meta) fields that the search is used on.
     *
     */
    private $search_by;

    /**
     * @var string Contains the base query for displaying orders.
     */
    private $query;

    /**
     * @var array containing joins needed for the query.
     */
    private $joins;

    /**
     * @var string the order of how to sort orders.
     */
    private $order;

    /**
     * @var string
     */
    private $limit;

    /**
     * @var array
     */
    private $where;

    /** @var array */
    private $brenger_shipping_classes = array();

    /**
     * Transports constructor.
     */
    public function __construct()
    {
        parent::__construct(
            array(
                'singular' => esc_html__('Transport', 'brenger-for-woocommerce'),
                'plural'   => esc_html__('Transports', 'brenger-for-woocommerce'),
                'ajax'     => true,
            )
        );

        $shipping_methods = WC()->shipping()->get_shipping_methods();

        if (array_key_exists('brenger', $shipping_methods)
            && is_iterable($shipping_methods['brenger']->settings['shipping_classes']))
        {
            $this->brenger_shipping_classes = array_map(function ($class_id) {
                return intval($class_id);
            }, $shipping_methods['brenger']->settings['shipping_classes']);
        }

        $this->columns = array(
            'order'        => esc_html__('Order', 'brenger-for-woocommerce'),
            'date'         => esc_html__('Date', 'brenger-for-woocommerce'),
            'transport'    => esc_html__('Transport', 'brenger-for-woocommerce'),
            'order_status' => esc_html__('Order status', 'brenger-for-woocommerce'),
            'action'       => esc_html__('Action', 'brenger-for-woocommerce'),
        );

        $this->sort_by = array(
            'order' => 'orders.id',
            'date'  => 'orders.post_date',
        );

        $this->search_by = array(
            '_billing_address_index',
            '_shipping_address_index',
        );

        $this->_column_headers = [];
        $this->items = [];

        $this->query = $this->setQuery();
        $this->joins = $this->setJoins();
        $this->where = $this->setWhere();
        $this->setLimit();
        $this->setOrder();
    }

    /**
     * Set the column output on the overview page.
     *
     * @return array
     */
    public function get_columns(): array
    {
        return array_merge(
            array( 'cb' => '<input type="checkbox" />' ),
            $this->columns
        );
    }

    /**
     * Display the table heading and search query, if any
     */
    protected function display_header(): void
    {
        echo '<h1 class="wp-heading-inline">' . esc_attr($this->table_header) . '</h1>';
        if ($this->get_request_search_query()) {
            /* translators: %s: search query */
            echo '<span class="subtitle">' . esc_attr(sprintf(
                __('Search results for "%s"', 'woocommerce'),
                $this->get_request_search_query()
            )) . '</span>';
        }
        echo '<hr class="wp-header-end">';
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
     * Get an array of all sortable columns.
     *
     * @return array[]
     */
    public function get_sortable_columns(): array
    {
        $sort_by = array();
        foreach ($this->sort_by as $key => $column) {
            $sort_by[ $key ] = array( $column, true );
        }

        return $sort_by;
    }

    /**
     * Prepares the list of items for displaying.
     */
    public function prepare_items(): void
    {
        $this->process_bulk_items();
        $this->process_row_actions();
        $this->display_header();

        $columns               = $this->get_columns();
        $hidden                = array();
        $sortable              = $this->get_sortable_columns();
        $this->_column_headers = array( $columns, $hidden, $sortable );
        $this->items           = $this->get_transports();

        global $wpdb;
        $query_count = "SELECT DISTINCT COUNT(ID) as order_id
FROM {$wpdb->prefix}posts as orders
INNER JOIN {$wpdb->prefix}woocommerce_order_items as order_items ON orders.ID = order_items.order_id
INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS order_itemmeta ON order_items.order_item_id = order_itemmeta.order_item_id
INNER JOIN {$wpdb->prefix}term_relationships AS term_relationships ON order_itemmeta.meta_value = term_relationships.object_id
WHERE orders.post_status != 'trash'
AND order_items.order_item_type = 'line_item'
AND order_itemmeta.meta_key in ('_product_id', '_variation_id')
AND term_relationships.term_taxonomy_id IN (" . implode(', ', $this->brenger_shipping_classes) . ")";

        $total_items = $wpdb->get_var($query_count);

        $this->set_pagination_args(
            array(
                'total_items' => $total_items,
                'per_page'    => $this->get_items_per_page('brenger_transport_items_per_page', 20),
                'total_pages' => ceil($total_items / $this->get_items_per_page(
                    'brenger_transport_items_per_page',
                    20
                )),
            )
        );
    }

    /**
     *
     */
    private function process_bulk_items(): void
    {
        // @ToDo add functionality for processing items in bulk.
    }

    /**
     *
     */
    private function process_row_actions(): void
    {
        // @ToDo add functionality for processing items in per row.
    }

    /**
     * Show extra options above and below the table such as filters and a search option.
     *
     * @param string $which The location of the tablenav. Either top or bottom of the table.
     */
    public function extra_tablenav($which): void
    {
        echo '<form action="" method="GET">';
        $this->search_box(esc_html__('Search', 'brenger-for-woocommerce'), 'search-transports');
        echo '<input type="hidden" name="page" value="' . esc_attr($_REQUEST['page']) . '" />';

        echo '<div class="alignleft actions">';
        $this->transportStatusDropdown();
        $this->orderStatusDropdown();
        submit_button(
            esc_html__('Filter', 'brenger-for-woocommerce'),
            '',
            'filter_action',
            false,
            array( 'id' => 'post-query-submit' )
        );
        echo '</div>';
        echo '</form>';
    }

    /**
     * Display the drop down with Brenger transport statuses.
     */
    private function transportStatusDropdown(): void
    {
        $selected = ( isset($_GET['brenger_transport_status']) ? sanitize_text_field($_GET['brenger_transport_status']) : '' );

        echo '<select name="brenger_transport_status">';
        echo '<option value="">' . esc_html__('All transport statuses', 'brenger-for-woocommerce') . '</option>';

        foreach (TransportStatus::getStatuses() as $key => $status) {
            echo '<option value="' . esc_attr($key) . '" ' . selected($selected, $key) . '>' .
                esc_html($status['message']) . '</option>';
        }

        echo '</select>';
    }

    /**
     * Display the dropdown with WooCommerce order statuses.
     */
    private function orderStatusDropdown(): void
    {
        $selected = ( isset($_GET['order-status']) ? sanitize_text_field($_GET['order-status']) : '' );

        echo '<select name="order-status">';
        echo '<option value="">' . esc_html__('All order statuses', 'brenger-for-woocommerce') . '</option>';

        foreach (wc_get_order_statuses() as $key => $status) {
            echo '<option value="' . esc_attr($key) . '" ' . selected($selected, $key) . '>' .
                esc_html($status) . '</option>';
        }

        echo '</select>';
    }

    /**
     * Render the checkbox field.
     *
     * @param object|array $item
     */
    public function column_cb($item): string
    {
        if (! is_array($item)) {
            $item = (array) $item;
        }

        return sprintf('<input type="checkbox" name="transport_order[]" value="%s" />', $item['id']);
    }

    /**
     * Get all orders that have a Brenger shipping method.
     *
     * @return array
     */
    public function get_transports(): array
    {
        global $wpdb;
        $data = array();

        $query = $this->getQuery();

        $orders = $wpdb->get_results($query);

        if (! is_iterable($orders)) {
            return $data;
        }

        foreach ($orders as $order_data) {
            $order  = new OverrideOrder($order_data->order_id);
            $status = new TransportStatus($order);

            $data[] = array(
                'id'           => $order->get_id(),
                'order'        => '<strong><a href="' . esc_url($order->get_edit_order_url()) . '">#' .
                    esc_html((string)$order->get_id()) . ' ' . esc_html($order->get_billing_first_name()) . ' ' .
                    esc_html($order->get_billing_last_name()) . '</a></strong>',
                'date'         => $this->renderOrderDate($order),
                'transport'    => '<p><mark class="order-status status-' .
                    esc_attr($status->getStatusClass()) . '"><span>' .
                    esc_html($status->getStatusMessage()) . '</span></mark></p>',
                'order_status' => wc_get_order_status_name($order->get_status()),
                'action'       => $this->getOrderAction($status, $order),
            );
        }

        return $data;
    }

    /**
     * Get the action to display in the transport overview. This will either
     * be a button to create a transport, a link to track the transport or
     * an empty string if the URL is not present.
     */
    private function getOrderAction(TransportStatus $status, OverrideOrder $order): string
    {
        if ($status->getTransportStatus() === 'not_created') {
            $action_url = add_query_arg(
                array(
                    'action' => 'create',
                    'order_id' => $order->get_id()),
                admin_url('admin.php?page=brenger-transports')
            );

            return sprintf(
                esc_html__('%1$sCreate transport%2$s ', 'brenger-for-woocommerce'),
                '<a href="' . esc_url($action_url) . '" class="button">',
                '</a>'
            );
        }

        $tracking_url = get_post_meta($order->get_id(), '_brenger_transport_tracking_url', true);
        if ($tracking_url) {
            return sprintf(
                esc_html__('%1$sTrack transport%2$s', 'brenger-for-woocommerce'),
                '<a href="' . esc_url($tracking_url) . '" target="_blank">',
                '</a>'
            );
        }

        return '';
    }

    /**
     * Get the query used for displaying the orders in the Brenger transport overview.
     */
    private function getQuery(): string
    {
        $where = $this->getWhere();
        $joins = $this->getJoins();

        return $this->query .  $joins .  $where . $this->order . $this->limit;
    }

    /**
     * The main query to get orders containing Brenger as a shipping method
     */
    private function setQuery(): string
    {
        global $wpdb;

        return "SELECT DISTINCT ID as order_id FROM {$wpdb->prefix}posts as orders";
    }

    /**
     * Set an array with arrays containing the JOINS needed for the query.
     * The array key should be the left table in the JOIN.
     */
    private function setJoins(): array
    {
        global $wpdb;

        $joins = array();
        $joins['woocommerce_order_items'][] = "INNER JOIN {$wpdb->prefix}woocommerce_order_items" .
            " as order_items ON orders.ID = order_items.order_id";
        $joins['woocommerce_order_itemmeta'][] = "INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta" .
            " as order_itemmeta ON order_items.order_item_id = order_itemmeta.order_item_id";
        $joins['wp_term_relationships'][] = "INNER JOIN {$wpdb->prefix}term_relationships" .
            " as term_relationships ON order_itemmeta.meta_value = term_relationships.object_id";

        return $joins;
    }

    /**
     * Transform the array items to a single string with JOINS to be used in the query.
     */
    private function getJoins(): string
    {
        $joins = '';

        foreach ($this->joins as $join) {
            $joins .= ' ' . join("\n ", $join);
        }

        return $joins;
    }

    /**
     * Set all WHERE clauses for the query.
     */
    private function setWhere(): array
    {
        $where = array();

        // filter orders where at least one line item has Brenger shipping class
        $where[] = "order_items.order_item_type = 'line_item'";
        $where[] = "order_itemmeta.meta_key in ('_product_id', '_variation_id')";
        $where[] = "term_relationships.term_taxonomy_id IN (" . implode(', ', $this->brenger_shipping_classes) . ")";

        $where[] = "orders.post_status != 'trash'";
        $where[] = $this->setSearch();
        $where   = array_merge($where, $this->setFilters());

        return $where;
    }

    /**
     * Transform the array items to a single string with WHERE to be used in the query.
     */
    private function getWhere(): string
    {
        $this->where = array_filter($this->where);

        if (! empty($this->where)) {
            $where = " WHERE " . implode(' AND ', $this->where) . '';
        } else {
            $where = '';
        }

        return $where;
    }

    /**
     * Apply the search query to the WHERE clause.
     */
    private function setSearch(): string
    {
        global $wpdb;

        if (empty($_GET['s']) || empty($this->search_by)) {
            return '';
        }

        $filter = array();
        $suffix = '';

        if (isset($this->joins['postmeta'])) {
            $suffix = '_' . ( count($this->joins['postmeta']) + 1 );
        }

        $this->joins['postmeta'][] = "INNER JOIN {$wpdb->prefix}postmeta" .
                                     " as order_meta{$suffix} ON orders.ID = order_meta.post_id";
        foreach ($this->search_by as $column) {
            $filter[] = "order_meta{$suffix}.meta_key = '{$column}'";
        }

        $search_query = "(";
        $search_query .= implode(' OR ', $filter);

        $search_query .= ' ) AND ' . $wpdb->prepare('order_meta' . $suffix . '.meta_value like "%s"', '%' .
                $wpdb->esc_like(sanitize_text_field($_GET['s'])) . '%');

        return $search_query;
    }

    /**
     * Handles the possibility of filters.
     */
    private function setFilters(): array
    {
        $filters = array();

        if (isset($_GET['brenger_transport_status']) && ! empty($_GET['brenger_transport_status'])) {
            $filters[] = $this->transportStatusFilter();
        }

        if (isset($_GET['order-status']) && ! empty($_GET['order-status'])) {
            $filters[] = $this->woocommerceOrderStatusFilter();
        }

        return $filters;
    }

    /**
     * Set the query variables needed for using the Brenger transport status filter.
     */
    private function transportStatusFilter(): string
    {
        global $wpdb;

        $suffix = '';

        if (isset($this->joins['postmeta'])) {
            $suffix = '_' . ( count($this->joins['postmeta']) + 1 );
        }

        $this->joins['postmeta'][] = "INNER JOIN {$wpdb->prefix}postmeta" .
                                     " as order_meta{$suffix} ON orders.ID = order_meta{$suffix}.post_id";

        return $wpdb->prepare(
            "order_meta{$suffix}.meta_key = '_brenger_transport_status'" .
                                        " AND order_meta{$suffix}.meta_value = '%s'",
            esc_sql(sanitize_text_field($_GET['brenger_transport_status']))
        );
    }

    /**
     * Set the query variables needed for using the WooCommerce order status filter
     */
    private function woocommerceOrderStatusFilter(): string
    {
        global $wpdb;

        return $wpdb->prepare("orders.post_status = %s", esc_sql(sanitize_text_field($_GET['order-status'])));
    }

    /**
     * Set ordering to the order query.
     */
    private function setOrder(): void
    {
        $orderby = esc_sql($this->getRequestOrderBy());
        $order   = esc_sql($this->getRequestOrder());

        if (is_array($orderby)) {
            $orderby = implode(' ', $orderby);
        }

        if (is_array($order)) {
            $order = implode(' ', $order);
        }

        $this->order = " ORDER BY {$orderby} {$order}";
    }

    /**
     * Return the sortable column specified for this request to order the results by, if any.
     */
    private function getRequestOrderBy(): string
    {

        $valid_sortable_columns = array_values($this->sort_by);

        if (! empty($_GET['orderby']) && in_array($_GET['orderby'], $valid_sortable_columns)) {
            $orderby = sanitize_text_field($_GET['orderby']);
        } else {
            $orderby = $valid_sortable_columns[0];
        }

        return $orderby;
    }

    /**
     * Return the sortable column order specified for this request.
     */
    private function getRequestOrder(): string
    {
        if (! empty($_GET['order']) && 'asc' === strtolower($_GET['order'])) {
            $order = 'ASC';
        } else {
            $order = 'DESC';
        }

        return $order;
    }

    /**
     * Get prepared LIMIT clause for items query
     */
    private function setLimit(): void
    {
        global $wpdb;
        $items_per_page = $this->get_items_per_page('brenger_transport_items_per_page');
        $current_page   = $this->get_pagenum();
        if (1 < $current_page) {
            $offset = $items_per_page * ( $current_page - 1 );
        } else {
            $offset = 0;
        }

        $this->limit = $wpdb->prepare(' LIMIT %1$d, %2$d', $offset, $items_per_page);
    }

    /**
     * Render order date column in human readable form.
     */
    private function renderOrderDate(OverrideOrder $order): string
    {
        $created_date = $order->get_date_created();

        if (empty($created_date)) {
            return '&ndash;';
        }

        $order_timestamp = $created_date->getTimestamp();

        if (! $order_timestamp) {
            return '&ndash;';
        }

        // Check if the order was created within the last 24 hours, and not in the future.
        if ($order_timestamp > strtotime('-1 day', time()) && $order_timestamp <= time()) {
            $show_date = sprintf(
            /* translators: %s: human-readable time difference */
                _x('%s ago', '%s = human-readable time difference', 'woocommerce'),
                human_time_diff($order_timestamp, time())
            );
        } else {
            $show_date = $created_date->date_i18n(apply_filters(
                'woocommerce_admin_order_date_format',
                __('M j, Y', 'woocommerce')
            ));
        }

        return sprintf(
            '<time datetime="%1$s" title="%2$s">%3$s</time>',
            esc_attr($created_date->date('c')),
            esc_html($created_date->date_i18n(get_option('date_format') . ' ' .
                get_option('time_format'))),
            esc_html($show_date)
        );
    }
}
