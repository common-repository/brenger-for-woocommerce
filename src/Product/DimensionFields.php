<?php

namespace Brenger\WooCommerce\Product;

use WP_Post;

/**
 * This class handles adding of the Brenger shipping method to WooCommerce.
 */
class DimensionFields
{
    /**
     * Register hooks.
     */
    public function registerHooks(): void
    {
        add_action('woocommerce_product_options_shipping', array( $this, 'addProductDimensionFields'));
        add_action('woocommerce_variation_options_dimensions', array( $this, 'addVariationDimensionFields'), 10, 3);
        add_action('woocommerce_process_product_meta', array( $this, 'saveDimensionData'));
        add_action('woocommerce_save_product_variation', array( $this, 'saveDimensionVariationData'), 10, 2);
    }

    /**
     * Add dimension fields to the shipping methods product tab. These fields
     * are used when a Brenger shipping class is selected.
     *
     * @param $loop
     * @param $variation_data
     * @param $variation
     */
    public function addVariationDimensionFields(int $loop, array $variation_data, WP_Post $variation): void
    {
        $product_id = get_the_ID();

        if ($product_id === false) {
            return;
        }

        $product_object = new \WC_Product();

        echo '<div class="form-field form-row form-row-full hide_if_variation_virtual">';
        echo '<h4 style="margin-bottom: 5px">' . esc_html__('Brenger', 'brenger-for-woocommerce') . '</h4>';
        echo '<p style="margin: 0">';
        esc_html_e('Description about Brenger dimensions', 'brenger-for-woocommerce');
        echo '</p>';

        woocommerce_wp_text_input(
            array(
                'id'            => "variable_brenger_weight{$loop}",
                'name'          => "variable_brenger_weight[{$loop}]",
                'value'         => get_post_meta($variation->ID, 'brenger_weight', true),
                'placeholder'   => wc_format_localized_decimal($product_object->get_weight()),
                'label'         => esc_html__(
                    'Weight',
                    'woocommerce'
                ) . ' (' . get_option('woocommerce_weight_unit') . ')',
                'desc_tip'      => true,
                'description'   => esc_html__('Weight in decimal form', 'woocommerce'),
                'type'          => 'text',
                'data_type'     => 'decimal',
                'wrapper_class' => 'form-row form-row-first hide_if_variation_virtual',
            )
        );

        echo '<p class="form-field form-row dimensions_field hide_if_variation_virtual form-row-last">';
        echo '<label for="brenger_length">' . esc_html(
            sprintf(__('Dimensions (LxWxH) (%s)', 'woocommerce-brenger'), get_option('woocommerce_dimension_unit'))
        ) . '</label>';
        echo wc_help_tip(
            __('Length x width x height in decimal form', 'brenger-for-woocommerce')
        ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

        echo '<span class="wrap">';

        $dimension_fields = array(
            'brenger_length' => __('Length', 'brenger-for-woocommerce'),
            'brenger_width'  => __('Width', 'brenger-for-woocommerce'),
            'brenger_height' => __('Height', 'brenger-for-woocommerce'),
        );

        foreach ($dimension_fields as $key => $value) {
            echo '<input id="' . esc_attr($key) . '" placeholder="' . esc_attr($value) . '" class="input-text' .
                ' wc_input_decimal" size="6" type="text" name="variable_' . esc_attr($key) .
                '[' . esc_attr((string)$loop) . ']" value="' . esc_attr(
                    wc_format_localized_decimal(get_post_meta($variation->ID, $key, true))
                ) . '" />';
        }

        echo '</span>';

        echo '</p>';
        echo '</div>';
    }

    /**
     * Add custom dimension fields with the same look and feel as WooCommerce dimension fields.
     * These fields are used to determine the complete dimensions of the parcel including packaging material.
     */
    public function addProductDimensionFields(): void
    {
        $product_id = get_the_ID();

        if ($product_id === false) {
            return;
        }

        echo '<p class="form-field">' . esc_html__('Set specific package dimensions for items sent ' .
                'with Brenger', 'brenger-for-woocommerce') . '</p>';

        woocommerce_wp_text_input(
            array(
                'id'          => 'brenger_weight',
                'value'       => get_post_meta($product_id, 'brenger_weight', true),
                'label'       => sprintf(
                    esc_html__(
                        'Weight (%s)',
                        'brenger-for-woocommerce'
                    ),
                    get_option('woocommerce_weight_unit')
                ),
                'placeholder' => wc_format_localized_decimal('0'),
                'desc_tip'    => true,
                'description' => esc_html__('Weight in decimal form', 'brenger-for-woocommerce'),
                'type'        => 'text',
                'data_type'   => 'decimal',
            )
        );

        echo '<p class="form-field dimensions_field">';
        echo '<label for="brenger_length">' . esc_html(sprintf(
            __(
                'Dimensions (%s)',
                'woocommerce-brenger'
            ),
            get_option('woocommerce_dimension_unit')
        )) . '</label>';
        echo '<span class="wrap">';

        $dimension_fields = array(
            'brenger_length' => __('Length', 'brenger-for-woocommerce'),
            'brenger_width'  => __('Width', 'brenger-for-woocommerce'),
            'brenger_height' => __('Height', 'brenger-for-woocommerce'),
        );

        foreach ($dimension_fields as $key => $value) {
            // phpcs:ignore PHPCompatibility.FunctionUse.NewFunctions.array_key_lastFound
            $extra_class = ( array_key_last($dimension_fields) === $key ) ? ' last' : '';
            echo '<input id="' . esc_attr($key) . '" placeholder="' . esc_attr($value) . '" class="input-text' .
                ' wc_input_decimal' . esc_attr($extra_class) . '" size="6" type="text" name="' . esc_attr($key) .
                '" value="' . esc_attr(wc_format_localized_decimal(get_post_meta($product_id, $key, true))) .
                '" />';
        }

        echo '</span>';
        echo wc_help_tip(esc_html__(
            'LxWxH in decimal form',
            'brenger-for-woocommerce'
        )); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

        echo '</p>';
    }

    /**
     * Save our custom dimensions as meta data to the product.
     *
     * @param $post_id
     */
    public function saveDimensionData(int $post_id): void
    {
        if (! check_admin_referer('woocommerce_save_data', 'woocommerce_meta_nonce')) {
            return;
        }

        $dimensions = array();

        $dimensions['brenger_weight'] = ( isset($_POST['brenger_weight']) ? sanitize_text_field($_POST['brenger_weight']) : '' );
        $dimensions['brenger_length'] = ( isset($_POST['brenger_length']) ? sanitize_text_field($_POST['brenger_length']) : '' );
        $dimensions['brenger_width']  = ( isset($_POST['brenger_width']) ? sanitize_text_field($_POST['brenger_width']) : '' );
        $dimensions['brenger_height'] = ( isset($_POST['brenger_height']) ? sanitize_text_field($_POST['brenger_height']) : '' );

        $this->saveData($post_id, $dimensions);
    }

    /**
     * Save dimension data for product variations.
     *
     * @param int $variation_id
     * @param int $i
     */
    public function saveDimensionVariationData($variation_id, $i): void
    {
        $dimensions = array();

        $dimensions['brenger_weight'] = (sanitize_text_field($_POST['variable_brenger_weight'][$i]) ?? '');
        $dimensions['brenger_length'] = (sanitize_text_field($_POST['variable_brenger_length'][$i]) ?? '');
        $dimensions['brenger_width']  = (sanitize_text_field($_POST['variable_brenger_width'][$i]) ?? '');
        $dimensions['brenger_height'] = (sanitize_text_field($_POST['variable_brenger_height'][$i]) ?? '');

        $this->saveData($variation_id, $dimensions);
    }

    /**
     * Actually update or delete post meta for either a product or product variable.
     *
     * @param int   $post_id The ID of the product or product variable.
     * @param array $data    An array containing the dimension fields to be saved.
     */
    private function saveData(int $post_id, array $data): void
    {
        foreach ($data as $key => $value) {
            if (! empty($value)) {
                update_post_meta($post_id, $key, sanitize_text_field($value));
                continue;
            }

            delete_post_meta($post_id, $key);
        }
    }
}
