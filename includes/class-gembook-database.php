<?php
/**
 * Database operations for GemBook
 */
class GemBook_Database {
    
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Bookings table
        $bookings_table = $wpdb->prefix . 'gembook_bookings';
        $bookings_sql = "CREATE TABLE $bookings_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            product_id bigint(20) NOT NULL,
            order_id bigint(20) DEFAULT NULL,
            customer_id bigint(20) DEFAULT NULL,
            booking_type varchar(20) NOT NULL DEFAULT 'single_day',
            start_date date NOT NULL,
            end_date date DEFAULT NULL,
            start_time time DEFAULT NULL,
            end_time time DEFAULT NULL,
            duration decimal(4,2) DEFAULT NULL,
            total_price decimal(10,2) NOT NULL DEFAULT 0.00,
            status varchar(20) NOT NULL DEFAULT 'pending',
            booking_notes text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY product_id (product_id),
            KEY order_id (order_id),
            KEY customer_id (customer_id),
            KEY start_date (start_date),
            KEY status (status)
        ) $charset_collate;";
        
        // Availability table (for custom availability rules)
        $availability_table = $wpdb->prefix . 'gembook_availability';
        $availability_sql = "CREATE TABLE $availability_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            product_id bigint(20) NOT NULL,
            date date NOT NULL,
            start_time time DEFAULT NULL,
            end_time time DEFAULT NULL,
            max_bookings int(11) NOT NULL DEFAULT 1,
            is_available tinyint(1) NOT NULL DEFAULT 1,
            notes text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY product_id (product_id),
            KEY date (date),
            KEY is_available (is_available),
            UNIQUE KEY unique_product_date_time (product_id, date, start_time, end_time)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $result1 = dbDelta($bookings_sql);
        $result2 = dbDelta($availability_sql);
        
        // Log the results
        error_log('GemBook: Creating bookings table - ' . print_r($result1, true));
        error_log('GemBook: Creating availability table - ' . print_r($result2, true));
        
        // Verify tables were created
        $bookings_exists = $wpdb->get_var("SHOW TABLES LIKE '$bookings_table'") == $bookings_table;
        $availability_exists = $wpdb->get_var("SHOW TABLES LIKE '$availability_table'") == $availability_table;
        
        if ($bookings_exists && $availability_exists) {
            update_option('gembook_db_version', '1.0');
            error_log('GemBook: Database tables created successfully');
            return true;
        } else {
            error_log('GemBook: Failed to create database tables');
            return false;
        }
    }
    
    public static function drop_tables() {
        global $wpdb;
        
        $bookings_table = $wpdb->prefix . 'gembook_bookings';
        $availability_table = $wpdb->prefix . 'gembook_availability';
        
        $wpdb->query("DROP TABLE IF EXISTS $bookings_table");
        $wpdb->query("DROP TABLE IF EXISTS $availability_table");
        
        delete_option('gembook_db_version');
        
        error_log('GemBook: Database tables dropped');
    }
    
    public static function check_and_create_tables() {
        global $wpdb;
        
        $bookings_table = $wpdb->prefix . 'gembook_bookings';
        $availability_table = $wpdb->prefix . 'gembook_availability';
        
        $bookings_exists = $wpdb->get_var("SHOW TABLES LIKE '$bookings_table'") == $bookings_table;
        $availability_exists = $wpdb->get_var("SHOW TABLES LIKE '$availability_table'") == $availability_table;
        
        if (!$bookings_exists || !$availability_exists) {
            error_log('GemBook: Missing database tables, creating them now...');
            return self::create_tables();
        }
        
        return true;
    }
    
    public static function get_bookings($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'product_id' => 0,
            'user_id' => 0,
            'status' => '',
            'date_from' => '',
            'date_to' => ''
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array('1=1');
        
        if ($args['product_id']) {
            $where[] = $wpdb->prepare('product_id = %d', $args['product_id']);
        }
        
        if ($args['user_id']) {
            $where[] = $wpdb->prepare('user_id = %d', $args['user_id']);
        }
        
        if ($args['status']) {
            $where[] = $wpdb->prepare('status = %s', $args['status']);
        }
        
        if ($args['date_from']) {
            $where[] = $wpdb->prepare('start_date >= %s', $args['date_from']);
        }
        
        if ($args['date_to']) {
            $where[] = $wpdb->prepare('start_date <= %s', $args['date_to']);
        }
        
        $sql = "SELECT * FROM {$wpdb->prefix}gembook_bookings WHERE " . implode(' AND ', $where);
        
        return $wpdb->get_results($sql);
    }
    
    public static function create_booking($data) {
        global $wpdb;
        
        return $wpdb->insert(
            $wpdb->prefix . 'gembook_bookings',
            $data,
            array('%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%f', '%s')
        );
    }
    
    /**
     * Fixed availability check method for GemBook Database class
     */
    public static function check_availability($product_id, $date, $start_time = null, $end_time = null) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'gembook_bookings';
        
        // Debug logging
        error_log("GemBook Debug: Checking availability for product $product_id on $date");
        if ($start_time && $end_time) {
            error_log("GemBook Debug: Time range: $start_time to $end_time");
        }
        
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") == $table;
        if (!$table_exists) {
            error_log("GemBook Debug: Bookings table does not exist, returning available");
            return true; // If no bookings table, assume available
        }
        
        // Base query to find conflicting bookings
        $where_conditions = array();
        $where_conditions[] = $wpdb->prepare('product_id = %d', $product_id);
        $where_conditions[] = $wpdb->prepare('start_date = %s', $date);
        $where_conditions[] = $wpdb->prepare('status != %s', 'cancelled');
        
        // For time-based bookings, check for time conflicts
        if ($start_time && $end_time) {
            // Check for overlapping time slots
            $time_condition = $wpdb->prepare(
                '(start_time < %s AND end_time > %s)', 
                $end_time, 
                $start_time
            );
            $where_conditions[] = $time_condition;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        $query = "SELECT COUNT(*) FROM $table WHERE $where_clause";
        
        error_log("GemBook Debug: SQL Query: $query");
        
        $existing_bookings = $wpdb->get_var($query);
        
        error_log("GemBook Debug: Found $existing_bookings existing bookings");
        
        // Get maximum allowed bookings for this product
        $max_bookings = get_post_meta($product_id, '_gembook_max_bookings', true);
        if (empty($max_bookings)) {
            $max_bookings = 1; // Default to 1 if not set
        }
        
        error_log("GemBook Debug: Max bookings allowed: $max_bookings");
        
        $available = intval($existing_bookings) < intval($max_bookings);
        
        error_log("GemBook Debug: Available: " . ($available ? 'YES' : 'NO'));
        
        return $available;
    }
}