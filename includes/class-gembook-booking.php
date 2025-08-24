<?php
/**
 * GemBook Booking Class
 *
 * @package GemBook
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The GemBook Booking class.
 */
class GemBook_Booking {

	/**
	 * The booking ID.
	 *
	 * @var int
	 */
	public $id = 0;

	/**
	 * The booking data.
	 *
	 * @var array
	 */
	protected $data = array();

	/**
	 * Constructor.
	 *
	 * @param int|object $booking
	 */
	public function __construct( $booking = 0 ) {
		if ( is_numeric( $booking ) && $booking > 0 ) {
			$this->id   = $booking;
			$this->data = $this->get_booking_data( $this->id );
		} elseif ( is_object( $booking ) ) {
			$this->id   = $booking->booking_id;
			$this->data = (array) $booking;
		}
	}

	/**
	 * Get booking data.
	 *
	 * @param int $booking_id
	 * @return array
	 */
	protected function get_booking_data( $booking_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'gembook_bookings';
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE booking_id = %d", $booking_id ), ARRAY_A );
	}

	/**
	 * __get function.
	 *
	 * @param string $key
	 * @return mixed
	 */
	public function __get( $key ) {
		return isset( $this->data[ $key ] ) ? $this->data[ $key ] : null;
	}
}