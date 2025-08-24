<?php
/**
 * Frontend functionality for GemBook
 */
class GemBook_Frontend {
    
    public function __construct() {
        // Try multiple hooks to ensure the booking form displays
        add_action('woocommerce_before_add_to_cart_button', array($this, 'display_booking_form'));
        add_action('woocommerce_single_product_summary', array($this, 'display_booking_form'), 25);
        add_action('woocommerce_before_single_product_summary', array($this, 'display_booking_form'), 25);
        
        // AJAX handlers
        add_action('wp_ajax_gembook_calculate_price', array($this, 'ajax_calculate_price'));
        add_action('wp_ajax_nopriv_gembook_calculate_price', array($this, 'ajax_calculate_price'));
        add_action('wp_ajax_gembook_check_availability', array($this, 'ajax_check_availability'));
        add_action('wp_ajax_nopriv_gembook_check_availability', array($this, 'ajax_check_availability'));
        
        // Add custom styles and scripts
        add_action('wp_head', array($this, 'add_inline_styles'));
        
        // Handle add to cart button
        add_filter('woocommerce_product_add_to_cart_text', array($this, 'add_to_cart_text'), 10, 2);
        add_filter('woocommerce_product_single_add_to_cart_text', array($this, 'add_to_cart_text'), 10, 2);
    }
    
    public function add_to_cart_text($text, $product) {
        if ($product && $product->get_type() === 'gembook_service') {
            return __('Book Now', 'gembook');
        }
        return $text;
    }
    
    public function display_booking_form() {
        global $product;
        
        // Check if we have a product and it's the right type
        if (!$product || !is_object($product)) {
            return;
        }
        
        if ($product->get_type() !== 'gembook_service') {
            return;
        }
        
        // Prevent multiple displays
        static $displayed = false;
        if ($displayed) {
            return;
        }
        $displayed = true;
        
        $booking_type = $product->get_meta('_gembook_booking_type', true);
        $available_times = $product->get_meta('_gembook_available_times', true);
        
        // Debug output (remove in production)
        if (current_user_can('manage_options')) {
            echo '<!-- GemBook Debug: Product Type = ' . $product->get_type() . ', Booking Type = ' . $booking_type . ' -->';
        }
        
        ?>
        <div id="gembook-booking-form" class="gembook-booking-form" style="display: block; margin: 20px 0; padding: 20px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 5px;">
            <h3><?php _e('Booking Options', 'gembook'); ?></h3>
            
            <form class="cart" action="<?php echo esc_url(apply_filters('woocommerce_add_to_cart_form_action', $product->get_permalink())); ?>" method="post" enctype='multipart/form-data'>
                
                <?php if ($booking_type === 'all' || $booking_type === 'single_day' || $booking_type === 'multi_day'): ?>
                <div class="gembook-field" style="margin-bottom: 15px;">
                    <label for="gembook_start_date" style="display: block; margin-bottom: 5px; font-weight: bold;"><?php _e('Start Date:', 'gembook'); ?></label>
                    <input type="text" id="gembook_start_date" name="gembook_start_date" class="gembook-datepicker" required style="width: 100%; max-width: 300px; padding: 8px 12px; border: 1px solid #ccc; border-radius: 3px;" />
                </div>
                <?php endif; ?>
                
                <?php if ($booking_type === 'all' || $booking_type === 'multi_day'): ?>
                <div class="gembook-field" style="margin-bottom: 15px;">
                    <label for="gembook_end_date" style="display: block; margin-bottom: 5px; font-weight: bold;"><?php _e('End Date:', 'gembook'); ?></label>
                    <input type="text" id="gembook_end_date" name="gembook_end_date" class="gembook-datepicker" style="width: 100%; max-width: 300px; padding: 8px 12px; border: 1px solid #ccc; border-radius: 3px;" />
                </div>
                <?php endif; ?>
                
                <?php if ($booking_type === 'all' || $booking_type === 'time_based'): ?>
                <div class="gembook-field" style="margin-bottom: 15px;">
                    <label for="gembook_start_time" style="display: block; margin-bottom: 5px; font-weight: bold;"><?php _e('Start Time:', 'gembook'); ?></label>
                    <select id="gembook_start_time" name="gembook_start_time" style="width: 100%; max-width: 300px; padding: 8px 12px; border: 1px solid #ccc; border-radius: 3px;">
                        <option value=""><?php _e('Select time...', 'gembook'); ?></option>
                        <?php $this->render_time_options($available_times); ?>
                    </select>
                </div>
                
                <div class="gembook-field" style="margin-bottom: 15px;">
                    <label for="gembook_duration" style="display: block; margin-bottom: 5px; font-weight: bold;"><?php _e('Duration (hours):', 'gembook'); ?></label>
                    <input type="number" id="gembook_duration" name="gembook_duration" min="1" max="24" step="0.5" style="width: 100%; max-width: 300px; padding: 8px 12px; border: 1px solid #ccc; border-radius: 3px;" />
                </div>
                <?php endif; ?>
                
                <?php if ($booking_type === 'all'): ?>
                <div class="gembook-field" style="margin-bottom: 15px;">
                    <label for="gembook_type" style="display: block; margin-bottom: 5px; font-weight: bold;"><?php _e('Booking Type:', 'gembook'); ?></label>
                    <select id="gembook_type" name="gembook_type" required style="width: 100%; max-width: 300px; padding: 8px 12px; border: 1px solid #ccc; border-radius: 3px;">
                        <option value=""><?php _e('Select type...', 'gembook'); ?></option>
                        <option value="single_day"><?php _e('Single Day', 'gembook'); ?></option>
                        <option value="multi_day"><?php _e('Multi Day', 'gembook'); ?></option>
                        <option value="time_based"><?php _e('Time Based', 'gembook'); ?></option>
                    </select>
                </div>
                <?php else: ?>
                <input type="hidden" id="gembook_type" name="gembook_type" value="<?php echo esc_attr($booking_type); ?>" />
                <?php endif; ?>
                
                <div class="gembook-price-display" style="background: #fff; padding: 15px; margin: 15px 0; border-radius: 3px; border: 2px solid #0073aa; text-align: center;">
                    <strong><?php _e('Total Price:', 'gembook'); ?> <span id="gembook-total-price"><?php echo wc_price($product->get_price()); ?></span></strong>
                </div>
                
                <div class="gembook-availability-status" style="margin: 10px 0; padding: 10px; border-radius: 3px; text-align: center; font-weight: bold; min-height: 20px;">
                    <span id="gembook-availability-message"><?php _e('Please select booking options to check availability', 'gembook'); ?></span>
                </div>
                
                <?php do_action('woocommerce_before_add_to_cart_button'); ?>
                
                <div class="quantity" style="margin: 15px 0;">
                    <input type="hidden" name="quantity" value="1" />
                </div>
                
                <button type="submit" name="add-to-cart" value="<?php echo esc_attr($product->get_id()); ?>" class="single_add_to_cart_button button alt" disabled style="width: 100%; max-width: 300px; padding: 12px 24px; font-size: 16px; background: #ccc; color: #666; border: none; border-radius: 3px; cursor: not-allowed;">
                    <?php _e('Select Options', 'gembook'); ?>
                </button>
                
                <?php do_action('woocommerce_after_add_to_cart_button'); ?>
                
            </form>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            console.log('GemBook: Initializing booking form for product ID: <?php echo $product->get_id(); ?>');
            
            var product_id = <?php echo $product->get_id(); ?>;
            var isAvailable = false;
            
            // Initialize datepickers
            $('.gembook-datepicker').datepicker({
                dateFormat: 'yy-mm-dd',
                minDate: 0,
                onSelect: function() {
                    console.log('GemBook: Date selected');
                    setTimeout(function() {
                        calculatePrice();
                        checkAvailability();
                    }, 100);
                }
            });
            
            // Bind change events
            $('#gembook_type, #gembook_start_time, #gembook_duration').change(function() {
                console.log('GemBook: Field changed');
                setTimeout(function() {
                    calculatePrice();
                    checkAvailability();
                }, 100);
            });
            
            // Show/hide fields based on booking type
            $('#gembook_type').change(function() {
                var booking_type = $(this).val();
                console.log('GemBook: Booking type changed to: ' + booking_type);
                
                // Hide all optional fields first
                $('.gembook-field').show();
                
                if (booking_type === 'single_day') {
                    $('#gembook_end_date').closest('.gembook-field').hide();
                    $('#gembook_start_time').closest('.gembook-field').hide();
                    $('#gembook_duration').closest('.gembook-field').hide();
                } else if (booking_type === 'multi_day') {
                    $('#gembook_start_time').closest('.gembook-field').hide();
                    $('#gembook_duration').closest('.gembook-field').hide();
                } else if (booking_type === 'time_based') {
                    $('#gembook_end_date').closest('.gembook-field').hide();
                }
                
                // Recalculate after field changes
                setTimeout(function() {
                    calculatePrice();
                    checkAvailability();
                }, 100);
            }).trigger('change');
            
            function updateAddToCartButton(available) {
                isAvailable = available;
                var button = $('.single_add_to_cart_button');
                
                if (available) {
                    button.prop('disabled', false)
                        .removeClass('disabled')
                        .text('<?php _e('Book Now', 'gembook'); ?>')
                        .css({
                            'background': '#0073aa',
                            'color': '#fff',
                            'cursor': 'pointer'
                        });
                } else {
                    button.prop('disabled', true)
                        .addClass('disabled')
                        .text('<?php _e('Select Options', 'gembook'); ?>')
                        .css({
                            'background': '#ccc',
                            'color': '#666',
                            'cursor': 'not-allowed'
                        });
                }
                
                console.log('GemBook: Add to cart button updated, available:', available);
            }
            
            function calculatePrice() {
                var booking_type = $('#gembook_type').val();
                var start_date = $('#gembook_start_date').val();
                var end_date = $('#gembook_end_date').val();
                var duration = $('#gembook_duration').val();
                
                console.log('GemBook: Calculating price for type: ' + booking_type);
                
                if (!booking_type) {
                    updateAddToCartButton(false);
                    return;
                }
                
                var data = {
                    action: 'gembook_calculate_price',
                    product_id: product_id,
                    booking_type: booking_type,
                    start_date: start_date,
                    end_date: end_date,
                    duration: duration,
                    nonce: '<?php echo wp_create_nonce('gembook_nonce'); ?>'
                };
                
                $.post('<?php echo admin_url('admin-ajax.php'); ?>', data, function(response) {
                    console.log('GemBook: Price calculation response:', response);
                    if (response.success) {
                        $('#gembook-total-price').html(response.data.price_html);
                    }
                }).fail(function(xhr, status, error) {
                    console.error('GemBook: Price calculation failed:', error);
                });
            }
            
            function checkAvailability() {
                var booking_type = $('#gembook_type').val();
                var start_date = $('#gembook_start_date').val();
                var end_date = $('#gembook_end_date').val();
                var start_time = $('#gembook_start_time').val();
                var duration = $('#gembook_duration').val();
                
                console.log('GemBook: Checking availability for:', {
                    booking_type: booking_type,
                    start_date: start_date,
                    end_date: end_date,
                    start_time: start_time,
                    duration: duration
                });
                
                // Basic validation
                if (!booking_type || !start_date) {
                    $('#gembook-availability-message').html('<?php _e('Please select booking options', 'gembook'); ?>');
                    updateAddToCartButton(false);
                    return;
                }
                
                // Additional validation based on booking type
                if (booking_type === 'multi_day' && !end_date) {
                    $('#gembook-availability-message').html('<?php _e('Please select end date', 'gembook'); ?>');
                    updateAddToCartButton(false);
                    return;
                }
                
                if (booking_type === 'time_based' && (!start_time || !duration)) {
                    $('#gembook-availability-message').html('<?php _e('Please select start time and duration', 'gembook'); ?>');
                    updateAddToCartButton(false);
                    return;
                }
                
                // Show checking message
                $('#gembook-availability-message').html('<?php _e('Checking availability...', 'gembook'); ?>').css({
                    'background': '#fff3cd',
                    'color': '#856404',
                    'border': '1px solid #ffeaa7'
                });
                
                var data = {
                    action: 'gembook_check_availability',
                    product_id: product_id,
                    booking_type: booking_type,
                    start_date: start_date,
                    end_date: end_date,
                    start_time: start_time,
                    duration: duration,
                    nonce: '<?php echo wp_create_nonce('gembook_nonce'); ?>'
                };
                
                $.post('<?php echo admin_url('admin-ajax.php'); ?>', data, function(response) {
                    console.log('GemBook: Availability check response:', response);
                    if (response.success) {
                        var messageEl = $('#gembook-availability-message');
                        messageEl.html(response.data.message);
                        
                        updateAddToCartButton(response.data.available);
                        
                        if (response.data.available) {
                            messageEl.css({
                                'background': '#d4edda',
                                'color': '#155724',
                                'border': '1px solid #c3e6cb'
                            });
                        } else {
                            messageEl.css({
                                'background': '#f8d7da',
                                'color': '#721c24',
                                'border': '1px solid #f5c6cb'
                            });
                        }
                    } else {
                        $('#gembook-availability-message').html('<?php _e('Error checking availability', 'gembook'); ?>');
                        updateAddToCartButton(false);
                    }
                }).fail(function(xhr, status, error) {
                    console.error('GemBook: Availability check failed:', error);
                    $('#gembook-availability-message').html('<?php _e('Error checking availability', 'gembook'); ?>');
                    updateAddToCartButton(false);
                });
            }
            
            // Initial setup
            setTimeout(function() {
                if ($('#gembook_type').val()) {
                    calculatePrice();
                    checkAvailability();
                }
            }, 500);
        });
        </script>
        <?php
    }
    
    public function add_inline_styles() {
        if (!is_product()) {
            return;
        }
        
        global $product;
        if (!$product || $product->get_type() !== 'gembook_service') {
            return;
        }
        
        ?>
        <style type="text/css">
        .gembook-booking-form {
            background: #f9f9f9 !important;
            padding: 20px !important;
            margin: 20px 0 !important;
            border-radius: 5px !important;
            border: 1px solid #ddd !important;
            display: block !important;
        }
        
        .gembook-booking-form h3 {
            margin-top: 0 !important;
            color: #333 !important;
        }
        
        .gembook-field {
            margin-bottom: 15px !important;
        }
        
        .gembook-field label {
            display: block !important;
            margin-bottom: 5px !important;
            font-weight: bold !important;
            color: #555 !important;
        }
        
        .gembook-field input,
        .gembook-field select {
            width: 100% !important;
            max-width: 300px !important;
            padding: 8px 12px !important;
            border: 1px solid #ccc !important;
            border-radius: 3px !important;
            font-size: 14px !important;
        }
        
        .gembook-price-display {
            background: #fff !important;
            padding: 15px !important;
            margin: 15px 0 !important;
            border-radius: 3px !important;
            border: 2px solid #0073aa !important;
            text-align: center !important;
        }
        
        .gembook-availability-status {
            margin: 10px 0 !important;
            padding: 10px !important;
            border-radius: 3px !important;
            text-align: center !important;
            font-weight: bold !important;
        }
        
        .ui-datepicker {
            z-index: 9999 !important;
        }
        </style>
        <?php
    }
    
    private function render_time_options($available_times) {
        if (empty($available_times)) {
            // Default time slots
            for ($hour = 9; $hour <= 17; $hour++) {
                $time = sprintf('%02d:00', $hour);
                echo '<option value="' . $time . '">' . $time . '</option>';
            }
        } else {
            $times = explode("\n", $available_times);
            foreach ($times as $time_range) {
                $time_range = trim($time_range);
                if (strpos($time_range, '-') !== false) {
                    list($start, $end) = explode('-', $time_range);
                    $start_hour = intval(substr(trim($start), 0, 2));
                    $end_hour = intval(substr(trim($end), 0, 2));
                    
                    for ($hour = $start_hour; $hour <= $end_hour; $hour++) {
                        $time = sprintf('%02d:00', $hour);
                        echo '<option value="' . $time . '">' . $time . '</option>';
                    }
                } else {
                    // Single time entry
                    $time = trim($time_range);
                    if (preg_match('/^\d{2}:\d{2}$/', $time)) {
                        echo '<option value="' . $time . '">' . $time . '</option>';
                    }
                }
            }
        }
    }
    
    public function ajax_calculate_price() {
        check_ajax_referer('gembook_nonce', 'nonce');
        
        $product_id = intval($_POST['product_id']);
        $booking_type = sanitize_text_field($_POST['booking_type']);
        $start_date = sanitize_text_field($_POST['start_date']);
        $end_date = sanitize_text_field($_POST['end_date']);
        $duration = floatval($_POST['duration']);
        
        $product = wc_get_product($product_id);
        
        if (!$product || $product->get_type() !== 'gembook_service') {
            wp_send_json_error('Invalid product');
        }
        
        $calculated_duration = $duration;
        
        if ($booking_type === 'multi_day' && $start_date && $end_date) {
            $start = new DateTime($start_date);
            $end = new DateTime($end_date);
            $calculated_duration = $end->diff($start)->days + 1;
        }
        
        $price = $product->calculate_price($calculated_duration, $booking_type);
        
        wp_send_json_success(array(
            'price' => $price,
            'price_html' => wc_price($price)
        ));
    }
    
    public function ajax_check_availability() {
        check_ajax_referer('gembook_nonce', 'nonce');
        
        $product_id = intval($_POST['product_id']);
        $booking_type = sanitize_text_field($_POST['booking_type']);
        $start_date = sanitize_text_field($_POST['start_date']);
        $end_date = sanitize_text_field($_POST['end_date']);
        $start_time = sanitize_text_field($_POST['start_time']);
        $duration = floatval($_POST['duration']);
        
        // Basic validation
        if (!$product_id || !$booking_type || !$start_date) {
            wp_send_json_error('Missing required data');
        }
        
        $available = true;
        $message = __('Available', 'gembook');
        
        try {
            if ($booking_type === 'time_based' && $start_date && $start_time && $duration) {
                $start_datetime = new DateTime($start_date . ' ' . $start_time);
                $end_datetime = clone $start_datetime;
                $end_datetime->add(new DateInterval('PT' . ($duration * 60) . 'M'));
                
                $available = GemBook_Database::check_availability(
                    $product_id, 
                    $start_date, 
                    $start_datetime->format('H:i:s'), 
                    $end_datetime->format('H:i:s')
                );
            } else if ($start_date) {
                $dates_to_check = array($start_date);
                
                if ($booking_type === 'multi_day' && $end_date) {
                    $start = new DateTime($start_date);
                    $end = new DateTime($end_date);
                    
                    // Validate that end date is after start date
                    if ($end < $start) {
                        wp_send_json_success(array(
                            'available' => false,
                            'message' => __('End date must be after start date', 'gembook')
                        ));
                        return;
                    }
                    
                    $interval = new DateInterval('P1D');
                    $period = new DatePeriod($start, $interval, $end->add($interval));
                    
                    $dates_to_check = array();
                    foreach ($period as $date) {
                        $dates_to_check[] = $date->format('Y-m-d');
                    }
                }
                
                foreach ($dates_to_check as $date) {
                    if (!GemBook_Database::check_availability($product_id, $date)) {
                        $available = false;
                        break;
                    }
                }
            }
            
            if (!$available) {
                $message = __('Not available for selected dates/times', 'gembook');
            }
            
        } catch (Exception $e) {
            wp_send_json_error('Error checking availability: ' . $e->getMessage());
        }
        
        wp_send_json_success(array(
            'available' => $available,
            'message' => $message
        ));
    }
}