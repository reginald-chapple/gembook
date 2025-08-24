<?php

// Hook to create tables on plugin activation
register_activation_hook(__FILE__, array('GemBook_Database', 'create_tables'));

// Hook to drop tables on plugin deactivation (optional - you might want to keep data)
// register_deactivation_hook(__FILE__, array('GemBook_Database', 'drop_tables'));

// Check tables exist on admin init
add_action('admin_init', array('GemBook_Database', 'check_and_create_tables'));

// Add admin notice if tables don't exist
add_action('admin_notices', function() {
    if (current_user_can('manage_options')) {
        global $wpdb;
        
        $bookings_table = $wpdb->prefix . 'gembook_bookings';
        $bookings_exists = $wpdb->get_var("SHOW TABLES LIKE '$bookings_table'") == $bookings_table;
        
        if (!$bookings_exists) {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>GemBook:</strong> Database tables are missing! ';
            echo '<a href="' . admin_url('admin.php?page=gembook-setup') . '">Click here to create them</a>';
            echo '</p></div>';
        }
    }
});

// Add admin menu for manual table creation
add_action('admin_menu', function() {
    add_submenu_page(
        'tools.php',
        'GemBook Setup',
        'GemBook Setup',
        'manage_options',
        'gembook-setup',
        function() {
            if (isset($_POST['create_tables'])) {
                $result = GemBook_Database_Setup::create_tables();
                echo '<div class="notice notice-' . ($result ? 'success' : 'error') . '"><p>';
                echo $result ? 'Tables created successfully!' : 'Failed to create tables. Check error logs.';
                echo '</p></div>';
            }
            
            if (isset($_POST['drop_tables'])) {
                GemBook_Database_Setup::drop_tables();
                echo '<div class="notice notice-success"><p>Tables dropped successfully!</p></div>';
            }
            
            global $wpdb;
            $bookings_table = $wpdb->prefix . 'gembook_bookings';
            $availability_table = $wpdb->prefix . 'gembook_availability';
            
            $bookings_exists = $wpdb->get_var("SHOW TABLES LIKE '$bookings_table'") == $bookings_table;
            $availability_exists = $wpdb->get_var("SHOW TABLES LIKE '$availability_table'") == $availability_table;
            
            ?>
            <div class="wrap">
                <h1>GemBook Database Setup</h1>
                
                <h2>Table Status</h2>
                <table class="widefat">
                    <tr>
                        <td><strong>Bookings Table (<?php echo $bookings_table; ?>)</strong></td>
                        <td><?php echo $bookings_exists ? '✅ EXISTS' : '❌ MISSING'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Availability Table (<?php echo $availability_table; ?>)</strong></td>
                        <td><?php echo $availability_exists ? '✅ EXISTS' : '❌ MISSING'; ?></td>
                    </tr>
                </table>
                
                <h2>Actions</h2>
                <form method="post">
                    <p>
                        <input type="submit" name="create_tables" class="button button-primary" 
                               value="Create/Update Tables" />
                        <span class="description">This will create missing tables or update existing ones.</span>
                    </p>
                </form>
                
                <form method="post" onsubmit="return confirm('Are you sure? This will delete all booking data!');">
                    <p>
                        <input type="submit" name="drop_tables" class="button button-secondary" 
                               value="Drop Tables" />
                        <span class="description">⚠️ This will permanently delete all booking data!</span>
                    </p>
                </form>
            </div>
            <?php
        }
    );
});

/**
 * Manual GemBook Table Creation Script
 * Run this once to create the database tables
 */

// Add this to your functions.php temporarily, then remove it after running

add_action('init', function() {
    if (isset($_GET['create_gembook_tables']) && current_user_can('manage_options')) {
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
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $result = dbDelta($bookings_sql);
        
        // Check if table was created
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$bookings_table'") == $bookings_table;
        
        if ($table_exists) {
            wp_die('✅ GemBook bookings table created successfully! You can now remove this code from functions.php');
        } else {
            wp_die('❌ Failed to create GemBook bookings table. Check your database permissions.');
        }
    }
});

// Add admin notice with creation link
add_action('admin_notices', function() {
    if (current_user_can('manage_options')) {
        global $wpdb;
        $bookings_table = $wpdb->prefix . 'gembook_bookings';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$bookings_table'") == $bookings_table;
        
        if (!$table_exists) {
            $create_url = add_query_arg('create_gembook_tables', '1', admin_url());
            echo '<div class="notice notice-error"><p>';
            echo '<strong>GemBook:</strong> Database table missing! ';
            echo '<a href="' . $create_url . '" class="button">Create Table Now</a>';
            echo '</p></div>';
        }
    }
});

?>