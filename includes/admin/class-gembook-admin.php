<?php
/**
 * GemBook Admin Class
 *
 * @package GemBook
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The GemBook Admin class.
 */
class GemBook_Admin {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
	}

	/**
	 * Add admin menu.
	 */
	public function admin_menu() {
		add_menu_page(
			__( 'Bookings', 'gembook' ),
			__( 'Bookings', 'gembook' ),
			'manage_woocommerce',
			'gembook-bookings',
			array( $this, 'bookings_page' ),
			'dashicons-calendar-alt',
			56
		);
	}

	/**
	 * Bookings page.
	 */
	public function bookings_page() {
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php _e( 'Bookings', 'gembook' ); ?></h1>
			<hr class="wp-header-end">
			<?php
			$bookings_list_table = new GemBook_Bookings_List_Table();
			$bookings_list_table->prepare_items();
			$bookings_list_table->display();
			?>
		</div>
		<?php
	}
}