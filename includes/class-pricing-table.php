<?php
/**
 * Pricing Table Renderer
 *
 * @package WC_Dynamic_Pricing_Table
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_Dynamic_Pricing_Table_Renderer
 *
 * Handles rendering of pricing tables
 */
class WC_Dynamic_Pricing_Table_Renderer {

	/**
	 * Constructor
	 */
	public function __construct() {
		// Initialize if needed
	}

	/**
	 * Get pricing rules for a specific product
	 *
	 * @param int $product_id
	 * @return array
	 */
	public function get_product_pricing_rules( $product_id = 0 ) {
		if ( ! $product_id ) {
			$product_id = get_the_ID();
		}

		// Get product-specific pricing rules
		$price_sets = get_post_meta( $product_id, '_pricing_rules', true );

		if ( empty( $price_sets ) ) {
			return array();
		}

		// Filter valid rules based on dates and conditions
		$valid_rules = array();
		foreach ( $price_sets as $key => $price_set ) {
			// Only process product-specific rules, not category rules
			if ( isset( $price_set['collector'] ) && isset( $price_set['collector']['type'] ) &&
				 $price_set['collector']['type'] === 'cat_product' ) {
				continue;
			}

			// Check date validity
			if ( $this->is_rule_date_valid( $price_set ) && $this->is_rule_condition_valid( $price_set ) ) {
				$valid_rules[ $key ] = $price_set;
			}
		}

		return $valid_rules;
	}

	/**
	 * Check if rule date is valid
	 *
	 * @param array $rule
	 * @return bool
	 */
	private function is_rule_date_valid( $rule ) {
		$from_date = empty( $rule['date_from'] ) ? false : strtotime( date_i18n( 'Y-m-d 00:00:00', strtotime( $rule['date_from'] ), false ) );
		$to_date   = empty( $rule['date_to'] ) ? false : strtotime( date_i18n( 'Y-m-d 00:00:00', strtotime( $rule['date_to'] ), false ) );
		$now       = current_time( 'timestamp' );

		if ( $from_date && $to_date && ! ( $now >= $from_date && $now <= $to_date ) ) {
			return false;
		} elseif ( $from_date && ! $to_date && ! ( $now >= $from_date ) ) {
			return false;
		} elseif ( $to_date && ! $from_date && ! ( $now <= $to_date ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Check if rule conditions are valid for current user
	 *
	 * @param array $rule
	 * @return bool
	 */
	private function is_rule_condition_valid( $rule ) {
		if ( ! isset( $rule['conditions'] ) || empty( $rule['conditions'] ) ) {
			return true;
		}

		$conditions_met = 0;
		$total_conditions = count( $rule['conditions'] );

		foreach ( $rule['conditions'] as $condition ) {
			if ( $this->evaluate_condition( $condition ) ) {
				$conditions_met++;
			}
		}

		if ( isset( $rule['conditions_type'] ) && $rule['conditions_type'] === 'any' ) {
			return $conditions_met > 0;
		}

		return $conditions_met === $total_conditions;
	}

	/**
	 * Evaluate a single condition
	 *
	 * @param array $condition
	 * @return bool
	 */
	private function evaluate_condition( $condition ) {
		if ( ! isset( $condition['type'] ) ) {
			return false;
		}

		switch ( $condition['type'] ) {
			case 'apply_to':
				if ( isset( $condition['args']['applies_to'] ) ) {
					switch ( $condition['args']['applies_to'] ) {
						case 'everyone':
							return true;
						case 'unauthenticated':
							return ! is_user_logged_in();
						case 'authenticated':
							return is_user_logged_in();
						case 'roles':
							if ( is_user_logged_in() && isset( $condition['args']['roles'] ) ) {
								foreach ( $condition['args']['roles'] as $role ) {
									if ( current_user_can( $role ) ) {
										return true;
									}
								}
							}
							return false;
					}
				}
				break;
		}

		return apply_filters( 'wc_dynamic_pricing_table_evaluate_condition', false, $condition );
	}

	/**
	 * Render the pricing table
	 *
	 * @param int $product_id
	 * @return string
	 */
	public function render( $product_id = 0 ) {
		if ( ! $product_id ) {
			$product_id = get_the_ID();
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return '';
		}

		$rules = $this->get_product_pricing_rules( $product_id );
		if ( empty( $rules ) ) {
			return '';
		}

		// Get the first valid rule set (usually there's only one per product)
		$pricing_rule_set = reset( $rules );

		// Only process continuous (bulk) pricing mode
		if ( ! isset( $pricing_rule_set['mode'] ) || $pricing_rule_set['mode'] !== 'continuous' ) {
			return '';
		}

		// Get pieces per carton from product attributes
		$pieces_per_carton = $this->get_pieces_per_carton( $product );

		// Start output buffering
		ob_start();

		// Render the table
		$this->render_table( $pricing_rule_set, $product, $pieces_per_carton );

		return ob_get_clean();
	}

	/**
	 * Get pieces per carton from product attributes
	 *
	 * @param WC_Product $product
	 * @return int
	 */
	private function get_pieces_per_carton( $product ) {
		$attributes = $product->get_attributes();
		if ( isset( $attributes['pezzi-a-cartone'] ) && isset( $attributes['pezzi-a-cartone']['value'] ) ) {
			return intval( $attributes['pezzi-a-cartone']['value'] );
		}
		return 1; // Default to 1 if not found
	}

	/**
	 * Get maximum order quantity from WooCommerce Min Max Quantities plugin
	 *
	 * @param WC_Product $product
	 * @return int Maximum order quantity (0 if not set or plugin not active)
	 */
	private function get_product_max_quantity( $product ) {
		// Check if WC Min Max Quantities plugin is active and class exists
		if ( class_exists( 'WC_Min_Max_Quantities_Quantity_Rules' ) ) {
			try {
				$rules = new WC_Min_Max_Quantities_Quantity_Rules( $product );
				$all_rules = $rules->get();
				$max_quantity = isset( $all_rules[ WC_Min_Max_Quantities_Quantity_Rules::MAXIMUM ] )
					? absint( $all_rules[ WC_Min_Max_Quantities_Quantity_Rules::MAXIMUM ] )
					: 0;
				return $max_quantity;
			} catch ( Exception $e ) {
				// Fallback to post meta if class method fails
			}
		}

		// Fallback: try to get from post meta directly
		$max_quantity = absint( $product->get_meta( 'maximum_allowed_quantity', true ) );

		// If still 0, check for variation-specific meta
		if ( 0 === $max_quantity && $product->is_type( 'variation' ) ) {
			$max_quantity = absint( $product->get_meta( 'variation_maximum_allowed_quantity', true ) );
		}

		return $max_quantity;
	}

	/**
	 * Render the pricing table HTML
	 *
	 * @param array $pricing_rule_set
	 * @param WC_Product $product
	 * @param int $pieces_per_carton
	 */
	private function render_table( $pricing_rule_set, $product, $pieces_per_carton ) {
		?>
		<div class="wc-dynamic-pricing-table"
			 data-product-id="<?php echo esc_attr( $product->get_id() ); ?>">
			<table>
				<thead>
					<tr>
						<th class="quantity-col"><?php esc_html_e( 'Cartons', 'wc-dynamic-pricing-table' ); ?></th>
						<th class="discount-col"><?php esc_html_e( 'Discount', 'wc-dynamic-pricing-table' ); ?></th>
						<th class="price-col"><?php esc_html_e( '€/pizza', 'wc-dynamic-pricing-table' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php $this->render_table_rows( $pricing_rule_set['rules'], $product, $pieces_per_carton ); ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Render table rows
	 *
	 * @param array $rules
	 * @param WC_Product $product
	 * @param int $pieces_per_carton
	 */
	private function render_table_rows( $rules, $product, $pieces_per_carton ) {
		foreach ( $rules as $rule ) {
			$this->render_single_row( $rule, $product, $pieces_per_carton );
		}
	}

	/**
	 * Render a single table row
	 *
	 * @param array $rule
	 * @param WC_Product $product
	 * @param int $pieces_per_carton
	 */
	private function render_single_row( $rule, $product, $pieces_per_carton ) {
		$from = wc_stock_amount( $rule['from'] );
		$to = isset( $rule['to'] ) ? wc_stock_amount( $rule['to'] ) : null;

		// Calculate pizzas count
		$from_pizzas = $from * $pieces_per_carton;
		$to_pizzas = $to ? $to * $pieces_per_carton : null;

		// Get max order quantity from Min Max Quantities plugin
		$max_order = $this->get_product_max_quantity( $product );

		// Format quantity display
		$quantity_text = $this->format_quantity_text( $from, $to, $from_pizzas, $to_pizzas, $max_order );

		// Calculate discount and price
		$discount_text = '';
		$price_per_pizza = '';

		$base_price = $product->get_regular_price();

		switch ( $rule['type'] ) {
			case 'percentage_discount':
				$discount_text = esc_html( $rule['amount'] ) . '%';
				$discounted_price = $base_price - ( ( $base_price * $rule['amount'] ) / 100 );
				$price_per_pizza = wc_price( $discounted_price / $pieces_per_carton );
				break;

			case 'fixed_price':
				$discount_text = $this->calculate_percentage_from_fixed_price( $rule['amount'], $base_price ) . '%';
				$price_per_pizza = wc_price( $rule['amount'] / $pieces_per_carton );
				break;

			case 'price_discount':
				$discounted_price = $base_price - $rule['amount'];
				$discount_text = $this->calculate_percentage_discount( $rule['amount'], $base_price ) . '%';
				$price_per_pizza = wc_price( $discounted_price / $pieces_per_carton );
				break;
		}

		?>
		<tr data-from="<?php echo esc_attr( $from ); ?>"
			data-to="<?php echo esc_attr( $to ?: '' ); ?>"
			data-type="<?php echo esc_attr( $rule['type'] ); ?>"
			data-amount="<?php echo esc_attr( $rule['amount'] ); ?>">
			<td class="quantity-col">
				<strong><?php echo esc_html( $quantity_text['cartons'] ); ?></strong>
				<br>
				<small><?php echo esc_html( $quantity_text['pizzas'] ); ?></small>
			</td>
			<td class="discount-col"><?php echo esc_html( $discount_text ); ?></td>
			<td class="price-col"><?php echo $price_per_pizza; ?></td>
		</tr>
		<?php
	}

	/**
	 * Format quantity text for display
	 *
	 * @param int $from
	 * @param int|null $to
	 * @param int $from_pizzas
	 * @param int|null $to_pizzas
	 * @param int $max_order Maximum order quantity from product
	 * @return array
	 */
	private function format_quantity_text( $from, $to, $from_pizzas, $to_pizzas, $max_order = -1 ) {
		$cartons_text = '';
		$pizzas_text = '';

		if ( ! $to || $to == $from ) {
			// Single quantity
			$cartons_text = sprintf( _n( '%s carton', '%s cartons', $from, 'wc-dynamic-pricing-table' ), $from );
			$pizzas_text = sprintf( _n( '%s pizza', '%s pizzas', $from_pizzas, 'wc-dynamic-pricing-table' ), number_format( $from_pizzas, 0, ',', '.' ) );
		} elseif ( ( $max_order > 0 && $to >= $max_order ) || $to > 1000000 ) {
			// Last tier: max order reached or unlimited (more than)
			$cartons_text = sprintf( __( 'From %s+', 'wc-dynamic-pricing-table' ), $from );
			$pizzas_text = sprintf( __( 'from %s+ pizzas', 'wc-dynamic-pricing-table' ), number_format( $from_pizzas, 0, ',', '.' ) );
		} else {
			// Range
			$cartons_text = sprintf( __( 'From %1$s to %2$s', 'wc-dynamic-pricing-table' ), $from, $to );
			$pizzas_text = sprintf(
				__( 'from %1$s to %2$s pizzas', 'wc-dynamic-pricing-table' ),
				number_format( $from_pizzas, 0, ',', '.' ),
				number_format( $to_pizzas, 0, ',', '.' )
			);
		}

		return array(
			'cartons' => $cartons_text,
			'pizzas' => $pizzas_text,
		);
	}

	/**
	 * Calculate percentage discount from amount
	 *
	 * @param float $discount_amount
	 * @param float $base_price
	 * @return string
	 */
	private function calculate_percentage_discount( $discount_amount, $base_price ) {
		if ( $base_price <= 0 ) {
			return '0';
		}
		return round( ( $discount_amount / $base_price ) * 100 );
	}

	/**
	 * Calculate percentage from fixed price
	 *
	 * @param float $fixed_price
	 * @param float $base_price
	 * @return string
	 */
	private function calculate_percentage_from_fixed_price( $fixed_price, $base_price ) {
		if ( $base_price <= 0 ) {
			return '0';
		}
		$discount = $base_price - $fixed_price;
		return round( ( $discount / $base_price ) * 100 );
	}
}