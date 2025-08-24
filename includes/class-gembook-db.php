<?php
/**
 * GemBook DB Class
 *
 * @package GemBook
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The GemBook DB class.
 */
class GemBook_DB {

	/**
	 * Install GemBook.
	 */
	public static function install() {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'gembook_bookings';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			booking_id bigint(20) NOT NULL AUTO_INCREMENT,
			service_id bigint(20) NOT NULL,
			user_id bigint(20) NOT NULL,
			order_id bigint(20) NOT NULL,
			start_date date NOT NULL,
			end_date date NOT NULL,
			start_time time DEFAULT NULL,
			end_time time DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (booking_id)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}
}