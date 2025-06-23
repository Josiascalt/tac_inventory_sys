<?php
/**
 * INVENTORY AUTOMATION SNIPPET (v4 - Complete)
 * ---------------------------------------------------------
 * PURPOSE: A complete automation solution for Stock Entries. When a stock entry is saved, this will:
 * 1. (On first save only) Generate unique, sequential IDs for the asset batch.
 * 2. (On every save) Update the 'Last Checked Date' to the current date.
 * 3. (On every save) Recalculate the depreciation status and end date.
 * TYPE: PHP
 */

// This single action hook handles all automation when a 'stock_entry' is saved.
add_action('acf/save_post', 'handle_complete_stock_entry_automation', 20);

function handle_complete_stock_entry_automation($post_id) {
    
    // --- Safety Checks ---
    // Only run this on the 'stock_entry' post type.
    if (get_post_type($post_id) !== 'stock_entry') {
        return;
    }
    
    // --- Get shared data needed for all automations ---
    $linked_item = get_field('linked_item', $post_id);
    if (!$linked_item) {
        return; // Can't do anything without a linked master item.
    }
    $master_item_id = $linked_item->ID;


    // ===================================================================
    // PART 1: UNIQUE ID GENERATION (Runs only ONCE per stock entry)
    // ===================================================================

    // Check if the 'Starting Unit ID' has already been set. If it is, this part will be skipped.
    if ( ! get_field('starting_unit_id', $post_id) ) {
        
        $quantity_in_this_batch = (int)get_field('quantity', $post_id);

        if ($quantity_in_this_batch > 0) {
            // Get the current total number of units for the master item.
            $global_unit_counter = (int)get_field('total_units_created', $master_item_id);

            // "Stamp" this stock entry with its starting number.
            update_field('starting_unit_id', $global_unit_counter, $post_id);

            // Calculate the new total and update the global counter on the master item.
            $new_global_total = $global_unit_counter + $quantity_in_this_batch;
            update_field('total_units_created', $new_global_total, $master_item_id);
        }
    }

    // ===================================================================
    // PART 2: DEPRECIATION & DATE LOGIC (Runs EVERY time the entry is saved)
    // ===================================================================

    // 1. Always update "Last Checked Date" to today.
    update_field('last_checked_date', date('Ymd'), $post_id);
    
    // 2. Recalculate depreciation.
    $purchase_date_str = get_field('purchase_date', $post_id);
    $usable_life_years = (int)get_field('usable_life', $master_item_id);

    // Proceed only if we have the data needed for calculation.
    if ($purchase_date_str && $usable_life_years > 0) {
        try {
            // Calculate Depreciation End Date
            $purchase_date_obj = new DateTime($purchase_date_str);
            $purchase_date_obj->modify('+' . $usable_life_years . ' years');
            update_field('depreciation_end_date', $purchase_date_obj->format('Ymd'), $post_id);

            // Determine Depreciation Status
            $depreciation_status = (time() > $purchase_date_obj->getTimestamp()) ? 'Depreciated' : 'Active';
            update_field('depreciation_status', $depreciation_status, $post_id);

        } catch (Exception $e) {
            // If there's an error with date calculation, do nothing to prevent crashing.
        }
    }
}
