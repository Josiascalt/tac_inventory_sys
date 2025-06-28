<?php

/**
 * INVENTORY DISPLAY SNIPPET
 * --------------------------------
 * PURPOSE: Provides a shortcode [inventory_display] to show a complete list 
 * of all inventory items, their total stock, and a location breakdown.
 * Now includes AJAX-powered filters that update without a page reload.
 * TYPE: PHP
 */

 add_shortcode('inventory_display', 'render_inventory_display_shortcode');

 function render_inventory_display_shortcode($atts) {
     // This function now only sets up the initial structure. 
     // The actual list is loaded via AJAX.
     ob_start();
 
     // --- RENDER THE FILTER FORMS ---
     $locations = get_terms(array('taxonomy' => 'location', 'hide_empty' => false));
     $categories = get_terms(array('taxonomy' => 'item_category', 'hide_empty' => false));
     ?>
     <div class="inventory-filter-form" style="margin-bottom: 30px; padding: 20px; background-color: #f9f9f9; border-radius: 8px;">
         <form id="inventory-filters" method="POST" action="">
             <div style="display: flex; gap: 20px; align-items: center;">
                 <div>
                     <label for="filter_location" style="font-weight: bold; display: block; margin-bottom: 5px;">Filter by Location:</label>
                     <select name="filter_location" id="filter_location" class="inventory-filter-select">
                         <option value="0">All Locations</option>
                         <?php foreach ($locations as $location) : ?>
                             <option value="<?php echo esc_attr($location->term_id); ?>">
                                 <?php echo esc_html($location->name); ?>
                             </option>
                         <?php endforeach; ?>
                     </select>
                 </div>
                 <div>
                     <label for="filter_category" style="font-weight: bold; display: block; margin-bottom: 5px;">Filter by Category:</label>
                     <select name="filter_category" id="filter_category" class="inventory-filter-select">
                         <option value="0">All Categories</option>
                         <?php foreach ($categories as $category) : ?>
                             <option value="<?php echo esc_attr($category->term_id); ?>">
                                 <?php echo esc_html($category->name); ?>
                             </option>
                         <?php endforeach; ?>
                     </select>
                 </div>
             </div>
         </form>
     </div>
 
     <!-- This container will be filled with the inventory list by AJAX -->
     <div id="inventory-display-container" class="inventory-display-container">
         <p>Loading inventory...</p>
     </div>
 
     <?php // --- JAVASCRIPT FOR AJAX FILTERING --- ?>
     <script>
     document.addEventListener('DOMContentLoaded', function() {
         const filterForm = document.getElementById('inventory-filters');
         const displayContainer = document.getElementById('inventory-display-container');
         const selects = filterForm.querySelectorAll('.inventory-filter-select');
 
         function fetchInventory() {
             // Show a loading state
             displayContainer.innerHTML = '<p>Loading...</p>';
 
             const formData = new FormData(filterForm);
             formData.append('action', 'filter_inventory_items');
             formData.append('nonce', '<?php echo wp_create_nonce("inventory_filter_nonce"); ?>');
 
             fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                 method: 'POST',
                 body: formData
             })
             .then(response => response.text())
             .then(html => {
                 displayContainer.innerHTML = html;
             })
             .catch(error => {
                 displayContainer.innerHTML = '<p>An error occurred. Please try again.</p>';
                 console.error('Error:', error);
             });
         }
 
         // Add event listeners to each select dropdown
         selects.forEach(function(select) {
             select.addEventListener('change', fetchInventory);
         });
 
         // Initial load of all inventory items
         fetchInventory();
     });
     </script>
     <?php
     
     return ob_get_clean();
 }
 
 /**
  * AJAX Handler Function
  * ---------------------
  * This new function handles the background request from the JavaScript.
  * It builds the HTML for the filtered list and sends it back.
  */
 add_action('wp_ajax_filter_inventory_items', 'handle_ajax_filter_inventory');
 add_action('wp_ajax_nopriv_filter_inventory_items', 'handle_ajax_filter_inventory'); // For non-logged-in users if needed
 
 function handle_ajax_filter_inventory() {
     check_ajax_referer('inventory_filter_nonce', 'nonce');
 
     $location_id = isset($_POST['filter_location']) ? (int)$_POST['filter_location'] : 0;
     $category_id = isset($_POST['filter_category']) ? (int)$_POST['filter_category'] : 0;
     
     // --- PREPARE THE QUERY ARGUMENTS ---
     $main_args = array(
         'post_type'      => 'inventory_item',
         'posts_per_page' => -1,
         'post_status'    => 'publish',
         'orderby'        => 'title',
         'order'          => 'ASC',
     );
 
     if ($location_id) {
         $stock_in_location_args = array('post_type' => 'stock_entry', 'posts_per_page' => -1, 'tax_query' => array(array('taxonomy' => 'location', 'field' => 'term_id', 'terms' => $location_id)), 'fields' => 'ids');
         $stock_entry_ids = get_posts($stock_in_location_args);
         if (empty($stock_entry_ids)) { echo '<p>No items found in the selected location.</p>'; wp_die(); }
         $master_item_ids = array_unique(array_map(function($stock_id) { return get_field('linked_item', $stock_id, false); }, $stock_entry_ids));
         if (empty($master_item_ids)) { echo '<p>No items found in the selected location.</p>'; wp_die(); }
         $main_args['post__in'] = $master_item_ids;
     }
 
     if ($category_id) {
         $main_args['tax_query'] = array(array('taxonomy' => 'item_category', 'field' => 'term_id', 'terms' => $category_id));
     }
 
     $inventory_query = new WP_Query($main_args);
 
     if ( ! $inventory_query->have_posts() ) {
         echo '<p>No inventory items match the current filter(s).</p>';
         wp_die();
     }
 
     // --- RENDER THE HTML FOR THE RESULTS ---
     while ( $inventory_query->have_posts() ) {
         $inventory_query->the_post();
         $master_item_id = get_the_ID();
         $brand = get_field('brand', $master_item_id);
         $short_description = get_field('short_description', $master_item_id);
         $usable_life = get_field('usable_life', $master_item_id);
 
         $stock_entries_args = array('post_type' => 'stock_entry', 'posts_per_page' => -1, 'meta_query' => array(array('key' => 'linked_item', 'value' => $master_item_id, 'compare' => '=')));
         if ($location_id) {
             $stock_entries_args['tax_query'] = array(array('taxonomy' => 'location', 'field' => 'term_id', 'terms' => $location_id));
         }
         $stock_entries_query = new WP_Query($stock_entries_args);
 
         $total_quantity = 0; $locations_breakdown = []; $statuses = [];
         if ($stock_entries_query->have_posts()) {
             while ($stock_entries_query->have_posts()) {
                 $stock_entries_query->the_post();
                 $stock_post_id = get_the_ID();
                 $quantity = (int) get_field('quantity', $stock_post_id);
                 $total_quantity += $quantity;
                 $location_terms = get_the_terms($stock_post_id, 'location');
                 if ($location_terms && !is_wp_error($location_terms)) {
                     foreach ($location_terms as $location_term) {
                         $location_name = $location_term->name;
                         if (!isset($locations_breakdown[$location_name])) { $locations_breakdown[$location_name] = 0; }
                         $locations_breakdown[$location_name] += $quantity;
                     }
                 }
                 $status = get_field('current_status', $stock_post_id);
                 if ($status) { if (!isset($statuses[$status])) { $statuses[$status] = 0; } $statuses[$status] += $quantity; }
             }
         }
         wp_reset_postdata();
 
         if ($location_id && $total_quantity === 0) { continue; }
         ?>
         <div class="inventory-master-item" id="inventory-item-<?php echo $master_item_id; ?>">
             <div class="item-header">
                 <?php if (has_post_thumbnail()) : ?><img src="<?php the_post_thumbnail_url('medium'); ?>" alt="<?php the_title(); ?>"><?php endif; ?>
                 <div class="item-title-area">
                     <h2><?php the_title(); ?></h2>
                     <?php if ($brand) : ?><p class="item-brand"><?php echo esc_html($brand); ?></p><?php endif; ?>
                     <?php if ($short_description) : ?><p class="item-description"><?php echo esc_html($short_description); ?></p><?php endif; ?>
                 </div>
             </div>
             <div class="item-details-grid">
                 <div class="detail-box"><strong><?php echo $location_id ? 'Quantity in Location' : 'Total Quantity'; ?></strong><span><?php echo $total_quantity; ?></span></div>
                 <div class="detail-box">
                     <strong>Status Breakdown</strong>
                     <?php if (!empty($statuses)): ?><ul><?php foreach($statuses as $name => $count): ?><li><?php echo esc_html($name); ?>: <?php echo $count; ?></li><?php endforeach; ?></ul><?php else: ?><span>N/A</span><?php endif; ?>
                 </div>
                 <?php if ($usable_life) : ?><div class="detail-box"><strong>Usable Life</strong><span><?php echo esc_html($usable_life); ?> years</span></div><?php endif; ?>
             </div>
             <?php if (!empty($locations_breakdown) && !$location_id): ?>
                 <div class="detail-box">
                     <strong>Location Breakdown</strong>
                     <table class="stock-breakdown-table">
                         <thead><tr><th>Location</th><th>Quantity</th></tr></thead>
                         <tbody><?php foreach ($locations_breakdown as $name => $qty): ?><tr><td><?php echo esc_html($name); ?></td><td><?php echo $qty; ?></td></tr><?php endforeach; ?></tbody>
                     </table>
                 </div>
             <?php endif; ?>
         </div>
         <?php
     }
     wp_reset_postdata();
     wp_die(); // Important for AJAX handlers
 }
 
 