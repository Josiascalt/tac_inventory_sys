<?php
/**
 * ASSET DETAILS AJAX HANDLER SNIPPET
 * ------------------------------------
 * PURPOSE: Provides a dedicated AJAX endpoint to fetch all details for a given stock entry.
 * This is the new "brain" for the reusable details modal.
 * TYPE: PHP
 */

add_action('wp_ajax_get_asset_details', 'handle_ajax_get_asset_details');

function handle_ajax_get_asset_details() {
    // We don't need a nonce check here because this is just reading public data,
    // but we do check if the user is logged in.
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'You must be logged in.'], 403);
    }

    $stock_id = isset($_POST['stock_id']) ? intval($_POST['stock_id']) : 0;
    if (!$stock_id) {
        wp_send_json_error(['message' => 'No Stock ID provided.'], 400);
    }

    // --- Gather all the data ---
    
    // Get Stock Entry details
    $linked_item = get_field('linked_item', $stock_id);
    $master_item_id = $linked_item ? $linked_item->ID : 0;
    
    $purchase_date_obj = get_field('purchase_date', $stock_id) ? DateTime::createFromFormat('Ymd', get_field('purchase_date', $stock_id)) : null;
    $dep_end_date_obj = get_field('depreciation_end_date', $stock_id) ? DateTime::createFromFormat('Ymd', get_field('depreciation_end_date', $stock_id)) : null;

    // Get Master Item details
    $master_title = $master_item_id ? get_the_title($master_item_id) : 'N/A';
    $brand = $master_item_id ? get_field('brand', $master_item_id) : 'N/A';
    $short_desc = $master_item_id ? get_field('short_description', $master_item_id) : 'N/A';
    $usable_life = $master_item_id ? get_field('usable_life', $master_item_id) : 'N/A';
    $image_url = $master_item_id ? get_the_post_thumbnail_url($master_item_id, 'medium') : '';

    // --- Bundle it all into a clean array ---
    $data_to_return = [
        'title'         => $master_title,
        'brand'         => $brand,
        'description'   => $short_desc,
        'location'      => get_the_term_list($stock_id, 'location', '', ', '),
        'price'         => get_field('item_price', $stock_id),
        'purchase_date' => $purchase_date_obj ? $purchase_date_obj->format('d/m/Y') : 'N/A',
        'usable_life'   => $usable_life,
        'dep_status'    => get_field('depreciation_status', $stock_id),
        'dep_end_date'  => $dep_end_date_obj ? $dep_end_date_obj->format('d/m/Y') : 'N/A',
        'image_url'     => $image_url,
    ];

    // Send the data back to the browser as a JSON object
    wp_send_json_success($data_to_return);
}