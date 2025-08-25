<?php
/**
 * Bookable Service Product Class.
 *
 * @package GemBook
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Product_Bookable_Service extends WC_Product_Simple {

	/**
	 * Get product type.
	 *
	 * @return string
	 */
	public function get_type() {
		return 'bookable_service';
	}

	/**
	 * A bookable service is purchasable if it has a base price.
	 *
	 * @return boolean
	 */
	public function is_purchasable() {
		$is_purchasable = true;
		$base_price     = $this->get_meta( '_booking_base_price', true );

		if ( '' === $base_price ) {
			$is_purchasable = false;
		}

		return apply_filters( 'woocommerce_is_purchasable', $is_purchasable, $this );
	}
}