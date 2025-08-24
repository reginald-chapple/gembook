jQuery(document).ready(function($) {
    // Admin JavaScript functionality
    
    // Product type switching
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
    
    // Booking type dependent fields
    $('#_gembook_booking_type').change(function() {
        var booking_type = $(this).val();
        
        if (booking_type === 'time_based' || booking_type === 'all') {
            $('#_gembook_duration_pricing_hourly').closest('p').show();
            $('#_gembook_available_times').closest('p').show();
        } else {
            $('#_gembook_duration_pricing_hourly').closest('p').hide();
            $('#_gembook_available_times').closest('p').hide();
        }
        
        if (booking_type === 'multi_day' || booking_type === 'all') {
            $('#_gembook_duration_pricing_daily').closest('p').show();
        } else {
            $('#_gembook_duration_pricing_daily').closest('p').hide();
        }
    }).change();
});