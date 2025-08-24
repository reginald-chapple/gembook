<?php
/**
 * GemBook Frontend Class
 *
 * @package GemBook
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The GemBook Frontend class.
 */
class GemBook_Frontend {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'booking_form' ) );
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_booking' ), 10, 3 );
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_booking_to_cart_item' ), 10, 2 );
		add_filter( 'woocommerce_get_cart_item_from_session', array( $this, 'get_cart_item_from_session' ), 10, 3 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'display_booking_in_cart' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_booking_to_order_item' ), 10, 4 );
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'create_booking_from_order' ), 10, 3 );
	}

	/**
	 * Display booking form.
	 */
	public function booking_form() {
		global $product;
		if ( 'bookable_service' !== $product->get_type() ) {
			return;
		}
		$booking_type = $product->get_meta( '_booking_type' );
		echo '<div class="gembook-booking-form">';
		if ( 'single-day' === $booking_type ) {
			echo '<p><label for="booking_date">' . __( 'Date', 'gembook' ) . '</label><input type="date" id="booking_date" name="booking_date" class="gembook-date"></p>';
		} elseif ( 'multi-day' === $booking_type ) {
			echo '<p><label for="booking_start_date">' . __( 'Start Date', 'gembook' ) . '</label><input type="date" id="booking_start_date" name="booking_start_date" class="gembook-date"></p>';
			echo '<p><label for="booking_end_date">' . __( 'End Date', 'gembook' ) . '</label><input type="date" id="booking_end_date" name="booking_end_date" class="gembook-date"></p>';
		} elseif ( 'time-based' === $booking_type ) {
			echo '<p><label for="booking_date">' . __( 'Date', 'gembook' ) . '</label><input type="date" id="booking_date" name="booking_date" class="gembook-date"></p>';
			echo '<p><label for="booking_start_time">' . __( 'Start Time', 'gembook' ) . '</label><input type="time" id="booking_start_time" name="booking_start_time" class="gembook-time"></p>';
			echo '<p><label for="booking_end_time">' . __( 'End Time', 'gembook' ) . '</label><input type="time" id="booking_end_time" name="booking_end_time" class="gembook-time"></p>';
		}
		echo '</div>';
	}

	/**
	 * Validate booking.
	 */
	public function validate_booking( $passed, $product_id, $quantity ) {
		$product      = wc_get_product( $product_id );
		$booking_type = $product->get_meta( '_booking_type' );

		if ( 'bookable_service' === $product->get_type() ) {
			if ( 'single-day' === $booking_type && empty( $_POST['booking_date'] ) ) {
				wc_add_notice( __( 'Please select a date.', 'gembook' ), 'error' );
				return false;
			}
			if ( 'multi-day' === $booking_type && ( empty( $_POST['booking_start_date'] ) || empty( $_POST['booking_end_date'] ) ) ) {
				wc_add_notice( __( 'Please select a start and end date.', 'gembook' ), 'error' );
				return false;
			}
			if ( 'time-based' === $booking_type && ( empty( $_POST['booking_date'] ) || empty( $_POST['booking_start_time'] ) || empty( $_POST['booking_end_time'] ) ) ) {
				wc_add_notice( __( 'Please select a date and start/end times.', 'gembook' ), 'error' );
				return false;
			}
		}
		return $passed;
	}

	/**
	 * Add booking data to cart item.
	 */
	public function add_booking_to_cart_item( $cart_item_data, $product_id ) {
		$booking_data = array();
		if ( isset( $_POST['booking_date'] ) ) {
			$booking_data['date'] = sanitize_text_field( $_POST['booking_date'] );
		}
		if ( isset( $_POST['booking_start_date'] ) ) {
			$booking_data['start_date'] = sanitize_text_field( $_POST['booking_start_date'] );
		}
		if ( isset( $_POST['booking_end_date'] ) ) {
			$booking_data['end_date'] = sanitize_text_field( $_POST['booking_end_date'] );
		}
		if ( isset( $_POST['booking_start_time'] ) ) {
			$booking_data['start_time'] = sanitize_text_field( $_POST['booking_start_time'] );
		}
		if ( isset( $_POST['booking_end_time'] ) ) {
			$booking_data['end_time'] = sanitize_text_field( $_POST['booking_end_time'] );
		}

		if ( ! empty( $booking_data ) ) {
			$cart_item_data['gembook_booking'] = $booking_data;
		}
		return $cart_item_data;
	}

	/**
	 * Get cart item from session and set price.
	 */
	public function get_cart_item_from_session( $cart_item, $values, $cart_item_key ) {
		if ( isset( $values['gembook_booking'] ) ) {
			$cart_item['gembook_booking'] = $values['gembook_booking'];
			$cart_item['data']->set_price( $this->calculate_booking_price( $cart_item['data'], $values['gembook_booking'] ) );
		}
		return $cart_item;
	}

	/**
	 * Calculate dynamic price.
	 */
	public function calculate_booking_price( $product, $booking_data ) {
		$base_price     = (float) $product->get_meta( '_booking_base_price' );
		$duration_price = (float) $product->get_meta( '_booking_duration_price' );
		$booking_type   = $product->get_meta( '_booking_type' );

		if ( 'multi-day' === $booking_type && isset( $booking_data['start_date'] ) && isset( $booking_data['end_date'] ) ) {
			$start    = new DateTime( $booking_data['start_date'] );
			$end      = new DateTime( $booking_data['end_date'] );
			$interval = $start->diff( $end );
			$days     = $interval->days + 1;
			return $base_price + ( $duration_price * $days );
		}

		if ( 'time-based' === $booking_type && isset( $booking_data['start_time'] ) && isset( $booking_data['end_time'] ) ) {
			$start    = strtotime( $booking_data['start_time'] );
			$end      = strtotime( $booking_data['end_time'] );
			$hours    = round( ( $end - $start ) / 3600, 2 );
			return $base_price + ( $duration_price * $hours );
		}

		return $base_price;
	}

	/**
	 * Display booking data in cart.
	 */
	public function display_booking_in_cart( $item_data, $cart_item ) {
		if ( isset( $cart_item['gembook_booking'] ) ) {
			$booking_data = $cart_item['gembook_booking'];
			if ( isset( $booking_data['date'] ) ) {
				$item_data[] = array( 'key' => __( 'Date', 'gembook' ), 'value' => $booking_data['date'] );
			}
			if ( isset( $booking_data['start_date'] ) ) {
				$item_data[] = array( 'key' => __( 'From', 'gembook' ), 'value' => $booking_data['start_date'] );
			}
			if ( isset( $booking_data['end_date'] ) ) {
				$item_data[] = array( 'key' => __( 'To', 'gembook' ), 'value' => $booking_data['end_date'] );
			}
			if ( isset( $booking_data['start_time'] ) ) {
				$item_data[] = array( 'key' => __( 'Start Time', 'gembook' ), 'value' => $booking_data['start_time'] );
			}
			if ( isset( $booking_data['end_time'] ) ) {
				$item_data[] = array( 'key' => __( 'End Time', 'gembook' ), 'value' => $booking_data['end_time'] );
			}
		}
		return $item_data;
	}

	/**
	 * Add booking data to order item meta.
	 */
	public function add_booking_to_order_item( $item, $cart_item_key, $values, $order ) {
		if ( isset( $values['gembook_booking'] ) ) {
			$item->add_meta_data( '_gembook_booking_data', $values['gembook_booking'] );
		}
	}

	/**
	 * Create booking record after order is processed.
	 */
	public function create_booking_from_order( $order_id, $posted_data, $order ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'gembook_bookings';

		foreach ( $order->get_items() as $item_id => $item ) {
			$booking_data = $item->get_meta( '_gembook_booking_data' );
			if ( $booking_data ) {
				$product_id = $item->get_product_id();
				$user_id    = $order->get_user_id();

				$start_date = $booking_data['start_date'] ?? $booking_data['date'] ?? null;
				$end_date   = $booking_data['end_date'] ?? $start_date;
				$start_time = $booking_data['start_time'] ?? null;
				$end_time   = $booking_data['end_time'] ?? null;

				$wpdb->insert(
					$table_name,
					array(
						'service_id' => $product_id,
						'user_id'    => $user_id,
						'order_id'   => $order_id,
						'start_date' => $start_date,
						'end_date'   => $end_date,
						'start_time' => $start_time,
						'end_time'   => $end_time,
						'status'     => 'confirmed', // Or use order status
					)
				);
			}
		}
	}
}