<?php
/**
 * Blocksy Theme Integration & Shortcode
 *
 * @package WC_Dynamic_Pricing_Table
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_Dynamic_Pricing_Table_Blocksy_Integration
 *
 * Handles shortcode for use in Blocksy content blocks
 */
class WC_Dynamic_Pricing_Table_Blocksy_Integration {

	/**
	 * Constructor
	 */
	public function __construct() {
		// Register the shortcode
		add_shortcode( 'dynamic_pricing_table', array( $this, 'render_shortcode' ) );

		// Also register with alternative name for convenience
		add_shortcode( 'pricing_table', array( $this, 'render_shortcode' ) );
	}

	/**
	 * Render the pricing table via shortcode
	 *
	 * @param array $atts Shortcode attributes
	 * @return string
	 */
	public function render_shortcode( $atts ) {
		// Parse shortcode attributes
		$atts = shortcode_atts( array(
			'product_id' => 0,
		), $atts, 'dynamic_pricing_table' );

		// Get product ID
		$product_id = absint( $atts['product_id'] );

		// If no product ID specified, try to get current product
		if ( ! $product_id ) {
			global $product;

			if ( $product && is_a( $product, 'WC_Product' ) ) {
				$product_id = $product->get_id();
			} else {
				// Try to get from global post
				global $post;
				if ( $post && $post->post_type === 'product' ) {
					$product_id = $post->ID;
				}
			}
		}

		// If still no product ID, return empty
		if ( ! $product_id ) {
			return '';
		}

		// Get the pricing table instance
		$pricing_table = new WC_Dynamic_Pricing_Table_Renderer();

		// Check if there are pricing rules for this product
		$rules = $pricing_table->get_product_pricing_rules( $product_id );

		if ( empty( $rules ) ) {
			return '';
		}

		// Render and return the table
		return $pricing_table->render( $product_id );
	}
}