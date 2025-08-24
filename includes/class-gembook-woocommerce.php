<?php
/**
 * GemBook WooCommerce Class
 *
 * @package GemBook
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The GemBook WooCommerce class.
 */
class GemBook_WooCommerce {

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Add product type
		add_filter( 'product_type_selector', array( $this, 'add_product_type' ) );

		// Add product data tabs
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_product_data_tab' ) );

		// Add product data panels
		add_action( 'woocommerce_product_data_panels', array( $this, 'add_product_data_panels' ) );

		// Save product data
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_data' ) );
	}

	/**
	 * Add "Bookable Service" product type.
	 *
	 * @param array $types
	 * @return array
	 */
	public function add_product_type( $types ) {
		$types['bookable_service'] = __( 'Bookable Service', 'gembook' );
		return $types;
	}

	/**
	 * Add "Booking" product data tab.
	 *
	 * @param array $tabs
	 * @return array
	 */
	public function add_product_data_tab( $tabs ) {
		$tabs['booking'] = array(
			'label'    => __( 'Booking', 'gembook' ),
			'target'   => 'booking_product_data',
			'class'    => array( 'show_if_bookable_service' ),
			'priority' => 21,
		);
		return $tabs;
	}

	/**
	 * Add "Booking" product data panels.
	 */
	public function add_product_data_panels() {
		global $post;

		?>
		<div id="booking_product_data" class="panel woocommerce_options_panel">
			<div class="options_group">
				<?php
				woocommerce_wp_select(
					array(
						'id'      => '_booking_type',
						'label'   => __( 'Booking Type', 'gembook' ),
						'options' => array(
							'single-day' => __( 'Single Day', 'gembook' ),
							'multi-day'  => __( 'Multi Day', 'gembook' ),
							'time-based' => __( 'Time Based', 'gembook' ),
						),
					)
				);

				woocommerce_wp_text_input(
					array(
						'id'          => '_booking_base_price',
						'label'       => __( 'Base Price', 'gembook' ),
						'placeholder' => '0.00',
						'desc_tip'    => 'true',
						'description' => __( 'The base price for the booking.', 'gembook' ),
						'type'        => 'number',
						'custom_attributes' => array(
							'step' => '0.01',
							'min'  => '0',
						),
					)
				);

				woocommerce_wp_text_input(
					array(
						'id'          => '_booking_duration_price',
						'label'       => __( 'Price per Duration', 'gembook' ),
						'placeholder' => '0.00',
						'desc_tip'    => 'true',
						'description' => __( 'The price per day or hour.', 'gembook' ),
						'type'        => 'number',
						'custom_attributes' => array(
							'step' => '0.01',
							'min'  => '0',
						),
					)
				);
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Save product data.
	 *
	 * @param int $post_id
	 */
	public function save_product_data( $post_id ) {
		$product = wc_get_product( $post_id );

		$booking_type = isset( $_POST['_booking_type'] ) ? sanitize_text_field( $_POST['_booking_type'] ) : '';
		$product->update_meta_data( '_booking_type', $booking_type );

		$booking_base_price = isset( $_POST['_booking_base_price'] ) ? sanitize_text_field( $_POST['_booking_base_price'] ) : '';
		$product->update_meta_data( '_booking_base_price', $booking_base_price );

		$booking_duration_price = isset( $_POST['_booking_duration_price'] ) ? sanitize_text_field( $_POST['_booking_duration_price'] ) : '';
		$product->update_meta_data( '_booking_duration_price', $booking_duration_price );

		$product->save();
	}
}