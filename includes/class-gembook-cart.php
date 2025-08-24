<?php
/**
 * Cart functionality for GemBook
 */
class GemBook_Cart {
    
    public function __construct() {
        add_filter('woocommerce_add_to_cart_validation', array($this, 'validate_add_to_cart'), 10, 3);
        add_filter('woocommerce_add_cart_item_data', array($this, 'add_cart_item_data'), 10, 3);
        add_filter('woocommerce_get_cart_item_from_session', array($this, 'get_cart_item_from_session'), 10, 3);
        add_filter('woocommerce_get_item_data', array($this, 'display_cart_item_data'), 10, 2);
        add_action('woocommerce_before_calculate_totals', array($this, 'calculate_cart_totals'));
    }
    
    public function validate_add_to_cart($passed, $product_id, $quantity) {
        $product = wc_get_product($product_id);
        
        if ($product->get_type() !== 'gembook_service') {
            return $passed;
        }
        
        // Validate booking data
        $booking_type = isset($_POST['gembook_type']) ? sanitize_text_field($_POST['gembook_type']) : '';
        $start_date = isset($_POST['gembook_start_date']) ? sanitize_text_field($_POST['gembook_start_date']) : '';
        
        if (empty($booking_type) || empty($start_date)) {
            wc_add_notice(__('Please select booking options.', 'gembook'), 'error');
            return false;
        }
        
        // Check availability
        $available = $this->check_booking_availability($product_id, $_POST);
        
        if (!$available) {
            wc_add_notice(__('Selected dates/times are not available.', 'gembook'), 'error');
            return false;
        }
        
        return $passed;
    }
    
    public function add_cart_item_data($cart_item_data, $product_id, $variation_id) {
        $product = wc_get_product($product_id);
        
        if ($product->get_type() !== 'gembook_service') {
            return $cart_item_data;
        }
        
        $booking_data = array(
            'booking_type' => sanitize_text_field($_POST['gembook_type']),
            'start_date' => sanitize_text_field($_POST['gembook_start_date']),
            'end_date' => isset($_POST['gembook_end_date']) ? sanitize_text_field($_POST['gembook_end_date']) : '',
            'start_time' => isset($_POST['gembook_start_time']) ? sanitize_text_field($_POST['gembook_start_time']) : '',
            'duration' => isset($_POST['gembook_duration']) ? floatval($_POST['gembook_duration']) : 0
        );
        
        $cart_item_data['gembook_data'] = $booking_data;
        $cart_item_data['unique_key'] = md5(microtime() . rand());
        
        return $cart_item_data;
    }
    
    public function get_cart_item_from_session($item, $values, $key) {
        if (array_key_exists('gembook_data', $values)) {
            $item['gembook_data'] = $values['gembook_data'];
        }
        return $item;
    }
    
    public function display_cart_item_data($item_data, $cart_item) {
        if (empty($cart_item['gembook_data'])) {
            return $item_data;
        }
        
        $booking_data = $cart_item['gembook_data'];
        
        $item_data[] = array(
            'key' => __('Booking Type', 'gembook'),
            'value' => ucfirst(str_replace('_', ' ', $booking_data['booking_type']))
        );
        
        $item_data[] = array(
            'key' => __('Start Date', 'gembook'),
            'value' => date('F j, Y', strtotime($booking_data['start_date']))
        );
        
        if (!empty($booking_data['end_date'])) {
            $item_data[] = array(
                'key' => __('End Date', 'gembook'),
                'value' => date('F j, Y', strtotime($booking_data['end_date']))
            );
        }
        
        if (!empty($booking_data['start_time'])) {
            $item_data[] = array(
                'key' => __('Start Time', 'gembook'),
                'value' => $booking_data['start_time']
            );
        }
        
        if (!empty($booking_data['duration'])) {
            $item_data[] = array(
                'key' => __('Duration', 'gembook'),
                'value' => $booking_data['duration'] . ' ' . __('hours', 'gembook')
            );
        }
        
        return $item_data;
    }
    
    public function calculate_cart_totals($cart) {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }
        
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if (empty($cart_item['gembook_data'])) {
                continue;
            }
            
            $product = $cart_item['data'];
            
            if ($product->get_type() !== 'gembook_service') {
                continue;
            }
            
            $booking_data = $cart_item['gembook_data'];
            $duration = $this->calculate_duration($booking_data);
            $price = $product->calculate_price($duration, $booking_data['booking_type']);
            
            $cart_item['data']->set_price($price);
        }
    }
    
    private function calculate_duration($booking_data) {
        $duration = 1;
        
        switch ($booking_data['booking_type']) {
            case 'multi_day':
                if (!empty($booking_data['end_date'])) {
                    $start = new DateTime($booking_data['start_date']);
                    $end = new DateTime($booking_data['end_date']);
                    $duration = $end->diff($start)->days + 1;
                }
                break;
                
            case 'time_based':
                $duration = floatval($booking_data['duration']);
                break;
        }
        
        return $duration;
    }
    
    private function check_booking_availability($product_id, $booking_data) {
        $booking_type = $booking_data['gembook_type'];
        $start_date = $booking_data['gembook_start_date'];
        
        if ($booking_type === 'time_based' && !empty($booking_data['gembook_start_time']) && !empty($booking_data['gembook_duration'])) {
            $start_datetime = new DateTime($start_date . ' ' . $booking_data['gembook_start_time']);
            $end_datetime = clone $start_datetime;
            $end_datetime->add(new DateInterval('PT' . ($booking_data['gembook_duration'] * 60) . 'M'));
            
            return GemBook_Database::check_availability(
                $product_id, 
                $start_date, 
                $start_datetime->format('H:i:s'), 
                $end_datetime->format('H:i:s')
            );
        } else {
            $dates_to_check = array($start_date);
            
            if ($booking_type === 'multi_day' && !empty($booking_data['gembook_end_date'])) {
                $start = new DateTime($start_date);
                $end = new DateTime($booking_data['gembook_end_date']);
                $interval = new DateInterval('P1D');
                $period = new DatePeriod($start, $interval, $end->add($interval));
                
                $dates_to_check = array();
                foreach ($period as $date) {
                    $dates_to_check[] = $date->format('Y-m-d');
                }
            }
            
            foreach ($dates_to_check as $date) {
                if (!GemBook_Database::check_availability($product_id, $date)) {
                    return false;
                }
            }
        }
        
        return true;
    }
}