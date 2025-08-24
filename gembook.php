<?php
/**
 * Plugin Name: GemBook - WooCommerce Services Booking
 * Description: Create bookable services with single-day, multi-day, and time-based bookings. Dynamic pricing based on duration. Integrates with WooCommerce.
 * Version: 1.0.0
 * Author: Reginald Chapple
 * Requires Plugins: woocommerce
 * Text Domain: gembook
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('GemBook')) {

    final class GemBook {
        const VERSION = '1.0.0';
        const CPT_BOOKING = 'gembook_booking';
        const PRODUCT_TYPE = 'gembook_service';
        const NONCE_ACTION = 'gembook_booking_nonce_action';
        const NONCE_NAME = 'gembook_booking_nonce';

        private static $instance = null;

        public static function instance() {
            if (is_null(self::$instance)) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function __construct() {
            // Ensure WooCommerce is active
            add_action('plugins_loaded', [$this, 'check_dependencies'], 1);

            // Init hooks
            add_action('init', [$this, 'register_booking_cpt']);
            register_activation_hook(__FILE__, [$this, 'on_activation']);
            register_deactivation_hook(__FILE__, [$this, 'on_deactivation']);

            // Product type setup
            add_filter('product_type_selector', [$this, 'register_product_type']);
            add_filter('woocommerce_product_class', [$this, 'map_product_class'], 10, 2);

            // Admin fields
            add_filter('woocommerce_product_data_tabs', [$this, 'add_product_data_tab']);
            add_action('woocommerce_product_data_panels', [$this, 'render_product_data_panel']);
            add_action('woocommerce_admin_process_product_object', [$this, 'save_product_data']);

            // Single product - add to cart UI for this product type
            add_action('woocommerce_' . self::PRODUCT_TYPE . '_add_to_cart', [$this, 'render_add_to_cart'], 30);

            // Enqueue
            add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

            // Cart/Checkout integration
            add_filter('woocommerce_add_to_cart_validation', [$this, 'validate_booking_on_add_to_cart'], 10, 4);
            add_filter('woocommerce_add_cart_item_data', [$this, 'capture_cart_item_data'], 10, 3);
            add_action('woocommerce_before_calculate_totals', [$this, 'apply_dynamic_pricing']);
            add_filter('woocommerce_get_item_data', [$this, 'display_cart_item_data'], 10, 2);
            add_action('woocommerce_checkout_create_order_line_item', [$this, 'add_order_item_meta'], 10, 4);

            // Create/maintain booking records on order status changes
            add_action('woocommerce_order_status_processing', [$this, 'create_or_update_bookings_from_order']);
            add_action('woocommerce_order_status_completed',  [$this, 'create_or_update_bookings_from_order']);
            add_action('woocommerce_order_status_on-hold',     [$this, 'create_or_update_bookings_from_order']);
            add_action('woocommerce_order_status_cancelled',   [$this, 'release_bookings_from_order']);
            add_action('woocommerce_order_status_refunded',    [$this, 'release_bookings_from_order']);
            add_action('woocommerce_order_status_failed',      [$this, 'release_bookings_from_order']);
        }

        public function check_dependencies() {
            if (!class_exists('WooCommerce')) {
                add_action('admin_notices', function () {
                    echo '<div class="notice notice-error"><p>GemBook requires WooCommerce to be installed and active.</p></div>';
                });
            }
        }

        public function on_activation() {
            $this->register_booking_cpt();
            flush_rewrite_rules(false);
        }

        public function on_deactivation() {
            flush_rewrite_rules(false);
        }

        public function register_booking_cpt() {
            $labels = [
                'name'               => __('GemBook Bookings', 'gembook'),
                'singular_name'      => __('GemBook Booking', 'gembook'),
                'add_new'            => __('Add New Booking', 'gembook'),
                'add_new_item'       => __('Add New Booking', 'gembook'),
                'edit_item'          => __('Edit Booking', 'gembook'),
                'new_item'           => __('New Booking', 'gembook'),
                'view_item'          => __('View Booking', 'gembook'),
                'search_items'       => __('Search Bookings', 'gembook'),
                'not_found'          => __('No bookings found', 'gembook'),
                'not_found_in_trash' => __('No bookings found in Trash', 'gembook'),
                'menu_name'          => __('GemBook Bookings', 'gembook'),
            ];

            register_post_type(self::CPT_BOOKING, [
                'labels'             => $labels,
                'public'             => false,
                'show_ui'            => true,
                'show_in_menu'       => 'woocommerce',
                'capability_type'    => 'post',
                'hierarchical'       => false,
                'supports'           => ['title'],
                'rewrite'            => false,
            ]);
        }

        // Product type registration
        public function register_product_type($types) {
            $types[self::PRODUCT_TYPE] = __('GemBook Service', 'gembook');
            return $types;
        }

        public function map_product_class($classname, $product_type) {
            if ($product_type === self::PRODUCT_TYPE) {
                return 'WC_Product_GemBook_Service';
            }
            return $classname;
        }

        // Admin data tab/panel
        public function add_product_data_tab($tabs) {
            $tabs['gembook'] = [
                'label'  => __('GemBook', 'gembook'),
                'target' => 'gembook_product_data',
                'class'  => ['show_if_' . self::PRODUCT_TYPE],
                'priority' => 21,
            ];
            return $tabs;
        }

        public function render_product_data_panel() {
            global $post;

            echo '<div id="gembook_product_data" class="panel woocommerce_options_panel hidden">';

            // Booking type
            woocommerce_wp_select([
                'id'          => '_gembook_booking_type',
                'label'       => __('Booking Type', 'gembook'),
                'options'     => [
                    'single_day' => __('Single Day', 'gembook'),
                    'multi_day'  => __('Multi Day', 'gembook'),
                    'time_based' => __('Time Based (hours)', 'gembook'),
                ],
                'desc_tip'    => true,
                'description' => __('Select the type of booking this service supports.', 'gembook'),
            ]);

            // Pricing
            woocommerce_wp_text_input([
                'id'          => '_gembook_price_per_day',
                'label'       => __('Price per Day', 'gembook'),
                'data_type'   => 'price',
                'desc_tip'    => true,
                'description' => __('Used for Single Day and Multi Day bookings.', 'gembook'),
            ]);
            woocommerce_wp_text_input([
                'id'          => '_gembook_price_per_hour',
                'label'       => __('Price per Hour', 'gembook'),
                'data_type'   => 'price',
                'desc_tip'    => true,
                'description' => __('Used for Time Based bookings.', 'gembook'),
            ]);

            // Constraints
            echo '<p><strong>' . esc_html__('Constraints', 'gembook') . '</strong></p>';

            woocommerce_wp_text_input([
                'id'          => '_gembook_min_days',
                'label'       => __('Minimum Days', 'gembook'),
                'type'        => 'number',
                'custom_attributes' => ['min' => '1', 'step' => '1'],
                'description' => __('Applied to Multi Day bookings.', 'gembook'),
                'desc_tip'    => true,
            ]);
            woocommerce_wp_text_input([
                'id'          => '_gembook_max_days',
                'label'       => __('Maximum Days', 'gembook'),
                'type'        => 'number',
                'custom_attributes' => ['min' => '1', 'step' => '1'],
                'description' => __('Applied to Multi Day bookings.', 'gembook'),
                'desc_tip'    => true,
            ]);

            woocommerce_wp_text_input([
                'id'          => '_gembook_min_hours',
                'label'       => __('Minimum Hours', 'gembook'),
                'type'        => 'number',
                'custom_attributes' => ['min' => '1', 'step' => '1'],
                'description' => __('Applied to Time Based bookings.', 'gembook'),
                'desc_tip'    => true,
            ]);
            woocommerce_wp_text_input([
                'id'          => '_gembook_max_hours',
                'label'       => __('Maximum Hours', 'gembook'),
                'type'        => 'number',
                'custom_attributes' => ['min' => '1', 'step' => '1'],
                'description' => __('Applied to Time Based bookings.', 'gembook'),
                'desc_tip'    => true,
            ]);
            woocommerce_wp_text_input([
                'id'          => '_gembook_time_increment',
                'label'       => __('Time Increment (minutes)', 'gembook'),
                'type'        => 'number',
                'custom_attributes' => ['min' => '5', 'step' => '5'],
                'description' => __('Round durations to this increment for Time Based bookings.', 'gembook'),
                'desc_tip'    => true,
            ]);

            woocommerce_wp_checkbox([
                'id'          => '_gembook_reserve_on_hold',
                'label'       => __('Reserve on “On-hold”', 'gembook'),
                'description' => __('Mark slots reserved when an order is on-hold (recommended).', 'gembook'),
            ]);

            echo '</div>';
        }

        public function save_product_data($product) {
            if ($product->get_type() !== self::PRODUCT_TYPE) {
                return;
            }

            $fields = [
                '_gembook_booking_type',
                '_gembook_price_per_day',
                '_gembook_price_per_hour',
                '_gembook_min_days',
                '_gembook_max_days',
                '_gembook_min_hours',
                '_gembook_max_hours',
                '_gembook_time_increment',
                '_gembook_reserve_on_hold',
            ];

            foreach ($fields as $field) {
                $val = isset($_POST[$field]) ? wc_clean(wp_unslash($_POST[$field])) : '';
                // Normalize checkbox
                if ($field === '_gembook_reserve_on_hold') {
                    $val = isset($_POST[$field]) ? 'yes' : 'no';
                }
                $product->update_meta_data($field, $val);
            }

            // Force virtual + sold individually for services
            $product->set_virtual(true);
            $product->set_sold_individually(true);
        }

        // Frontend: Add-to-cart form for our product type
        public function render_add_to_cart() {
            global $product;
            if (!$product || $product->get_type() !== self::PRODUCT_TYPE) {
                return;
            }

            $booking_type = $product->get_meta('_gembook_booking_type') ?: 'single_day';

            wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);

            echo '<div class="gembook-fields" data-booking-type="' . esc_attr($booking_type) . '">';

            echo '<p class="form-row form-row-wide gembook-field gembook-type-single_day gembook-type-time_based">';
            echo '<label for="gembook_date">' . esc_html__('Date', 'gembook') . ' <span class="required">*</span></label>';
            echo '<input type="text" class="input-text" id="gembook_date" name="gembook_date" placeholder="YYYY-MM-DD" autocomplete="off" />';
            echo '</p>';

            echo '<p class="form-row form-row-first gembook-field gembook-type-multi_day">';
            echo '<label for="gembook_start_date">' . esc_html__('Start Date', 'gembook') . ' <span class="required">*</span></label>';
            echo '<input type="text" class="input-text" id="gembook_start_date" name="gembook_start_date" placeholder="YYYY-MM-DD" autocomplete="off" />';
            echo '</p>';

            echo '<p class="form-row form-row-last gembook-field gembook-type-multi_day">';
            echo '<label for="gembook_end_date">' . esc_html__('End Date', 'gembook') . ' <span class="required">*</span></label>';
            echo '<input type="text" class="input-text" id="gembook_end_date" name="gembook_end_date" placeholder="YYYY-MM-DD" autocomplete="off" />';
            echo '</p>';

            echo '<div class="clear"></div>';

            echo '<p class="form-row form-row-first gembook-field gembook-type-time_based">';
            echo '<label for="gembook_start_time">' . esc_html__('Start Time', 'gembook') . ' <span class="required">*</span></label>';
            echo '<input type="time" class="input-text" id="gembook_start_time" name="gembook_start_time" step="900" />';
            echo '</p>';

            echo '<p class="form-row form-row-last gembook-field gembook-type-time_based">';
            echo '<label for="gembook_end_time">' . esc_html__('End Time', 'gembook') . ' <span class="required">*</span></label>';
            echo '<input type="time" class="input-text" id="gembook_end_time" name="gembook_end_time" step="900" />';
            echo '</p>';

            echo '<input type="hidden" name="gembook_booking_type" value="' . esc_attr($booking_type) . '" />';

            echo '</div>';

            // Quantity is not used; sold individually; rely on default button
            echo wc_get_stock_html($product);
            do_action('woocommerce_before_add_to_cart_button');
            echo '<button type="submit" name="add-to-cart" value="' . esc_attr($product->get_id()) . '" class="single_add_to_cart_button button alt">';
            echo esc_html($product->single_add_to_cart_text());
            echo '</button>';
            do_action('woocommerce_after_add_to_cart_button');
        }

        public function enqueue_frontend_assets() {
            if (!is_product()) {
                return;
            }
            global $product;
            if (!$product || $product->get_type() !== self::PRODUCT_TYPE) {
                return;
            }
            // jQuery UI datepicker
            wp_enqueue_script('jquery-ui-datepicker');
            wp_enqueue_style('jquery-ui-style', 'https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.css', [], '1.12.1');

            $inline_js = <<<JS
jQuery(function($){
    function toggleFields() {
        var container = $('.gembook-fields');
        if (!container.length) return;
        var type = container.data('booking-type');
        container.find('.gembook-field').hide();
        container.find('.gembook-type-' + type).show();
    }
    $('#gembook_date, #gembook_start_date, #gembook_end_date').datepicker({
        dateFormat: 'yy-mm-dd',
        minDate: 0
    });
    toggleFields();
});
JS;
            wp_add_inline_script('jquery-ui-datepicker', $inline_js);
        }

        public function enqueue_admin_assets($hook) {
            if ($hook !== 'post.php' && $hook !== 'post-new.php') return;
            $screen = get_current_screen();
            if (!$screen || $screen->id !== 'product') return;

            $inline_admin_js = <<<JS
jQuery(function($){
    function toggleGemBookFields() {
        var type = $('#_gembook_booking_type').val();
        // No complex show/hide here; fields are generic but description explains usage
    }
    $(document).on('change', '#_gembook_booking_type', toggleGemBookFields);
    toggleGemBookFields();
});
JS;
            wp_register_script('gembook-admin', '', [], false, true);
            wp_enqueue_script('gembook-admin');
            wp_add_inline_script('gembook-admin', $inline_admin_js);
        }

        // Validation before adding to cart
        public function validate_booking_on_add_to_cart($passed, $product_id, $quantity, $variation_id = null) {
            $product = wc_get_product($product_id);
            if (!$product || $product->get_type() !== self::PRODUCT_TYPE) {
                return $passed;
            }

            // Nonce
            if (!isset($_POST[self::NONCE_NAME]) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE_NAME])), self::NONCE_ACTION)) {
                wc_add_notice(__('Security check failed. Please try again.', 'gembook'), 'error');
                return false;
            }

            $booking_type = isset($_POST['gembook_booking_type']) ? sanitize_text_field(wp_unslash($_POST['gembook_booking_type'])) : 'single_day';

            try {
                $range = $this->parse_and_validate_input_range($product, $booking_type, $_POST);
            } catch (\Exception $e) {
                wc_add_notice($e->getMessage(), 'error');
                return false;
            }

            // Check availability vs existing bookings
            if (!$this->is_range_available($product_id, $range['start_ts'], $range['end_ts'])) {
                wc_add_notice(__('Selected time range is not available. Please choose a different slot.', 'gembook'), 'error');
                return false;
            }

            // Also check against items already in cart for same product
            foreach (WC()->cart->get_cart() as $item) {
                if ((int)$item['product_id'] === (int)$product_id && isset($item['gembook'])) {
                    $existing = $item['gembook'];
                    if ($this->ranges_overlap($range['start_ts'], $range['end_ts'], $existing['start_ts'], $existing['end_ts'])) {
                        wc_add_notice(__('You already have an overlapping booking for this service in your cart.', 'gembook'), 'error');
                        return false;
                    }
                }
            }

            return $passed;
        }

        public function capture_cart_item_data($cart_item_data, $product_id, $variation_id) {
            $product = wc_get_product($product_id);
            if (!$product || $product->get_type() !== self::PRODUCT_TYPE) {
                return $cart_item_data;
            }

            $booking_type = isset($_POST['gembook_booking_type']) ? sanitize_text_field(wp_unslash($_POST['gembook_booking_type'])) : 'single_day';
            try {
                $range = $this->parse_and_validate_input_range($product, $booking_type, $_POST);
            } catch (\Exception $e) {
                return $cart_item_data; // already handled in validation
            }

            $cart_item_data['gembook'] = [
                'booking_type' => $booking_type,
                'start_ts'     => $range['start_ts'],
                'end_ts'       => $range['end_ts'],
                'units'        => $range['units'],
                'unit'         => $range['unit'],
                'display'      => $range['display'],
            ];

            // Ensure unique key so Woo won't merge items
            $cart_item_data['unique_key'] = md5(json_encode($cart_item_data['gembook']) . microtime(true));
            return $cart_item_data;
        }

        public function apply_dynamic_pricing($cart) {
            if (is_admin() && !defined('DOING_AJAX')) return;
            if (did_action('woocommerce_before_calculate_totals') >= 2) return;

            foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
                if (!isset($cart_item['gembook'])) continue;
                $product = $cart_item['data'];
                if (!$product || $product->get_type() !== self::PRODUCT_TYPE) continue;

                $meta = $cart_item['gembook'];
                $price = $this->calculate_price_for_range($product, $meta);
                if ($price !== null && $price >= 0) {
                    $product->set_price($price);
                }
            }
        }

        public function display_cart_item_data($item_data, $cart_item) {
            if (isset($cart_item['gembook'])) {
                $item_data[] = [
                    'key'   => __('Booking', 'gembook'),
                    'value' => wp_kses_post($cart_item['gembook']['display']),
                ];
            }
            return $item_data;
        }

        public function add_order_item_meta($item, $cart_item_key, $values, $order) {
            if (isset($values['gembook'])) {
                $meta = $values['gembook'];
                $item->add_meta_data(__('Booking Type', 'gembook'), ucfirst(str_replace('_', ' ', $meta['booking_type'])));
                $item->add_meta_data(__('Start', 'gembook'), $this->format_ts_local($meta['start_ts']));
                $item->add_meta_data(__('End', 'gembook'), $this->format_ts_local($meta['end_ts']));
                $item->add_meta_data(__('Units', 'gembook'), $meta['units'] . ' ' . $meta['unit'] . (intval($meta['units']) === 1 ? '' : 's'));
            }
        }

        public function create_or_update_bookings_from_order($order_id) {
            $order = wc_get_order($order_id);
            if (!$order) return;

            foreach ($order->get_items() as $item_id => $item) {
                $product = $item->get_product();
                if (!$product || $product->get_type() !== self::PRODUCT_TYPE) continue;

                $start = $this->parse_meta_ts($item->get_meta(__('Start', 'gembook')));
                $end   = $this->parse_meta_ts($item->get_meta(__('End', 'gembook')));

                if (!$start || !$end) continue;

                // Find existing booking for this order item or create new
                $existing = get_posts([
                    'post_type'   => self::CPT_BOOKING,
                    'post_status' => 'any',
                    'numberposts' => 1,
                    'meta_query'  => [
                        ['key' => '_gembook_order_id', 'value' => $order_id],
                        ['key' => '_gembook_order_item_id', 'value' => $item_id],
                    ],
                    'fields' => 'ids',
                ]);

                $status = 'reserved'; // internal status
                $title = sprintf('Booking #%s - %s', $order->get_order_number(), $product->get_name());

                $postarr = [
                    'post_title'   => $title,
                    'post_type'    => self::CPT_BOOKING,
                    'post_status'  => 'publish',
                ];

                if ($existing) {
                    $booking_id = $existing[0];
                    wp_update_post(array_merge(['ID' => $booking_id], $postarr));
                } else {
                    $booking_id = wp_insert_post($postarr);
                }

                if ($booking_id && !is_wp_error($booking_id)) {
                    update_post_meta($booking_id, '_gembook_product_id', $product->get_id());
                    update_post_meta($booking_id, '_gembook_order_id', $order_id);
                    update_post_meta($booking_id, '_gembook_order_item_id', $item_id);
                    update_post_meta($booking_id, '_gembook_start_ts', $start);
                    update_post_meta($booking_id, '_gembook_end_ts', $end);
                    update_post_meta($booking_id, '_gembook_status', $status);
                }
            }
        }

        public function release_bookings_from_order($order_id) {
            $order = wc_get_order($order_id);
            if (!$order) return;

            $bookings = get_posts([
                'post_type'   => self::CPT_BOOKING,
                'post_status' => 'any',
                'numberposts' => -1,
                'meta_query'  => [
                    ['key' => '_gembook_order_id', 'value' => $order_id],
                ],
                'fields' => 'ids',
            ]);

            foreach ($bookings as $booking_id) {
                update_post_meta($booking_id, '_gembook_status', 'released');
            }
        }

        // Helpers

        private function parse_and_validate_input_range(WC_Product $product, $booking_type, $post) {
            $tz = wp_timezone();

            $min_days   = intval($product->get_meta('_gembook_min_days') ?: 1);
            $max_days   = intval($product->get_meta('_gembook_max_days') ?: 0);
            $min_hours  = intval($product->get_meta('_gembook_min_hours') ?: 1);
            $max_hours  = intval($product->get_meta('_gembook_max_hours') ?: 0);
            $increment  = intval($product->get_meta('_gembook_time_increment') ?: 30);

            if ($booking_type === 'single_day') {
                $date = isset($post['gembook_date']) ? sanitize_text_field(wp_unslash($post['gembook_date'])) : '';
                if (!$date) {
                    throw new \Exception(__('Please select a date.', 'gembook'));
                }
                $start = new DateTime($date . ' 00:00:00', $tz);
                $end   = new DateTime($date . ' 23:59:59', $tz);

                $start_ts = $start->getTimestamp();
                $end_ts   = $end->getTimestamp();

                $units = 1;
                $unit  = 'day';
                $display = sprintf('%s (%s)', $this->format_ts_local($start_ts, 'Y-m-d'), __('Single Day', 'gembook'));

                return compact('start_ts','end_ts','units','unit','display');
            }

            if ($booking_type === 'multi_day') {
                $start_date = isset($post['gembook_start_date']) ? sanitize_text_field(wp_unslash($post['gembook_start_date'])) : '';
                $end_date   = isset($post['gembook_end_date']) ? sanitize_text_field(wp_unslash($post['gembook_end_date'])) : '';
                if (!$start_date || !$end_date) {
                    throw new \Exception(__('Please select start and end dates.', 'gembook'));
                }
                $start = new DateTime($start_date . ' 00:00:00', $tz);
                $end   = new DateTime($end_date . ' 23:59:59', $tz);
                if ($end < $start) {
                    throw new \Exception(__('End date must be after start date.', 'gembook'));
                }

                // Compute days inclusive
                $diff_days = (int)$start->diff($end)->format('%a') + 1;

                if ($diff_days < $min_days) {
                    throw new \Exception(sprintf(__('Minimum booking is %d day(s).', 'gembook'), $min_days));
                }
                if ($max_days > 0 && $diff_days > $max_days) {
                    throw new \Exception(sprintf(__('Maximum booking is %d day(s).', 'gembook'), $max_days));
                }

                $start_ts = $start->getTimestamp();
                $end_ts   = $end->getTimestamp();

                $units = $diff_days;
                $unit  = 'day';
                $display = sprintf('%s → %s (%d %s)',
                    $this->format_ts_local($start_ts, 'Y-m-d'),
                    $this->format_ts_local($end_ts, 'Y-m-d'),
                    $units,
                    _n('day', 'days', $units, 'gembook')
                );

                return compact('start_ts','end_ts','units','unit','display');
            }

            if ($booking_type === 'time_based') {
                $date       = isset($post['gembook_date']) ? sanitize_text_field(wp_unslash($post['gembook_date'])) : '';
                $start_time = isset($post['gembook_start_time']) ? sanitize_text_field(wp_unslash($post['gembook_start_time'])) : '';
                $end_time   = isset($post['gembook_end_time']) ? sanitize_text_field(wp_unslash($post['gembook_end_time'])) : '';
                if (!$date || !$start_time || !$end_time) {
                    throw new \Exception(__('Please select date, start time, and end time.', 'gembook'));
                }

                $start = new DateTime($date . ' ' . $start_time . ':00', $tz);
                $end   = new DateTime($date . ' ' . $end_time . ':00', $tz);
                if ($end <= $start) {
                    throw new \Exception(__('End time must be after start time.', 'gembook'));
                }

                $seconds = $end->getTimestamp() - $start->getTimestamp();
                $hours = $seconds / 3600.0;

                // Apply increment rounding (minutes)
                $inc_hours = max(5, $increment) / 60.0;
                $hours = ceil($hours / $inc_hours) * $inc_hours;

                if ($hours < $min_hours) {
                    throw new \Exception(sprintf(__('Minimum booking is %d hour(s).', 'gembook'), $min_hours));
                }
                if ($max_hours > 0 && $hours > $max_hours) {
                    throw new \Exception(sprintf(__('Maximum booking is %d hour(s).', 'gembook'), $max_hours));
                }

                // Recompute end timestamp after rounding
                $start_ts = $start->getTimestamp();
                $end_ts   = $start_ts + (int)round($hours * 3600);

                $units = $hours;
                $unit  = 'hour';
                $display = sprintf('%s %s–%s (%.2f %s)',
                    $this->format_ts_local($start_ts, 'Y-m-d'),
                    $this->format_ts_local($start_ts, 'H:i'),
                    $this->format_ts_local($end_ts, 'H:i'),
                    $units,
                    _n('hour', 'hours', (int)round($units), 'gembook')
                );

                return compact('start_ts','end_ts','units','unit','display');
            }

            throw new \Exception(__('Invalid booking type.', 'gembook'));
        }

        private function calculate_price_for_range(WC_Product $product, array $meta) {
            $booking_type = $meta['booking_type'];
            $units = (float)$meta['units'];
            if ($booking_type === 'time_based') {
                $ppu = (float)($product->get_meta('_gembook_price_per_hour') ?: 0);
                $price = $units * $ppu;
            } else {
                $ppu = (float)($product->get_meta('_gembook_price_per_day') ?: 0);
                $price = $units * $ppu;
            }

            // Example tiered discount based on duration (optional)
            // 5% off for >= 3 units, 10% off for >= 7 units
            if ($units >= 7) {
                $price *= 0.90;
            } elseif ($units >= 3) {
                $price *= 0.95;
            }

            return wc_format_decimal($price, wc_get_price_decimals());
        }

        private function is_range_available($product_id, $start_ts, $end_ts) {
            // Consider bookings with 'reserved' status as blocking. You can expand logic.
            $meta_query = [
                'relation' => 'AND',
                [
                    'key'     => '_gembook_product_id',
                    'value'   => $product_id,
                    'compare' => '=',
                ],
            ];

            $bookings = get_posts([
                'post_type'   => self::CPT_BOOKING,
                'post_status' => 'publish',
                'numberposts' => -1,
                'meta_query'  => $meta_query,
                'fields'      => 'ids',
            ]);

            foreach ($bookings as $booking_id) {
                $status = get_post_meta($booking_id, '_gembook_status', true);
                if (!in_array($status, ['reserved'], true)) {
                    continue; // ignore released or unknown
                }
                $b_start = intval(get_post_meta($booking_id, '_gembook_start_ts', true));
                $b_end   = intval(get_post_meta($booking_id, '_gembook_end_ts', true));
                if ($this->ranges_overlap($start_ts, $end_ts, $b_start, $b_end)) {
                    return false;
                }
            }
            return true;
        }

        private function ranges_overlap($s1, $e1, $s2, $e2) {
            // Treat ranges as [start, end) to avoid edge-case double booking at boundaries
            return ($s1 < $e2) && ($s2 < $e1);
        }

        private function format_ts_local($ts, $fmt = 'Y-m-d H:i') {
            $dt = wp_date($fmt, $ts);
            return $dt;
        }

        private function parse_meta_ts($str) {
            if (!$str) return null;
            // Attempt to parse 'Y-m-d H:i' or 'Y-m-d'
            $ts = strtotime($str);
            return $ts ?: null;
        }
    }

    // Product class
    if (!class_exists('WC_Product_GemBook_Service')) {
        class WC_Product_GemBook_Service extends WC_Product {
            public function get_type() {
                return GemBook::PRODUCT_TYPE;
            }

            // Force virtual and sold individually behavior
            public function is_virtual() {
                return true;
            }
            public function is_sold_individually() {
                return true;
            }
        }
    }

    // Bootstrap
    add_action('plugins_loaded', function () {
        GemBook::instance();
    });
}