<?php
/**
 * Order functionality for GemBook - HPOS Compatible
 */
class GemBook_Order {
    
    public function __construct() {
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'save_order_item_meta'), 10, 4);
        add_action('woocommerce_order_status_completed', array($this, 'create_booking_on_order_complete'));
        add_action('woocommerce_order_status_processing', array($this, 'create_booking_on_order_complete'));
        add_filter('woocommerce_order_item_display_meta_key', array($this, 'display_meta_key'), 10, 3);
        add_filter('woocommerce_order_item_display_meta_value', array($this, 'display_meta_value'), 10, 3);
    }
    
    public function save_order_item_meta($item, $cart_item_key, $values, $order) {
        if (empty($values['gembook_data'])) {
            return;
        }
        
        $booking_data = $values['gembook_data'];
        
        foreach ($booking_data as $key => $value) {
            if (!empty($value)) {
                $item->add_meta_data('_gembook_' . $key, $value);
            }
        }
    }
    
    public function create_booking_on_order_complete($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return;
        }
        
        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            
            if (!$product || $product->get_type() !== 'gembook_service') {
                continue;
            }
            
            // Check if booking already exists using HPOS-compatible method
            $existing_booking = $this->get_order_meta($order, '_gembook_booking_' . $item_id);
            if ($existing_booking) {
                continue;
            }
            
            $booking_data = array(
                'order_id' => $order_id,
                'product_id' => $product->get_id(),
                'user_id' => $order->get_user_id(),
                'booking_type' => $item->get_meta('_gembook_booking_type'),
                'start_date' => $item->get_meta('_gembook_start_date'),
                'end_date' => $item->get_meta('_gembook_end_date'),
                'start_time' => $item->get_meta('_gembook_start_time'),
                'end_time' => $this->calculate_end_time($item),
                'duration' => $this->calculate_duration_from_item($item),
                'total_cost' => $item->get_total(),
                'status' => 'confirmed'
            );
            
            $booking_id = GemBook_Database::create_booking($booking_data);
            
            if ($booking_id) {
                $this->update_order_meta($order, '_gembook_booking_' . $item_id, $booking_id);
                
                // Send confirmation email
                $this->send_booking_confirmation($order, $booking_data);
            }
        }
    }
    
    /**
     * HPOS-compatible method to get order meta
     */
    private function get_order_meta($order, $key) {
        if (method_exists($order, 'get_meta')) {
            return $order->get_meta($key, true);
        }
        // Fallback for older versions
        return get_post_meta($order->get_id(), $key, true);
    }
    
    /**
     * HPOS-compatible method to update order meta
     */
    private function update_order_meta($order, $key, $value) {
        if (method_exists($order, 'update_meta_data')) {
            $order->update_meta_data($key, $value);
            $order->save();
        } else {
            // Fallback for older versions
            update_post_meta($order->get_id(), $key, $value);
        }
    }
    
    private function calculate_end_time($item) {
        $start_time = $item->get_meta('_gembook_start_time');
        $duration = $item->get_meta('_gembook_duration');
        
        if (empty($start_time) || empty($duration)) {
            return null;
        }
        
        $start_datetime = new DateTime($start_time);
        $start_datetime->add(new DateInterval('PT' . ($duration * 60) . 'M'));
        
        return $start_datetime->format('H:i:s');
    }
    
    private function calculate_duration_from_item($item) {
        $booking_type = $item->get_meta('_gembook_booking_type');
        $duration = 1;
        
        switch ($booking_type) {
            case 'multi_day':
                $start_date = $item->get_meta('_gembook_start_date');
                $end_date = $item->get_meta('_gembook_end_date');
                if ($start_date && $end_date) {
                    $start = new DateTime($start_date);
                    $end = new DateTime($end_date);
                    $duration = $end->diff($start)->days + 1;
                }
                break;
                
            case 'time_based':
                $duration = floatval($item->get_meta('_gembook_duration'));
                break;
        }
        
        return $duration;
    }
    
    public function display_meta_key($display_key, $meta, $item) {
        $gembook_keys = array(
            '_gembook_booking_type' => __('Booking Type', 'gembook'),
            '_gembook_start_date' => __('Start Date', 'gembook'),
            '_gembook_end_date' => __('End Date', 'gembook'),
            '_gembook_start_time' => __('Start Time', 'gembook'),
            '_gembook_duration' => __('Duration', 'gembook')
        );
        
        if (array_key_exists($meta->key, $gembook_keys)) {
            return $gembook_keys[$meta->key];
        }
        
        return $display_key;
    }
    
    public function display_meta_value($display_value, $meta, $item) {
        switch ($meta->key) {
            case '_gembook_booking_type':
                return ucfirst(str_replace('_', ' ', $display_value));
                
            case '_gembook_start_date':
            case '_gembook_end_date':
                return date('F j, Y', strtotime($display_value));
                
            case '_gembook_duration':
                return $display_value . ' ' . __('hours', 'gembook');
        }
        
        return $display_value;
    }
    
    private function send_booking_confirmation($order, $booking_data) {
        $to = $order->get_billing_email();
        $subject = sprintf(__('Booking Confirmation - Order #%s', 'gembook'), $order->get_order_number());
        
        $message = sprintf(
            __('Your booking has been confirmed for %s on %s.', 'gembook'),
            get_the_title($booking_data['product_id']),
            date('F j, Y', strtotime($booking_data['start_date']))
        );
        
        if ($booking_data['start_time']) {
            $message .= sprintf(__(' Start time: %s', 'gembook'), $booking_data['start_time']);
        }
        
        if ($booking_data['end_date'] && $booking_data['end_date'] !== $booking_data['start_date']) {
            $message .= sprintf(__(' End date: %s', 'gembook'), date('F j, Y', strtotime($booking_data['end_date'])));
        }
        
        wp_mail($to, $subject, $message);
    }
}