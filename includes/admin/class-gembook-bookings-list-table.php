<?php
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class GemBook_Bookings_List_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'Booking',
				'plural'   => 'Bookings',
				'ajax'     => false,
			)
		);
	}

	public function get_columns() {
		return array(
			'cb'         => '<input type="checkbox" />',
			'booking_id' => __( 'Booking ID', 'gembook' ),
			'service'    => __( 'Service', 'gembook' ),
			'customer'   => __( 'Customer', 'gembook' ),
			'dates'      => __( 'Dates', 'gembook' ),
			'status'     => __( 'Status', 'gembook' ),
			'order_id'   => __( 'Order', 'gembook' ),
		);
	}

	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="booking[]" value="%s" />', $item['booking_id'] );
	}

	public function column_service( $item ) {
		$product_id = $item['service_id'];
		$product    = wc_get_product( $product_id );
		if ( ! $product ) {
			return __( 'N/A', 'gembook' );
		}
		return sprintf( '<a href="%s">%s</a>', get_edit_post_link( $product_id ), $product->get_name() );
	}

	public function column_customer( $item ) {
		$user = get_user_by( 'id', $item['user_id'] );
		if ( ! $user ) {
			return __( 'Guest', 'gembook' );
		}
		return sprintf( '<a href="%s">%s</a>', get_edit_user_link( $user->ID ), $user->display_name );
	}

	public function column_dates( $item ) {
		$start_date = date_i18n( get_option( 'date_format' ), strtotime( $item['start_date'] ) );
		$end_date   = date_i18n( get_option( 'date_format' ), strtotime( $item['end_date'] ) );

		if ( $item['start_time'] ) {
			$start_date .= ' ' . date_i18n( get_option( 'time_format' ), strtotime( $item['start_time'] ) );
		}
		if ( $item['end_time'] ) {
			$end_date .= ' ' . date_i18n( get_option( 'time_format' ), strtotime( $item['end_time'] ) );
		}

		if ( $start_date === $end_date ) {
			return $start_date;
		}

		return $start_date . ' - ' . $end_date;
	}

	public function column_order_id( $item ) {
		$order_url = get_edit_post_link( $item['order_id'] );
		return sprintf( '<a href="%s">#%s</a>', esc_url( $order_url ), esc_html( $item['order_id'] ) );
	}

	public function column_default( $item, $column_name ) {
		return isset( $item[ $column_name ] ) ? esc_html( $item[ $column_name ] ) : print_r( $item, true );
	}

	public function prepare_items() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'gembook_bookings';

		$per_page     = 20;
		$columns      = $this->get_columns();
		$hidden       = array();
		$sortable     = array();
		$this->_column_headers = array( $columns, $hidden, $sortable );

		$current_page = $this->get_pagenum();
		$offset       = ( $current_page - 1 ) * $per_page;

		$query = "SELECT * FROM {$table_name} ORDER BY booking_id DESC LIMIT {$per_page} OFFSET {$offset}";
		$data  = $wpdb->get_results( $query, ARRAY_A );

		$total_items = $wpdb->get_var( "SELECT COUNT(booking_id) FROM {$table_name}" );

		$this->items = $data;
		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total_items / $per_page ),
			)
		);
	}
}