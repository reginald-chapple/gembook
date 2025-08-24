<?php
/**
 * Custom product type for bookable services
 */

// Define the custom product class first, outside of any other class
class WC_Product_GemBook_Service extends WC_Product {
    
    public function __construct($product = 0) {
        $this->product_type = 'gembook_service';
        parent::__construct($product);
    }
    
    public function get_type() {
        return 'gembook_service';
    }
    
    public function is_virtual() {
        return true;
    }
    
    public function is_downloadable() {
        return false;
    }
    
    public function needs_shipping() {
        return false;
    }
    
    public function is_sold_individually() {
        return true;
    }
    
    public function get_booking_type() {
        return $this->get_meta('_gembook_booking_type', true);
    }
    
    public function get_base_price() {
        return $this->get_meta('_gembook_base_price', true);
    }
    
    public function get_duration_pricing() {
        return $this->get_meta('_gembook_duration_pricing', true);
    }
    
    public function calculate_price($duration, $booking_type = 'single_day') {
        $base_price = floatval($this->get_base_price());
        $duration_pricing = $this->get_duration_pricing();
        
        if (empty($duration_pricing) || !is_array($duration_pricing)) {
            return $base_price;
        }
        
        $total_price = $base_price;
        
        switch ($booking_type) {
            case 'multi_day':
                if (isset($duration_pricing['daily_rate'])) {
                    $total_price = $base_price + (($duration - 1) * floatval($duration_pricing['daily_rate']));
                }
                break;
                
            case 'time_based':
                if (isset($duration_pricing['hourly_rate'])) {
                    $total_price = floatval($duration_pricing['hourly_rate']) * $duration;
                }
                break;
        }
        
        return $total_price;
    }
}

// Main product type handler class
class GemBook_Product_Type {
    
    public function __construct() {
        add_filter('product_type_selector', array($this, 'add_product_type'));
        add_filter('woocommerce_product_data_tabs', array($this, 'add_product_data_tab'));
        add_action('woocommerce_product_data_panels', array($this, 'add_product_data_panel'));
        add_action('woocommerce_process_product_meta', array($this, 'save_product_data'));
        add_action('init', array($this, 'register_product_type'));
        add_filter('woocommerce_product_class', array($this, 'woocommerce_product_class'), 10, 2);
    }
    
    public function add_product_type($types) {
        $types['gembook_service'] = __('Bookable Service', 'gembook');
        return $types;
    }
    
    public function register_product_type() {
        // The class is already defined above, so we just need to register it with WooCommerce
        // This method can be used for any additional registration logic if needed
    }
    
    public function woocommerce_product_class($classname, $product_type) {
        if ($product_type === 'gembook_service') {
            $classname = 'WC_Product_GemBook_Service';
        }
        return $classname;
    }
    
    public function add_product_data_tab($tabs) {
        $tabs['gembook'] = array(
            'label' => __('Booking Options', 'gembook'),
            'target' => 'gembook_product_data',
            'class' => array('show_if_gembook_service'),
        );
        return $tabs;
    }
    
    public function add_product_data_panel() {
        global $post;
        ?>
        <div id="gembook_product_data" class="panel woocommerce_options_panel">
            <div class="options_group">
                <?php
                woocommerce_wp_select(array(
                    'id' => '_gembook_booking_type',
                    'label' => __('Booking Type', 'gembook'),
                    'options' => array(
                        'single_day' => __('Single Day', 'gembook'),
                        'multi_day' => __('Multi Day', 'gembook'),
                        'time_based' => __('Time Based', 'gembook'),
                        'all' => __('All Types', 'gembook')
                    ),
                    'desc_tip' => true,
                    'description' => __('Select the type of booking for this service.', 'gembook')
                ));
                
                woocommerce_wp_text_input(array(
                    'id' => '_gembook_base_price',
                    'label' => __('Base Price', 'gembook'),
                    'type' => 'number',
                    'custom_attributes' => array(
                        'step' => '0.01',
                        'min' => '0'
                    ),
                    'desc_tip' => true,
                    'description' => __('Base price for the service.', 'gembook')
                ));
                
                woocommerce_wp_text_input(array(
                    'id' => '_gembook_duration_pricing_daily',
                    'label' => __('Daily Rate (Multi-day)', 'gembook'),
                    'type' => 'number',
                    'custom_attributes' => array(
                        'step' => '0.01',
                        'min' => '0'
                    ),
                    'desc_tip' => true,
                    'description' => __('Additional daily rate for multi-day bookings.', 'gembook')
                ));
                
                woocommerce_wp_text_input(array(
                    'id' => '_gembook_duration_pricing_hourly',
                    'label' => __('Hourly Rate (Time-based)', 'gembook'),
                    'type' => 'number',
                    'custom_attributes' => array(
                        'step' => '0.01',
                        'min' => '0'
                    ),
                    'desc_tip' => true,
                    'description' => __('Hourly rate for time-based bookings.', 'gembook')
                ));
                
                woocommerce_wp_text_input(array(
                    'id' => '_gembook_max_bookings',
                    'label' => __('Max Concurrent Bookings', 'gembook'),
                    'type' => 'number',
                    'custom_attributes' => array(
                        'min' => '1'
                    ),
                    'desc_tip' => true,
                    'description' => __('Maximum number of bookings allowed at the same time.', 'gembook')
                ));
                
                woocommerce_wp_textarea_input(array(
                    'id' => '_gembook_available_times',
                    'label' => __('Available Times', 'gembook'),
                    'desc_tip' => true,
                    'description' => __('Enter available time slots (one per line) in format HH:MM-HH:MM, e.g., 09:00-17:00', 'gembook')
                ));
                ?>
            </div>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('select#product-type').change(function() {
                var product_type = $(this).val();
                if (product_type == 'gembook_service') {
                    $('.show_if_gembook_service').show();
                    $('.hide_if_gembook_service').hide();
                    $('#_virtual').prop('checked', true);
                    $('#_downloadable').prop('checked', false);
                } else {
                    $('.show_if_gembook_service').hide();
                    $('.hide_if_gembook_service').show();
                }
            }).change();
        });
        </script>
        <?php
    }
    
    public function save_product_data($post_id) {
        $booking_type = isset($_POST['_gembook_booking_type']) ? sanitize_text_field($_POST['_gembook_booking_type']) : '';
        $base_price = isset($_POST['_gembook_base_price']) ? floatval($_POST['_gembook_base_price']) : 0;
        $daily_rate = isset($_POST['_gembook_duration_pricing_daily']) ? floatval($_POST['_gembook_duration_pricing_daily']) : 0;
        $hourly_rate = isset($_POST['_gembook_duration_pricing_hourly']) ? floatval($_POST['_gembook_duration_pricing_hourly']) : 0;
        $max_bookings = isset($_POST['_gembook_max_bookings']) ? intval($_POST['_gembook_max_bookings']) : 1;
        $available_times = isset($_POST['_gembook_available_times']) ? sanitize_textarea_field($_POST['_gembook_available_times']) : '';
        
        update_post_meta($post_id, '_gembook_booking_type', $booking_type);
        update_post_meta($post_id, '_gembook_base_price', $base_price);
        update_post_meta($post_id, '_gembook_duration_pricing', array(
            'daily_rate' => $daily_rate,
            'hourly_rate' => $hourly_rate
        ));
        update_post_meta($post_id, '_gembook_max_bookings', $max_bookings);
        update_post_meta($post_id, '_gembook_available_times', $available_times);
        
        // Set the regular price to base price
        update_post_meta($post_id, '_regular_price', $base_price);
        update_post_meta($post_id, '_price', $base_price);
        
        // Set virtual and manage stock for bookable services
        if ($booking_type) {
            update_post_meta($post_id, '_virtual', 'yes');
            update_post_meta($post_id, '_downloadable', 'no');
            update_post_meta($post_id, '_sold_individually', 'yes');
        }
    }
}