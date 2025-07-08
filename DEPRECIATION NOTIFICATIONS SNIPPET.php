<?php
/**
 * DEPRECIATION NOTIFICATIONS SNIPPET V4 (Mobile Responsive)
 * ------------------------------------
 * PURPOSE: Provides a shortcode [depreciation_notifications] to display a scrollable
 * list of items, allowing users to acknowledge and dismiss them permanently.
 * - Now features a fully responsive design for mobile devices.
 * TYPE: PHP
 */

add_shortcode('depreciation_notifications', 'render_depreciation_notifications_shortcode');

function render_depreciation_notifications_shortcode($atts) {
    $atts = shortcode_atts(['days' => '90'], $atts, 'depreciation_notifications');
    ob_start();

    global $wpdb;
    $ack_table_name = $wpdb->prefix . 'inventory_depreciation_log';
    $current_user_id = get_current_user_id();

    $acknowledged_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT full_asset_id FROM $ack_table_name WHERE user_id = %d",
        $current_user_id
    ));

    $stock_entries_query = new WP_Query(['post_type' => 'stock_entry', 'posts_per_page' => -1, 'post_status' => 'publish']);

    if (!$stock_entries_query->have_posts()) { return '<p>No stock entries found.</p>'; }

    $depreciated_items = [];
    $nearing_depreciation_items = [];
    $current_time = new DateTime();
    $notification_threshold_date = (new DateTime())->modify("+" . (int)$atts['days'] . " days");

    while ($stock_entries_query->have_posts()) {
        $stock_entries_query->the_post();
        $stock_id = get_the_ID();
        $dep_status = get_field('depreciation_status', $stock_id);
        $dep_end_date_str = get_field('depreciation_end_date', $stock_id);
        $is_depreciated = ($dep_status === 'Depreciated');
        $is_nearing = false;

        if (!$is_depreciated && $dep_end_date_str) {
            $dep_end_date_obj = DateTime::createFromFormat('Ymd', $dep_end_date_str);
            if ($dep_end_date_obj && $dep_end_date_obj > $current_time && $dep_end_date_obj <= $notification_threshold_date) {
                $is_nearing = true;
            }
        }

        if (!$is_depreciated && !$is_nearing) { continue; }

        $linked_item = get_field('linked_item', $stock_id);
        $master_item_id = $linked_item ? $linked_item->ID : 0;
        $base_id = $master_item_id ? get_field('base_id', $master_item_id) : 'NO-ID';
        $start_id = (int)get_field('starting_unit_id', $stock_id);
        $quantity = (int)get_field('quantity', $stock_id);
        $location_terms = get_the_terms($stock_id, 'location');
        $location_name = ($location_terms && !is_wp_error($location_terms)) ? $location_terms[0]->name : 'No Location';
        
        $purchase_date_obj = get_field('purchase_date', $stock_id) ? DateTime::createFromFormat('Ymd', get_field('purchase_date', $stock_id)) : null;
        $dep_end_date_obj_for_item = $dep_end_date_str ? DateTime::createFromFormat('Ymd', $dep_end_date_str) : null;

        for ($i = 0; $i < $quantity; $i++) {
            $unit_number = str_pad($start_id + $i, 3, '0', STR_PAD_LEFT);
            $full_asset_id = $base_id . '-' . $unit_number;

            if (in_array($full_asset_id, $acknowledged_ids)) {
                continue;
            }

            $notification_data = [
                'stock_id' => $stock_id,
                'full_asset_id' => $full_asset_id,
                'master_item_title' => $linked_item ? $linked_item->post_title : 'Unknown Item',
                'location' => $location_name,
                'dep_end_date' => $dep_end_date_str,
                'image_url' => get_the_post_thumbnail_url($master_item_id, 'medium'),
                'brand' => get_field('brand', $master_item_id),
                'short_desc' => get_field('short_description', $master_item_id),
                'price' => get_field('item_price', $stock_id),
                'purchase_date_formatted' => $purchase_date_obj ? $purchase_date_obj->format('d/m/Y') : 'N/A',
                'usable_life' => get_field('usable_life', $master_item_id),
                'dep_status' => $dep_status,
                'dep_end_date_formatted' => $dep_end_date_obj_for_item ? $dep_end_date_obj_for_item->format('d/m/Y') : 'N/A',
            ];

            if ($is_depreciated) { $depreciated_items[] = $notification_data; } 
            else { $nearing_depreciation_items[] = $notification_data; }
        }
    }
    wp_reset_postdata();
    ?>
    <style>
        .depreciation-dashboard { border: 1px solid #e0e0e0; border-radius: 8px; background: #fff; }
        .depreciation-header { padding: 15px 20px; background-color: #f9f9f9; border-bottom: 1px solid #e0e0e0; font-weight: bold; font-size: 1.2em; }
        .depreciation-list-container { max-height: 400px; overflow-y: auto; padding: 10px; }
        .notification-item { position: relative; display: flex; align-items: center; padding: 15px; border-bottom: 1px solid #f2f2f2; transition: opacity 0.4s ease-out, transform 0.4s ease-out; cursor: pointer; }
        .notification-item:hover { background-color: #f5f5f5; }
        .notification-item:last-child { border-bottom: none; }
        .notification-item.is-hiding { opacity: 0; transform: translateX(-25px); }
        .notification-item::after { content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0; z-index: 1; }
        .notification-confirm-wrap { padding-right: 15px; position: relative; z-index: 2; }
        .depreciation-confirm-checkbox { transform: scale(1.2); }
        .notification-content { flex-grow: 1; display: flex; justify-content: space-between; align-items: center; }
        .notification-item-title { font-weight: bold; color: #333; }
        .notification-item-meta { display: block; font-size: 0.9em; color: #777; font-weight: normal; }
        .notification-item-date { font-size: 0.9em; padding: 5px 10px; border-radius: 4px; white-space: nowrap; }
        .date-depreciated { background-color: #fbe9e7; color: #c62828; }
        .date-nearing { background-color: #fff3e0; color: #ef6c00; }

        /* --- NEW: Responsive styles for mobile devices --- */
        @media (max-width: 640px) {
            .notification-content {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
                width: 100%;
            }
            .notification-item-date {
                align-self: flex-end; /* Push the date badge to the right on its own line */
            }
        }
    </style>

    <div class="depreciation-dashboard">
        <div class="depreciation-header">Depreciation Status</div>
        <div class="depreciation-list-container">
            <?php if (empty($depreciated_items) && empty($nearing_depreciation_items)) : ?>
                <p style="text-align: center; padding: 20px;">All depreciation notifications have been acknowledged.</p>
            <?php endif; ?>

            <?php foreach ($depreciated_items as $item) : 
                $days_past_str = '';
                if (!empty($item['dep_end_date'])) {
                    $end_date_obj = DateTime::createFromFormat('Ymd', $item['dep_end_date']);
                    if ($end_date_obj) { $days_past_str = ' (' . $current_time->diff($end_date_obj)->days . ' days ago)'; }
                }
            ?>
                <div class="notification-item view-details-btn"
                    data-stock-id="<?php echo esc_attr($item['stock_id']); ?>"
                    data-asset-id="<?php echo esc_attr($item['full_asset_id']); ?>">
                    
                    <div class="notification-confirm-wrap">
                        <input type="checkbox" class="depreciation-confirm-checkbox" data-asset-id="<?php echo esc_attr($item['full_asset_id']); ?>" title="Acknowledge this notification">
                    </div>
                    <div class="notification-content">
                        <div>
                            <div class="notification-item-title"><?php echo esc_html($item['master_item_title']); ?></div>
                            <span class="notification-item-meta">ID: <?php echo esc_html($item['full_asset_id']); ?> | Location: <?php echo esc_html($item['location']); ?></span>
                        </div>
                        <div><span class="notification-item-date date-depreciated">Depreciated<?php echo esc_html($days_past_str); ?></span></div>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php foreach ($nearing_depreciation_items as $item) : 
                $date_obj = DateTime::createFromFormat('Ymd', $item['dep_end_date']);
            ?>
                <div class="notification-item view-details-btn"
                    data-stock-id="<?php echo esc_attr($item['stock_id']); ?>"
                    data-asset-id="<?php echo esc_attr($item['full_asset_id']); ?>">

                    <div class="notification-confirm-wrap">
                        <input type="checkbox" class="depreciation-confirm-checkbox" data-asset-id="<?php echo esc_attr($item['full_asset_id']); ?>" title="Acknowledge this notification">
                    </div>
                    <div class="notification-content">
                        <div>
                            <div class="notification-item-title"><?php echo esc_html($item['master_item_title']); ?></div>
                            <span class="notification-item-meta">ID: <?php echo esc_html($item['full_asset_id']); ?> | Location: <?php echo esc_html($item['location']); ?></span>
                        </div>
                        <div><span class="notification-item-date date-nearing">Ends: <?php echo $date_obj ? $date_obj->format('d/m/Y') : 'N/A'; ?></span></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <?php wp_nonce_field('acknowledge_nonce', 'ack_nonce'); ?>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const container = document.querySelector('.depreciation-list-container');
        if (container) {
            container.addEventListener('change', function(event) {
                if (event.target && event.target.classList.contains('depreciation-confirm-checkbox')) {
                    const checkbox = event.target;
                    const notificationItem = checkbox.closest('.notification-item');
                    const assetId = checkbox.dataset.assetId;

                    if (notificationItem && checkbox.checked) {
                        checkbox.disabled = true; 
                        
                        const formData = new FormData();
                        formData.append('action', 'acknowledge_depreciation_item');
                        formData.append('nonce', document.getElementById('ack_nonce').value);
                        formData.append('asset_id', assetId);

                        fetch('<?php echo admin_url("admin-ajax.php"); ?>', { method: 'POST', body: formData })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                notificationItem.classList.add('is-hiding');
                                setTimeout(() => { notificationItem.remove(); }, 400);
                            } else {
                                alert('Could not save acknowledgement. Please try again.');
                                checkbox.disabled = false;
                                checkbox.checked = false;
                            }
                        });
                    }
                }
            });
        }
    });
    </script>
    <?php

    return ob_get_clean();
}


/**
 * AJAX handler to save the acknowledgement to the custom table.
 */
add_action('wp_ajax_acknowledge_depreciation_item', 'handle_ajax_acknowledge_item');
function handle_ajax_acknowledge_item() {
    check_ajax_referer('acknowledge_nonce', 'nonce');

    $asset_id = isset($_POST['asset_id']) ? sanitize_text_field($_POST['asset_id']) : '';
    $user_id = get_current_user_id();

    if (empty($asset_id) || $user_id == 0) {
        wp_send_json_error(['message' => 'Invalid data provided.'], 400);
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'inventory_depreciation_log';

    $result = $wpdb->insert(
        $table_name,
        [
            'full_asset_id'   => $asset_id,
            'user_id'         => $user_id,
            'acknowledged_at' => current_time('mysql'),
        ],
        ['%s', '%d', '%s']
    );

    if ($result === false) {
        wp_send_json_error(['message' => 'Database error.'], 500);
    }

    wp_send_json_success();
}
