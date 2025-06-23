<?php
/**
 * QR CODE VERIFICATION HANDLER SNIPPET
 * --------------------------------
 * PURPOSE: Provides a dedicated AJAX endpoint to verify a scanned QR code.
 * It checks if the asset exists and if it belongs to the expected location.
 * TYPE: PHP
 */

add_action('wp_ajax_verify_qr_code', 'handle_ajax_verify_qr_code');

function handle_ajax_verify_qr_code() {
    // Security check
    check_ajax_referer('audit_save_nonce', 'nonce');

    // Get the data sent from the scanner
    $scanned_id = isset($_POST['scanned_id']) ? sanitize_text_field($_POST['scanned_id']) : '';
    $expected_location_id = isset($_POST['location_id']) ? (int)$_POST['location_id'] : 0;

    if (empty($scanned_id)) {
        wp_send_json_error(['status' => 'error', 'message' => 'No ID was scanned.']);
    }

    // --- NEW, MORE RELIABLE VERIFICATION LOGIC ---

    // 1. Parse the scanned ID to get the base ID and the unit number
    $parts = explode('-', $scanned_id);
    if (count($parts) < 2) {
        wp_send_json_success(['status' => 'unknown_item', 'message' => 'QR code format is invalid.']);
    }
    
    $base_id = $parts[0];
    $unit_index = (int)end($parts);

    // 2. Find the master item post that has this base ID
    $master_item_query = new WP_Query([
        'post_type' => 'inventory_item',
        'posts_per_page' => 1,
        'meta_key' => 'base_id',
        'meta_value' => $base_id,
        'fields' => 'ids'
    ]);
    
    if (!$master_item_query->have_posts()) {
         wp_send_json_success(['status' => 'unknown_item', 'message' => 'No master item found with this Base ID.']);
    }
    $master_item_id = $master_item_query->posts[0];

    // 3. Find ALL stock entries linked to this master item
    $all_linked_stock_entries = get_posts([
        'post_type' => 'stock_entry',
        'posts_per_page' => -1,
        'meta_query' => [['key' => 'linked_item', 'value' => $master_item_id]],
    ]);

    if (empty($all_linked_stock_entries)) {
        wp_send_json_success(['status' => 'unknown_item', 'message' => 'No stock entries exist for this master item.']);
    }

    // 4. Loop through the stock entries to find which batch this unit belongs to
    $correct_stock_entry = null;
    foreach ($all_linked_stock_entries as $entry) {
        $start_id = (int) get_post_meta($entry->ID, 'starting_unit_id', true);
        $quantity = (int) get_post_meta($entry->ID, 'quantity', true);

        if ($unit_index >= $start_id && $unit_index < ($start_id + $quantity)) {
            $correct_stock_entry = $entry; // We found the right batch!
            break; 
        }
    }
    
    if (!$correct_stock_entry) {
        wp_send_json_success(['status' => 'unknown_item', 'message' => 'Could not find a stock entry for this asset unit. (Check unit ranges).']);
    }

    // 5. We found the item. Now, get its actual location(s).
    $actual_locations = get_the_terms($correct_stock_entry->ID, 'location');
    if (is_wp_error($actual_locations) || empty($actual_locations)) {
        wp_send_json_success(['status' => 'unknown_item', 'message' => 'This asset exists but has no location assigned.']);
    }

    // 6. Compare its actual location with the location we are auditing.
    $is_in_correct_location = false;
    $actual_location_name = '';
    
    $child_locations = get_term_children($expected_location_id, 'location');
    $valid_location_ids = array_merge([$expected_location_id], $child_locations);

    foreach ($actual_locations as $term) {
        $actual_location_name = $term->name;
        if (in_array($term->term_id, $valid_location_ids)) {
            $is_in_correct_location = true;
            break;
        }
    }

    // 7. Send back the final verdict.
    if ($is_in_correct_location) {
        wp_send_json_success(['status' => 'perfect_match']);
    } else {
        wp_send_json_success([
            'status' => 'location_mismatch',
            'message' => 'This item belongs in ' . $actual_location_name . '.'
        ]);
    }
}