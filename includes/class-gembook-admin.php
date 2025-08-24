<?php
/**
 * Admin functionality for GemBook - HPOS Compatible
 */
class GemBook_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        
        // Add HPOS compatibility for admin order views
        add_action('add_meta_boxes', array($this, 'add_booking_meta_box'));
        add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'display_booking_info_in_admin'));
    }
    
    public function add_admin_menu() {
        add_menu_page(
            __('GemBook Bookings', 'gembook'),
            __('GemBook', 'gembook'),
            'manage_woocommerce',
            'gembook-bookings',
            array($this, 'bookings_page'),
            'dashicons-calendar-alt',
            56
        );
        
        add_submenu_page(
            'gembook-bookings',
            __('All Bookings', 'gembook'),
            __('All Bookings', 'gembook'),
            'manage_woocommerce',
            'gembook-bookings',
            array($this, 'bookings_page')
        );
        
        add_submenu_page(
            'gembook-bookings',
            __('Calendar View', 'gembook'),
            __('Calendar View', 'gembook'),
            'manage_woocommerce',
            'gembook-calendar',
            array($this, 'calendar_page')
        );
    }
    
    public function admin_init() {
        // Admin initialization code
    }
    
    /**
     * Add booking meta box for HPOS compatibility
     */
    public function add_booking_meta_box() {
        $screen = wc_get_container()->get(\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled()
            ? wc_get_page_screen_id('shop-order')
            : 'shop_order';
            
        add_meta_box(
            'gembook-booking-details',
            __('Booking Details', 'gembook'),
            array($this, 'booking_meta_box_content'),
            $screen,
            'normal',
            'default'
        );
    }
    
    /**
     * Display booking info in admin order view
     */
    public function display_booking_info_in_admin($order) {
        $has_bookings = false;
        
        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            
            if ($product && $product->get_type() === 'gembook_service') {
                $has_bookings = true;
                break;
            }
        }
        
        if ($has_bookings) {
            echo '<div class="gembook-admin-booking-info">';
            echo '<h3>' . __('Booking Information', 'gembook') . '</h3>';
            
            foreach ($order->get_items() as $item_id => $item) {
                $product = $item->get_product();
                
                if ($product && $product->get_type() === 'gembook_service') {
                    $this->display_item_booking_details($item, $product);
                }
            }
            
            echo '</div>';
        }
    }
    
    /**
     * Booking meta box content
     */
    public function booking_meta_box_content($post_or_order_object) {
        $order = ($post_or_order_object instanceof WP_Post) ? wc_get_order($post_or_order_object->ID) : $post_or_order_object;
        
        if (!$order) {
            return;
        }
        
        $bookings = GemBook_Database::get_bookings(array('order_id' => $order->get_id()));
        
        if (empty($bookings)) {
            echo '<p>' . __('No bookings found for this order.', 'gembook') . '</p>';
            return;
        }
        
        echo '<table class="widefat">';
        echo '<thead><tr>';
        echo '<th>' . __('Service', 'gembook') . '</th>';
        echo '<th>' . __('Type', 'gembook') . '</th>';
        echo '<th>' . __('Date(s)', 'gembook') . '</th>';
        echo '<th>' . __('Time', 'gembook') . '</th>';
        echo '<th>' . __('Status', 'gembook') . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';
        
        foreach ($bookings as $booking) {
            echo '<tr>';
            echo '<td>' . get_the_title($booking->product_id) . '</td>';
            echo '<td>' . ucfirst(str_replace('_', ' ', $booking->booking_type)) . '</td>';
            echo '<td>';
            echo date('F j, Y', strtotime($booking->start_date));
            if ($booking->end_date && $booking->end_date !== $booking->start_date) {
                echo ' - ' . date('F j, Y', strtotime($booking->end_date));
            }
            echo '</td>';
            echo '<td>';
            if ($booking->start_time) {
                echo date('g:i A', strtotime($booking->start_time));
                if ($booking->end_time) {
                    echo ' - ' . date('g:i A', strtotime($booking->end_time));
                }
            } else {
                echo '-';
            }
            echo '</td>';
            echo '<td><span class="booking-status status-' . $booking->status . '">' . ucfirst($booking->status) . '</span></td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
    }
    
    private function display_item_booking_details($item, $product) {
        echo '<div class="gembook-item-details">';
        echo '<h4>' . $product->get_name() . '</h4>';
        
        $booking_type = $item->get_meta('_gembook_booking_type');
        $start_date = $item->get_meta('_gembook_start_date');
        $end_date = $item->get_meta('_gembook_end_date');
        $start_time = $item->get_meta('_gembook_start_time');
        $duration = $item->get_meta('_gembook_duration');
        
        if ($booking_type) {
            echo '<p><strong>' . __('Type:', 'gembook') . '</strong> ' . ucfirst(str_replace('_', ' ', $booking_type)) . '</p>';
        }
        
        if ($start_date) {
            echo '<p><strong>' . __('Start Date:', 'gembook') . '</strong> ' . date('F j, Y', strtotime($start_date)) . '</p>';
        }
        
        if ($end_date) {
            echo '<p><strong>' . __('End Date:', 'gembook') . '</strong> ' . date('F j, Y', strtotime($end_date)) . '</p>';
        }
        
        if ($start_time) {
            echo '<p><strong>' . __('Start Time:', 'gembook') . '</strong> ' . date('g:i A', strtotime($start_time)) . '</p>';
        }
        
        if ($duration) {
            echo '<p><strong>' . __('Duration:', 'gembook') . '</strong> ' . $duration . ' ' . __('hours', 'gembook') . '</p>';
        }
        
        echo '</div>';
    }
    
    public function bookings_page() {
        $bookings = GemBook_Database::get_bookings();
        ?>
        <div class="wrap">
            <h1><?php _e('GemBook Bookings', 'gembook'); ?></h1>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('ID', 'gembook'); ?></th>
                        <th><?php _e('Service', 'gembook'); ?></th>
                        <th><?php _e('Customer', 'gembook'); ?></th>
                        <th><?php _e('Type', 'gembook'); ?></th>
                        <th><?php _e('Start Date', 'gembook'); ?></th>
                        <th><?php _e('End Date', 'gembook'); ?></th>
                        <th><?php _e('Time', 'gembook'); ?></th>
                        <th><?php _e('Cost', 'gembook'); ?></th>
                        <th><?php _e('Status', 'gembook'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $booking): ?>
                    <tr>
                        <td><?php echo $booking->id; ?></td>
                        <td><?php echo get_the_title($booking->product_id); ?></td>
                        <td>
                            <?php 
                            $user = get_user_by('id', $booking->user_id);
                            echo $user ? $user->display_name : __('Guest', 'gembook');
                            ?>
                        </td>
                        <td><?php echo ucfirst(str_replace('_', ' ', $booking->booking_type)); ?></td>
                        <td><?php echo date('F j, Y', strtotime($booking->start_date)); ?></td>
                        <td><?php echo $booking->end_date ? date('F j, Y', strtotime($booking->end_date)) : '-'; ?></td>
                        <td>
                            <?php 
                            if ($booking->start_time) {
                                echo date('g:i A', strtotime($booking->start_time));
                                if ($booking->end_time) {
                                    echo ' - ' . date('g:i A', strtotime($booking->end_time));
                                }
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td><?php echo wc_price($booking->total_cost); ?></td>
                        <td>
                            <span class="booking-status status-<?php echo $booking->status; ?>">
                                <?php echo ucfirst($booking->status); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    public function calendar_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Booking Calendar', 'gembook'); ?></h1>
            <div id="gembook-calendar"></div>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Calendar implementation would go here
            // You could integrate with FullCalendar.js or similar
            $('#gembook-calendar').html('<p><?php _e("Calendar view coming soon...", "gembook"); ?></p>');
        });
        </script>
        <?php
    }
}