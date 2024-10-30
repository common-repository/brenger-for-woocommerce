<?php

namespace Brenger\WooCommerce\Shipping;

use WC_Shipping_Method;

//phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps

/**
 * This class handles adding of the Brenger shipping method to WooCommerce.
 *
 * @psalm-suppress PropertyNotSetInConstructor
 */
class BrengerMethod extends WC_Shipping_Method
{

    /**
     * Holds an array of all currently available shipping classes.
     *
     * @var array
     */
    private $registered_shipping_classes = array();

    /**
     * Constructor for your shipping class
     *
     * @access public
     * @return void
     */
    public function __construct(int $instance_id = 0)
    {
        $this->id                          = 'brenger';
        $this->instance_id                 = absint($instance_id);
        $this->title                       = __('Brenger', 'brenger-for-woocommerce');
        $this->method_title                = __('Brenger', 'brenger-for-woocommerce');
        $this->method_description          = __('Shipping method for use with Brenger');
        $this->enabled                     = $this->get_option('enabled');

        $this->supports = array(
            'settings',
            'shipping-zones',
        );

        // Load on init, as otherwise the product_shipping_class taxonomy does not yet exist.
        add_action('init', function () {
            $this->registered_shipping_classes = WC()->shipping()->get_shipping_classes();
            $this->init();
        });
    }

    /**
     * Init your settings
     *
     * @access public
     * @return void
     */
    public function init(): void
    {
        // Load the settings API
        $this->init_form_fields();
        $this->init_settings();

        // Save settings in admin if you have any defined
        add_action('woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ));
    }

    /**
     * Init form fields.
     */
    public function init_form_fields(): void
    {
        $this->form_fields = array(
            'api_url'          => array(
                'title'       => __('Brenger API url', 'brenger-for-woocommerce'),
                'type'        => 'text',
                'description' => __('URL of the Brenger API (for debugging purposes).', 'brenger-for-woocommerce'),
                'default'     => 'https://external-api.brenger.nl/v1',
                'desc_tip'    => true,
            ),
            'api_key'          => array(
                'title'       => __('Brenger API key', 'brenger-for-woocommerce'),
                'type'        => 'password',
                'description' => __(
                    'API key of the Brenger service for this shipping method to communicate with.',
                    'brenger-for-woocommerce'
                ),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'shipping_classes' => array(
                'title'       => __('Brenger shipping classes', 'brenger-for-woocommerce'),
                'type'        => 'multiselect',
                'description' => $this->shipping_class_description(),
                'options'     => $this->get_shipping_class_options(),
                'desc_tip'    => ! empty($this->registered_shipping_classes),
            ),
            array(
                'title'       => __('Contact details', 'brenger-for-woocommerce'),
                'type'        => 'title',
                'id'          => 'pickup_contact_details',
                'description' => __(
                    'Please fill in the details of the contact person at the pickup point.',
                    'brenger-for-woocommerce'
                ),
            ),
            'first_name'             => array(
                'title'       => __('First Name', 'brenger-for-woocommerce'),
                'type'        => 'text',
                'description' => __('Required', 'brenger-for-woocommerce'),
                'desc_tip'    => true,
            ),
            'last_name'             => array(
                'title'       => __('Last Name', 'brenger-for-woocommerce'),
                'type'        => 'text',
                'description' => __('Required', 'brenger-for-woocommerce'),
                'desc_tip'    => true,
            ),
            'email'            => array(
                'title'       => __('Email', 'brenger-for-woocommerce'),
                'type'        => 'text',
                'description' => __('Required', 'brenger-for-woocommerce'),
                'desc_tip'    => true,
            ),
            'phone'            => array(
                'title' => __('Phone', 'brenger-for-woocommerce'),
                'type'  => 'text',
            ),
            array(
                'title'       => __('Pickup point details', 'brenger-for-woocommerce'),
                'type'        => 'title',
                'id'          => 'pickup_point_details',
                'description' => __('Please specify the location details of the pickup point.', 'brenger-for-woocommerce'),
            ),
            'address'          => array(
                'title'       => __('Address', 'brenger-for-woocommerce'),
                'type'        => 'text',
                'description' => __('Required', 'brenger-for-woocommerce'),
                'desc_tip'    => true,
            ),
            'address_line_2'   => array(
                'title' => __('Address line 2', 'brenger-for-woocommerce'),
                'type'  => 'text',
            ),
            'postal_code'      => array(
                'title'       => __('Postal code', 'brenger-for-woocommerce'),
                'type'        => 'text',
                'description' => __('Required', 'brenger-for-woocommerce'),
                'desc_tip'    => true,
            ),
            'locality'         => array(
                'title'       => __('City', 'brenger-for-woocommerce'),
                'type'        => 'text',
                'description' => __('Required', 'brenger-for-woocommerce'),
                'desc_tip'    => true,
            ),
            'province'         => array(
                'title'       => __('Province', 'brenger-for-woocommerce'),
                'type'        => 'text',
                'description' => __('Required', 'brenger-for-woocommerce'),
                'desc_tip'    => true,
            ),
            'country'         => array(
                'title'       => __('Country (two digit code)', 'brenger-for-woocommerce'),
                'type'        => 'text',
                'description' => __('Required', 'brenger-for-woocommerce'),
                'desc_tip'    => true,
            ),
            'situation'        => array(
                'title'       => __('Situation', 'brenger-for-woocommerce'),
                'type'        => 'select',
                'description' => __('Required', 'brenger-for-woocommerce'),
                'desc_tip'    => true,
                'options'     => $this->situation(),
            ),
            'floor'            => array(
                'title'       => __('Floor', 'brenger-for-woocommerce'),
                'type'        => 'select',
                'description' => __(
                    'Only required when the situation is located above or below ground floor',
                    'brenger-for-woocommerce'
                ),
                'desc_tip'    => true,
                'options'     => $this->floors(),
                'required'    => true,
                'default'     => 0,
            ),
            'elevator'         => array(
                'title'       => __('Elevator present', 'brenger-for-woocommerce'),
                'type'        => 'checkbox',
                'description' => __(
                    'Only required when the situation is located above or below ground floor',
                    'brenger-for-woocommerce'
                ),
                'desc_tip'    => true,
                'default'     => 'no',
            ),
            'instructions'     => array(
                'title'       => __('Instructions', 'brenger-for-woocommerce'),
                'type'        => 'textarea',
                'description' => __(
                    'Please specify any instructions the transporter should know about.',
                    'brenger-for-woocommerce'
                ),
                'desc_tip'    => true,
            ),
        );
    }

    /**
     * Get a key => value array of all current shipping classes.
     *
     * @return array
     */
    private function get_shipping_class_options(): array
    {
        $shipping_classes = array();

        foreach ($this->registered_shipping_classes as $class) {
            $shipping_classes[ $class->term_id ] = $class->name;
        }

        return $shipping_classes;
    }

    /**
     * Base the description of the shipping classes multi select based on if any shipping classes are available.
     *
     * @return string
     */
    private function shipping_class_description(): string
    {
        if (empty($this->registered_shipping_classes)) {
            $url = admin_url('admin.php?page=wc-settings&tab=shipping&section=classes');

            return sprintf(__(
                'In order for Brenger to work one or more shipping classes have to be defined. You can define them' .
                ' under the %1$sshipping classes%2$s',
                'brenger-for-woocommerce'
            ), '<a href="' . $url . '">', '</a>');
        }

        return __('Brenger will only show up for products with the selected shipping classes', 'brenger-for-woocommerce');
    }

    /**
     * Get an array of levels, with ground floor as bottom one.
     *
     * @return array
     */
    private function floors(): array
    {
        $floors = array();

        for ($i = - 3; $i <= 100; $i++) {
            if ($i === 0) {
                $floors[ $i ] = __('Ground floor', 'brenger-for-woocommerce');
                continue;
            }

            $floors[ $i ] = $i;
        }

        return $floors;
    }

    /**
     * Get an array of possible situations of the pickup location.
     *
     * @return array
     */
    private function situation(): array
    {
        return array(
            'store'   => __('Store', 'brenger-for-woocommerce'),
            'home'    => __('Home', 'brenger-for-woocommerce'),
            'auction' => __('Auction', 'brenger-for-woocommerce'),
        );
    }
}
