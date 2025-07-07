        <?php
        /**
         * INVENTORY SEARCH SNIPPET
         * --------------------------------
         * PURPOSE: Provides a shortcode [inventory_search] that renders a live search bar
         * for finding inventory items based on various criteria.
         * TYPE: PHP
         */

        add_shortcode('inventory_search', 'render_inventory_search_shortcode');

        function render_inventory_search_shortcode() {
            ob_start();
            ?>
            <style>
                .search-results-container.is-visible {
                    display: block;
                }
                .inventory-search-component-wrapper {position: relative; /* This is the key change */}
                .inventory-search-input { width: 50vw; padding: 5px; font-size: 1.2em; border-radius: 25px !important; border: 1px solid #ccc; }
                .search-results-container {margin-top: 20px; position: absolute; top: 100%; /* Position it right below the search bar */ left: 0; right: 0; z-index: 999; /* Ensure it appears on top of other content */ background: #ffffff;
                    border: 1px solid #ddd;
                    border-top: none;
                    border-radius: 0 0 8px 8px;
                    box-shadow: 0 5px 10px rgba(0,0,0,0.1);
                    max-height: 400px; /* Prevent it from being too long */
                    overflow-y: auto; /* Add a scrollbar if there are many results */
                    display: none;
                }
                .search-feedback { padding: 20px; text-align: center; color: #777; }
                .inventory-master-item { border: 1px solid #e0e0e0; border-radius: 8px; padding: 20px; background: #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.05); margin-bottom: 20px; }
                .item-header { display: flex; gap: 20px; align-items: flex-start; }
                .item-header img { width: 100px; height: 100px; object-fit: cover; border-radius: 4px; flex-shrink: 0; }
                .item-title-area h2 { margin: 0 0 5px 0; font-size: 1.5em; }
                
                /* New style for highlighting the found item */
                .inventory-item-highlight {
                    box-shadow: 0 0 15px 5px #ffc107 !important;
                    transition: box-shadow 0.3s ease-in-out;
                }
            </style>
            <!-- Add this new wrapper div -->
            <div class="inventory-search-component-wrapper">
                <div class="inventory-search-wrapper">
                    <input type="text" id="inventory-search-input" class="inventory-search-input" placeholder="Search by ID, Name, Brand, Description...">
                    <?php wp_nonce_field('inventory_search_nonce', 'search_nonce'); ?>
                </div>
                
                <div id="search-results-container" class="search-results-container">
                    <!-- Live search results will appear here -->
                </div>
            </div> <!-- Close the new wrapper div -->

            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const searchInput = document.getElementById('inventory-search-input');
                const resultsContainer = document.getElementById('search-results-container');
                const nonce = document.getElementById('search_nonce').value;
                let debounceTimer;

                searchInput.addEventListener('input', function() {
                    clearTimeout(debounceTimer);
                    const searchTerm = this.value;

                    debounceTimer = setTimeout(() => {
                        if (searchTerm.length > 2 || searchTerm.length === 0) {
                            performSearch(searchTerm);
                        }
                    }, 300);
                });

                // NEW: Event listener for clicking a search result
                resultsContainer.addEventListener('click', function(event) {
                    // Check if a result link was clicked
                    const targetLink = event.target.closest('.search-result-link');
                    if (targetLink) {
                        event.preventDefault();
                        const targetId = targetLink.getAttribute('href'); // e.g., "#inventory-item-123"
                        const targetElement = document.querySelector(targetId);

                        if (targetElement) {
                            // Scroll to the element
                            targetElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            
                            // Add a temporary highlight
                            targetElement.classList.add('inventory-item-highlight');
                            setTimeout(() => {
                                targetElement.classList.remove('inventory-item-highlight');
                            }, 2500); // Highlight lasts for 2.5 seconds

                            // Clear the search
                            searchInput.value = '';
                            resultsContainer.innerHTML = '';
                        }
                    }
                });

                function performSearch(term) {
                    // If the search term is empty, just hide the results and stop.
                    if (!term) {
                        resultsContainer.innerHTML = '';
                        resultsContainer.classList.remove('is-visible');
                        return;
                    }

                    resultsContainer.innerHTML = '<p class="search-feedback">Searching...</p>';
                    resultsContainer.classList.add('is-visible'); // Show the container with the "Searching..." message

                    const formData = new FormData();
                    formData.append('action', 'search_inventory');
                    formData.append('nonce', nonce);
                    formData.append('search_term', term);

                    fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.text())
                    .then(html => {
                        resultsContainer.innerHTML = html;
                        // This is a final check. If the server returned empty, hide the container again.
                        if (!html.trim()) {
                            resultsContainer.classList.remove('is-visible');
                        }
                    })
                    .catch(error => {
                        resultsContainer.innerHTML = '<p class="search-feedback">An error occurred.</p>';
                        console.error('Search Error:', error);
                    });
                }
            });
            </script>
            <?php
            return ob_get_clean();
        }


        /**
         * AJAX handler to process the live search request.
         */
        add_action('wp_ajax_search_inventory', 'handle_ajax_search_inventory');
        function handle_ajax_search_inventory() {
            check_ajax_referer('inventory_search_nonce', 'nonce');

            $search_term = isset($_POST['search_term']) ? sanitize_text_field($_POST['search_term']) : '';

            if (empty($search_term)) {
                wp_die();
            }

            global $wpdb;
            $master_item_ids = [];

            // --- Search Strategy ---
            $query1 = new WP_Query(['post_type' => 'inventory_item', 's' => $search_term, 'fields' => 'ids']);
            if ($query1->have_posts()) { $master_item_ids = array_merge($master_item_ids, $query1->posts); }

            $query2 = new WP_Query([
                'post_type' => 'inventory_item',
                'meta_query' => ['relation' => 'OR',
                    ['key' => 'brand', 'value' => $search_term, 'compare' => 'LIKE'],
                    ['key' => 'short_description', 'value' => $search_term, 'compare' => 'LIKE'],
                    ['key' => 'base_id', 'value' => $search_term, 'compare' => 'LIKE'],
                ],
                'fields' => 'ids'
            ]);
            if ($query2->have_posts()) { $master_item_ids = array_merge($master_item_ids, $query2->posts); }
            
            $log_table_name = $wpdb->prefix . 'inventory_audit_log';
            $found_stock_id = $wpdb->get_var($wpdb->prepare("SELECT stock_entry_id FROM $log_table_name WHERE full_asset_id = %s LIMIT 1", $search_term));
            if ($found_stock_id) {
                $linked_item_id = get_post_meta($found_stock_id, 'linked_item', true);
                if ($linked_item_id) { $master_item_ids[] = $linked_item_id; }
            }

            // --- Render Results ---
            $unique_ids = array_unique($master_item_ids);

            if (empty($unique_ids)) {
                echo '<p class="search-feedback">No items found matching your search.</p>';
                wp_die();
            }

            $results_query = new WP_Query(['post_type' => 'inventory_item', 'post__in' => $unique_ids, 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC']);

            if ($results_query->have_posts()) {
                while ($results_query->have_posts()) {
                    $results_query->the_post();
                    $master_item_id = get_the_ID();
                    $brand = get_field('brand', $master_item_id);
                    ?>
                    <div class="inventory-master-item">
                        <div class="item-header">
                            <?php if (has_post_thumbnail()) : ?>
                                <img src="<?php the_post_thumbnail_url('thumbnail'); ?>" alt="<?php the_title(); ?>">
                            <?php endif; ?>
                            <div class="item-title-area">
                                <!-- MODIFIED: The link now points to an anchor ID and has a class for JS targeting -->
                                <h2><a href="#inventory-item-<?php echo $master_item_id; ?>" class="search-result-link"><?php the_title(); ?></a></h2>
                                <?php if ($brand) : ?><p class="item-brand"><?php echo esc_html($brand); ?></p><?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php
                }
                wp_reset_postdata();
            } else {
                echo '<p class="search-feedback">No items found matching your search.</p>';
            }

            wp_die();
        }
