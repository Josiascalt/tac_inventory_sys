<?php
/**
 * AUDIT REPORT SNIPPET V4 (Refactored)
 * --------------------------------
 * PURPOSE: Provides a shortcode [audit_report] to display the results of a completed audit.
 * - Relies on the new reusable modal snippet for displaying asset details.
 * TYPE: PHP
 */

 add_shortcode('audit_report', 'render_audit_report_shortcode');

 function render_audit_report_shortcode($atts) {
     if (!current_user_can('auditor') && !current_user_can('manage_options')) {
         return '<p>You do not have permission to view this page.</p>';
     }
 
     ob_start();
     global $wpdb;
     $log_table_name = $wpdb->prefix . 'inventory_audit_log';
 
     $audit_sessions = $wpdb->get_col("SELECT DISTINCT audit_session_id FROM $log_table_name ORDER BY audit_timestamp DESC");
     $current_session_filter = isset($_GET['audit_session']) ? sanitize_text_field($_GET['audit_session']) : '';
     ?>
     <div class="inventory-filter-form" style="margin-bottom: 30px; padding: 20px; background-color: #f9f9f9; border-radius: 8px;">
         <form id="audit-report-filter-form" method="GET" action="">
             <label for="audit_session" style="font-weight: bold; margin-right: 10px;">Select an Audit Session to View Report:</label>
             <select name="audit_session" id="audit_session">
                 <option value="">— Select a Session —</option>
                 <?php foreach ($audit_sessions as $session_id) :
                     $parts = explode('_', str_replace('audit-', '', $session_id));
                     $display_text = 'Session ' . $session_id;
                     if (count($parts) === 3) {
                         $date_obj = DateTime::createFromFormat('Ymd', $parts[0]);
                         $date_str = $date_obj ? $date_obj->format('d/m/Y') : $parts[0];
                         $location_term = get_term($parts[1], 'location');
                         $user_info = get_userdata($parts[2]);
                         if ($location_term && $user_info) {
                             $display_text = esc_html($location_term->name) . ' - ' . $date_str . ' (by ' . esc_html($user_info->display_name) . ')';
                         }
                     }
                 ?>
                     <option value="<?php echo esc_attr($session_id); ?>" <?php selected($current_session_filter, $session_id); ?>>
                         <?php echo $display_text; ?>
                     </option>
                 <?php endforeach; ?>
             </select>
         </form>
     </div>
     <script>
         document.addEventListener('DOMContentLoaded', function() {
             document.getElementById('audit_session')?.addEventListener('change', function() { this.form.submit(); });
         });
     </script>
     <?php
     if (empty($current_session_filter)) {
         echo '<p>Please select an audit session from the dropdown above to see the report.</p>';
         return ob_get_clean();
     }
 
     $results = $wpdb->get_results($wpdb->prepare("SELECT * FROM $log_table_name WHERE audit_session_id = %s", $current_session_filter));
 
     if (empty($results)) {
         echo '<h2>Report for ' . esc_html($current_session_filter) . '</h2><p>No data was found for this audit session.</p>';
         return ob_get_clean();
     }
 
     // --- Pre-fetch all necessary data to avoid queries inside the loop ---
     $stock_entry_ids = array_unique(wp_list_pluck($results, 'stock_entry_id'));
     $stock_data_cache = [];
     $master_item_ids_to_fetch = [];
     foreach ($stock_entry_ids as $id) {
         $linked_item_id = get_post_meta($id, 'linked_item', true);
         $stock_data_cache[$id] = [
             'linked_item_id' => $linked_item_id, 'price' => get_post_meta($id, 'item_price', true),
             'purchase_date' => get_post_meta($id, 'purchase_date', true), 'dep_status' => get_post_meta($id, 'depreciation_status', true),
             'dep_end_date' => get_post_meta($id, 'depreciation_end_date', true),
         ];
         if ($linked_item_id && !in_array($linked_item_id, $master_item_ids_to_fetch)) { $master_item_ids_to_fetch[] = $linked_item_id; }
     }
     $master_data_cache = [];
     if (!empty($master_item_ids_to_fetch)) {
         foreach ($master_item_ids_to_fetch as $id) {
              $master_data_cache[$id] = [
                 'title' => get_the_title($id), 'brand' => get_post_meta($id, 'brand', true),
                 'short_desc' => get_post_meta($id, 'short_description', true), 'usable_life' => get_post_meta($id, 'usable_life', true),
                 'featured_image_url' => get_the_post_thumbnail_url($id, 'medium'),
             ];
         }
     }
     
     $found_items = [];
     $missing_items = [];
     foreach ($results as $row) {
         if ($row->is_found) { $found_items[] = $row; } 
         else { $missing_items[] = $row; }
     }
 
     $report_parts = explode('_', str_replace('audit-', '', $current_session_filter));
     $report_title = 'Report for Session ' . esc_html($current_session_filter);
     $auditor_line = '';
     if (count($report_parts) === 3) {
         $report_date_obj = DateTime::createFromFormat('Ymd', $report_parts[0]);
         $report_date_str = $report_date_obj ? $report_date_obj->format('d/m/Y') : $report_parts[0];
         $report_location_term = get_term($report_parts[1], 'location');
         $report_user_info = get_userdata($report_parts[2]);
         if ($report_location_term && $report_user_info) {
             $report_title = esc_html($report_location_term->name) . ' - ' . $report_date_str;
             $auditor_line = '<p><strong>Auditor:</strong> ' . esc_html($report_user_info->display_name) . '</p>';
         }
     }
     ?>
     <style>
         .audit-report-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
         .audit-report-table th, .audit-report-table td { padding: 12px; border: 1px solid #ddd; text-align: left; vertical-align: middle; }
         .audit-report-table th { background-color: #f2f2f2; }
         .condition-okay { color: green; }
         .condition-needs-revision { color: #d9534f; font-weight: bold; }
         .condition-not-found { color: #777; }
     </style>
 
     <div class="audit-report-container">
         <h2><?php echo $report_title; ?></h2>
         <?php echo $auditor_line; ?>
 
         <h3>Found Items (<?php echo count($found_items); ?>)</h3>
         <?php if (!empty($found_items)) : ?>
             <table class="audit-report-table">
                 <thead><tr><th>Asset ID</th><th>Asset Details</th><th>Condition</th><th>Notes</th></tr></thead>
                 <tbody>
                     <?php foreach ($found_items as $item) : 
                         $stock_details = $stock_data_cache[$item->stock_entry_id] ?? null;
                         $master_details = $stock_details ? ($master_data_cache[$stock_details['linked_item_id']] ?? null) : null;
                         $purchase_date_obj = !empty($stock_details['purchase_date']) ? DateTime::createFromFormat('Ymd', $stock_details['purchase_date']) : null;
                         $dep_end_date_obj = !empty($stock_details['dep_end_date']) ? DateTime::createFromFormat('Ymd', $stock_details['dep_end_date']) : null;
                     ?>
                         <tr>
                             <td><?php echo esc_html($item->full_asset_id); ?></td>
                             <td>
                                <button type="button" class="view-details-btn"
                                    data-stock-id="<?php echo esc_attr($item->stock_entry_id); ?>"
                                    data-asset-id="<?php echo esc_attr($item->full_asset_id); ?>"
                                >View Details</button>
                             </td>
                             <td class="condition-<?php echo esc_attr(strtolower(str_replace(' ', '-', $item->audit_condition))); ?>"><?php echo esc_html($item->audit_condition); ?></td>
                             <td><?php echo nl2br(esc_html($item->revision_notes)); ?></td>
                         </tr>
                     <?php endforeach; ?>
                 </tbody>
             </table>
         <?php else: ?><p>No items were marked as "Found" in this audit session.</p><?php endif; ?>
 
         <h3>Missing Items (<?php echo count($missing_items); ?>)</h3>
         <?php if (!empty($missing_items)) : ?>
              <table class="audit-report-table">
                 <thead><tr><th>Asset ID</th><th>Asset Details</th><th>Last Seen On</th></tr></thead>
                 <tbody>
                     <?php foreach ($missing_items as $item) : 
                         $stock_details = $stock_data_cache[$item->stock_entry_id] ?? null;
                         $master_details = $stock_details ? ($master_data_cache[$stock_details['linked_item_id']] ?? null) : null;
                         $last_seen_date = $wpdb->get_var($wpdb->prepare("SELECT audit_timestamp FROM $log_table_name WHERE full_asset_id = %s AND is_found = 1 AND audit_timestamp < %s ORDER BY audit_timestamp DESC LIMIT 1", $item->full_asset_id, $item->audit_timestamp));
                         $purchase_date_obj = !empty($stock_details['purchase_date']) ? DateTime::createFromFormat('Ymd', $stock_details['purchase_date']) : null;
                         if (!$last_seen_date && $purchase_date_obj) {
                             $last_seen_date = $purchase_date_obj->format('Y-m-d H:i:s');
                         }
                     ?>
                         <tr>
                             <td><?php echo esc_html($item->full_asset_id); ?></td>
                              <td>
                                <button type="button" class="view-details-btn"
                                    data-stock-id="<?php echo esc_attr($item->stock_entry_id); ?>"
                                    data-asset-id="<?php echo esc_attr($item->full_asset_id); ?>"
                                >View Details</button>
                             </td>
                             <td><?php echo $last_seen_date ? esc_html(date("d/m/Y", strtotime($last_seen_date))) : 'Date Not Available'; ?></td>
                         </tr>
                     <?php endforeach; ?>
                 </tbody>
             </table>
         <?php else: ?><p>No items were marked as "Missing" in this audit session.</p><?php endif; ?>
     </div>
     <?php
     // The Modal HTML and controlling JavaScript have been removed from this snippet.
     // They now live in the reusable 'inventory_modal_snippet'.
     return ob_get_clean();
 }
 