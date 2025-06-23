<?php
/**
 * LOCATION VIEW SNIPPET V5 (With Details Modal & All Functions)
 * --------------------------------
 * PURPOSE: Provides a shortcode [location_view] with an advanced auditing interface.
 * - Relies on the new reusable modal snippet for displaying asset details.
 */

add_shortcode("location_view", "render_location_view_shortcode");

function render_location_view_shortcode($atts)
{
    if (!current_user_can("auditor") && !current_user_can("manage_options")) {
        return "<p>You do not have permission to view this page.</p>";
    }
    ob_start();
    render_auditor_view_v5();
    return ob_get_clean();
}

/**
 * Renders the advanced Auditor interface with file upload and modal capabilities.
 */
function render_auditor_view_v5()
{
    $locations = get_terms(["taxonomy" => "location", "hide_empty" => true]);
    $current_location_filter = isset($_GET["filter_location"])
        ? (int) $_GET["filter_location"]
        : 0;
    ?>
    <style>
        .audit-switch { cursor: pointer; padding: 5px 10px; border-radius: 4px; text-align: center; font-weight: bold; min-width: 100px; transition: background-color 0.3s; user-select: none; }
        .audit-switch[data-state="pending"] { background-color: #ffc107; color: #fff; }
        .audit-switch[data-state="found"] { background-color: #4caf50; color: #fff; }
        .audit-switch[data-state="not-found"] { background-color: #f44336; color: #fff; }
        .audit-item { display: flex; flex-wrap: wrap; align-items: center; gap: 15px; padding: 10px; border-bottom: 1px solid #eee; }
        .audit-item-label { flex-grow: 1; }
        .audit-controls { display: none; margin-left: 10px; }
        .audit-revision-wrapper { display: none; width: 100%; margin-left: 125px; margin-top: 10px; border-left: 2px solid #ffc107; padding: 10px; background: #fffbe6; }
        #audit-actions { margin-top: 30px; display: flex; gap: 20px; }
        .saving-indicator { margin-left: 10px; font-style: italic; color: #007cba; display: none; }
    </style>
	<script src="https://cdn.jsdelivr.net/npm/browser-image-compression@2.0.1/dist/browser-image-compression.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>

    <div class="inventory-filter-form" style="margin-bottom: 30px; padding: 20px; background-color: #f9f9f9; border-radius: 8px;">
        <form id="location-filter-form" method="GET" action="">
            <label for="filter_location" style="font-weight: bold; margin-right: 10px;">Select Location to Audit:</label>
            <select name="filter_location" id="filter_location">
                <option value="0">— Select a Location —</option>
                <?php foreach ($locations as $location): ?>
                    <option value="<?php echo esc_attr(
                        $location->term_id
                    ); ?>" <?php selected(
    $current_location_filter,
    $location->term_id
); ?>><?php echo esc_html($location->name); ?></option>
                <?php endforeach; ?>
            </select>
        </form>
		<?php if ($current_location_filter): ?>
        <button type="button" class="js-start-qr-scan">Scan QR Code</button>
        <?php endif; ?>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('filter_location')?.addEventListener('change', function() { this.form.submit(); });
        });
    </script>
    <?php
    if (!$current_location_filter) {
        echo "<p>Please select a location to begin the audit.</p>";
        return;
    }

    $location_term = get_term($current_location_filter, "location");
    if (!$location_term || is_wp_error($location_term)) {
        echo "<p>Error: Invalid location.</p>";
        return;
    }

    $child_locations = get_term_children($current_location_filter, "location");
    $all_location_ids = array_merge(
        [$current_location_filter],
        $child_locations
    );

    $stock_entries_query = new WP_Query([
        "post_type" => "stock_entry",
        "posts_per_page" => -1,
        "tax_query" => [
            [
                "taxonomy" => "location",
                "field" => "term_id",
                "terms" => $all_location_ids,
            ],
        ],
    ]);

    if (!$stock_entries_query->have_posts()) {
        echo "<h2>Audit for " .
            esc_html($location_term->name) .
            "</h2><p>No items recorded for this location.</p>";
        return;
    }

    $audit_session_id =
        "audit-" .
        date("Ymd") .
        "_" .
        $current_location_filter .
        "_" .
        get_current_user_id();
    ?>
    <h2>Auditing: <?php echo esc_html($location_term->name); ?></h2>
    <p><strong>Audit Session ID:</strong> <?php echo $audit_session_id; ?></p>
    <div id="audit-form">
        <?php wp_nonce_field("audit_save_nonce", "audit_nonce"); ?>
        <div class="audit-list">
            <?php
            while ($stock_entries_query->have_posts()) {
                $stock_entries_query->the_post();
                $stock_id = get_the_ID();
                $linked_item = get_field("linked_item");
                $master_item_id = $linked_item ? $linked_item->ID : 0;

                $base_id = $master_item_id
                    ? get_field("base_id", $master_item_id)
                    : "NO-BASE-ID";
                $start_id = (int) get_field("starting_unit_id");
                $quantity = (int) get_field("quantity");

                $purchase_date_obj = get_field("purchase_date")
                    ? DateTime::createFromFormat(
                        "Ymd",
                        get_field("purchase_date")
                    )
                    : null;
                $dep_end_date_obj = get_field("depreciation_end_date")
                    ? DateTime::createFromFormat(
                        "Ymd",
                        get_field("depreciation_end_date")
                    )
                    : null;

                for ($i = 0; $i < $quantity; $i++) {

                    $unit_number = str_pad(
                        $start_id + $i,
                        3,
                        "0",
                        STR_PAD_LEFT
                    );
                    $full_asset_id = $base_id . "-" . $unit_number;
                    ?>
                    <div class="audit-item" data-full-id="<?php echo esc_attr(
                        $full_asset_id
                    ); ?>" data-stock-id="<?php echo esc_attr(
    $stock_id
); ?>" data-unit-index="<?php echo esc_attr($i); ?>">
                        <div class="audit-switch" data-state="pending">Pending</div>
                        <div class="audit-item-label"><?php echo esc_html(
                            $full_asset_id
                        ); ?> - <?php echo esc_html(
     $linked_item->post_title
 ); ?></div>
                        
                        <div class="audit-controls">
                            <select class="audit-condition-select">
                                <option value="Okay">Okay</option>
                                <option value="Needs Revision">Needs Revision</option>
                            </select>
                        </div>

                        <button type="button" class="view-details-btn" 
                            data-asset-id="<?php echo esc_attr(
                                $full_asset_id
                            ); ?>"
                            data-image-url="<?php echo esc_attr(
                                get_the_post_thumbnail_url(
                                    $master_item_id,
                                    "medium"
                                )
                            ); ?>"
                            data-title="<?php echo esc_attr(
                                $linked_item->post_title
                            ); ?>"
                            data-brand="<?php echo esc_attr(
                                get_field("brand", $master_item_id)
                            ); ?>"
                            data-desc="<?php echo esc_attr(
                                get_field("short_description", $master_item_id)
                            ); ?>"
                            data-price="<?php echo esc_attr(
                                get_field("item_price")
                            ); ?>"
                            data-purchase-date="<?php echo esc_attr(
                                $purchase_date_obj
                                    ? $purchase_date_obj->format("d/m/Y")
                                    : "N/A"
                            ); ?>"
                            data-usable-life="<?php echo esc_attr(
                                get_field("usable_life", $master_item_id)
                            ); ?>"
                            data-dep-status="<?php echo esc_attr(
                                get_field("depreciation_status")
                            ); ?>"
                            data-dep-end-date="<?php echo esc_attr(
                                $dep_end_date_obj
                                    ? $dep_end_date_obj->format("d/m/Y")
                                    : "N/A"
                            ); ?>"
                        >Details</button>
                        
                        <div class="audit-revision-wrapper">
                             <div class="needs-revision-form">
                                <p>
									<label>Photo:</label><br>
									<select class="photo-action-select">
										<option value="">— Select Action —</option>
										<option value="upload">Upload a Picture</option>
										<option value="capture">Take a Picture</option>
									</select>
									<span class="file-name-display" style="margin-left: 10px; font-style: italic;"></span>
									<!-- Hidden inputs for file selection -->
									<input type="file" class="audit-photo-upload" style="display:none;" accept="image/*">
									<input type="file" class="audit-photo-capture" style="display:none;" accept="image/*" capture="environment">
								</p>
                                <p><label>Notes:</label><br><textarea class="audit-notes" rows="2" style="width:100%;"></textarea></p>
                            </div>
                        </div>
                    </div>
                    <?php
                }
            }
            wp_reset_postdata();
            ?>
        </div>
        <div id="audit-actions">
            <button type="button" id="verify-location-btn"><?php echo esc_html(
                $location_term->name
            ); ?> is Verified</button>
            <span class="saving-indicator">Saving...</span>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const auditSessionId = '<?php echo $audit_session_id; ?>';
        const states = ['pending', 'found', 'not-found'];
        const stateLabels = { pending: 'Pending', found: 'Found', 'not-found': 'Not Found' };
		
		// --- NEW: EVENT LISTENER FOR THE REUSABLE SCANNER ---
		// This 'listens' for the broadcast from the other snippet.
		document.addEventListener('qrCodeScanned', function(event) {
			const id = event.detail.id; // Get the ID from the event

			// This is the page-specific logic that runs after a scan.
			const targetItem = document.querySelector(`.audit-item[data-full-id="${id}"]`);

			if (targetItem) {
				// Mark the item as found
				const switchEl = targetItem.querySelector('.audit-switch');
				if(switchEl.dataset.state !== 'found') {
					if(switchEl.dataset.state === 'pending') { switchEl.click(); }
					if(switchEl.dataset.state === 'not-found') { switchEl.click(); switchEl.click(); }
				}
				targetItem.style.backgroundColor = '#d4edda';
				targetItem.scrollIntoView({ behavior: 'smooth', block: 'center' });
			} else {
				// If the item isn't in the list for this location
				alert(`Error: Item ${id} was scanned but is not expected in this location.`);
			}
		});
		
        // Audit interaction logic
        document.querySelectorAll('.audit-switch').forEach(switchEl => {
            switchEl.addEventListener('click', function() {
                const item = this.closest('.audit-item');
                const controls = item.querySelector('.audit-controls');
                const conditionSelect = item.querySelector('.audit-condition-select');
                const currentState = this.dataset.state;
                const nextIndex = (states.indexOf(currentState) + 1) % states.length;
                const nextState = states[nextIndex];
                this.dataset.state = nextState;
                this.textContent = stateLabels[nextState];
                controls.style.display = (nextState === 'found') ? 'inline-block' : 'none';
                conditionSelect.dispatchEvent(new Event('change'));
            });
        });

        document.querySelectorAll('.audit-condition-select').forEach(select => {
            select.addEventListener('change', function() {
                const revisionWrapper = this.closest('.audit-item').querySelector('.audit-revision-wrapper');
                revisionWrapper.style.display = (this.value === 'Needs Revision') ? 'block' : 'none';
            });
        });

        document.getElementById('verify-location-btn').addEventListener('click', function() { saveAllAuditData(this); });
		// NEW: Event listeners for photo actions
		document.querySelectorAll('.audit-item').forEach(item => {
			const photoActionSelect = item.querySelector('.photo-action-select');
			const fileUploadInput = item.querySelector('.audit-photo-upload');
			const fileCaptureInput = item.querySelector('.audit-photo-capture');
			const fileNameDisplay = item.querySelector('.file-name-display');

			photoActionSelect.addEventListener('change', function() {
				if (this.value === 'upload') {
					fileUploadInput.click();
				} else if (this.value === 'capture') {
					fileCaptureInput.click();
				}
			});

			const handleFileSelect = (event) => {
				if (event.target.files.length > 0) {
					const file = event.target.files[0];
					// Store the file object directly on the item's DOM element for later use
					item.selectedFile = file; 
					fileNameDisplay.textContent = file.name;
					photoActionSelect.value = ''; // Reset dropdown
				}
			};
			fileUploadInput.addEventListener('change', handleFileSelect);
			fileCaptureInput.addEventListener('change', handleFileSelect);
		});
        async function saveAllAuditData(button) {
            const indicator = document.querySelector('.saving-indicator');
            const originalButtonText = button.textContent;
            button.textContent = 'Verifying...';
            button.disabled = true;
            indicator.style.display = 'inline';

            const auditItems = Array.from(document.querySelectorAll('.audit-item'));
            let allItemsChecked = true;

            for (const item of auditItems) {
                if (item.querySelector('.audit-switch').dataset.state === 'pending') {
                    allItemsChecked = false;
                    break;
                }
            }

            if (!allItemsChecked) {
                alert('Please review all items (set to "Found" or "Not Found") before verifying the location.');
                button.textContent = originalButtonText;
                button.disabled = false;
                indicator.style.display = 'none';
                return;
            }
			
			// --- NEW: Validation for "Needs Revision" fields ---
            let validationPassed = true;
            for (const item of auditItems) {
                const state = item.querySelector('.audit-switch').dataset.state;
                const condition = item.querySelector('.audit-condition-select').value;
                const notes = item.querySelector('.audit-notes').value;
                const photoFile = item.selectedFile;

                if (state === 'found' && condition === 'Needs Revision') {
                    // Check if either the notes are empty or no photo has been selected
                    if (!photoFile || notes.trim() === '') {
                        alert(`Item ${item.dataset.fullId} is marked "Needs Revision" but is missing a photo or notes. Please complete the details before verifying.`);
                        
                        // Highlight the problematic item for the user
                        item.style.border = '2px solid red'; 
                        item.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        
                        validationPassed = false;
                        break; // Stop checking on the first error
                    }
                }
                // If the item is okay, remove any previous error highlight
                item.style.border = ''; 
            }

            if (!validationPassed) {
                // Re-enable the button and stop the save process if validation fails
                button.textContent = originalButtonText;
                button.disabled = false;
                indicator.style.display = 'none';
                return; // Stop the function here
            }
            // --- END: Validation ---

            for (const item of auditItems) {
                const state = item.querySelector('.audit-switch').dataset.state;
                const fullId = item.dataset.fullId;
                const stockId = item.dataset.stockId;
                const unitIndex = item.dataset.unitIndex;
                const condition = item.querySelector('.audit-condition-select').value;
                const notes = item.querySelector('.audit-notes').value;
                const photoFile = item.selectedFile; // Use the stored file object
                let photoUrl = '';

                if (state === 'found' && condition === 'Needs Revision' && photoFile) {
                    
                    // --- NEW: Image Compression Logic ---
                    console.log(`Original file size: ${(photoFile.size / 1024 / 1024).toFixed(2)} MB`);
                    const options = {
                        maxSizeMB: 1,          // The maximum file size in megabytes
                        maxWidthOrHeight: 1024, // Resize the image to this dimension
                        useWebWorker: true,    // Use a separate thread for performance
                    }
                    try {
                        const compressedFile = await imageCompression(photoFile, options);
                        console.log(`Compressed file size: ${(compressedFile.size / 1024 / 1024).toFixed(2)} MB`);

                        // --- END: Image Compression Logic ---

                        const photoFormData = new FormData();
                        photoFormData.append('action', 'upload_audit_photo');
                        photoFormData.append('nonce', document.getElementById('audit_nonce').value);
                        // Use the new, smaller file for the upload
                        photoFormData.append('photo', compressedFile, compressedFile.name); 
                        
                        // This part for uploading the photo remains the same as your working version
                        const response = await fetch('<?php echo admin_url(
                            "admin-ajax.php"
                        ); ?>', { method: 'POST', body: photoFormData });
                        const result = await response.json();
                        if (result.success) {
                            photoUrl = result.data.url;
                        } else {
                            throw new Error(result.data.message);
                        }
                    } catch (error) {
                        alert(`Failed to process photo for ${fullId}: ${error.message}`);
                        continue; // Skip saving this item if photo processing fails
                    }
                }
                
                const mainFormData = new FormData();
                mainFormData.append('action', 'save_single_audit_log');
                mainFormData.append('nonce', document.getElementById('audit_nonce').value);
                mainFormData.append('audit_session_id', auditSessionId);
                mainFormData.append('full_asset_id', fullId);
                mainFormData.append('stock_id', stockId);
                mainFormData.append('unit_index', unitIndex);
                mainFormData.append('state', state);
                mainFormData.append('condition', condition);
                mainFormData.append('notes', notes);
                mainFormData.append('photo_url', photoUrl);
                
                try {
                    const response = await fetch('<?php echo admin_url(
                        "admin-ajax.php"
                    ); ?>', { method: 'POST', body: mainFormData });
                    const result = await response.json();
                    if (!result.success) throw new Error(result.data.message);
                    item.style.backgroundColor = '#e8f5e9';
                } catch (error) {
                    alert(`Failed to save data for ${fullId}: ${error.message}`);
                    item.style.backgroundColor = '#ffebee';
                }
            }

            button.textContent = originalButtonText;
            button.disabled = false;
            indicator.style.display = 'none';
            alert('Location Verified Successfully!');
            window.location.reload();
        }
    });
    </script>
    <?php
}

/**
 * AJAX handler for saving a SINGLE audit log to the custom table.
 */
add_action(
    "wp_ajax_save_single_audit_log",
    "handle_ajax_save_single_audit_log"
);
function handle_ajax_save_single_audit_log()
{
    check_ajax_referer("audit_save_nonce", "nonce");
    global $wpdb;
    $table_name = $wpdb->prefix . "inventory_audit_log";

    $state = sanitize_text_field($_POST["state"]);
    $condition = sanitize_text_field($_POST["condition"]);
    $photo_url = esc_url_raw($_POST["photo_url"]);

    // Get master item image as a fallback for 'Okay' items
    if ($state === "found" && $condition === "Okay" && empty($photo_url)) {
        $stock_id = intval($_POST["stock_id"]);
        $linked_item = get_field("linked_item", $stock_id);
        if ($linked_item) {
            $photo_url = get_the_post_thumbnail_url($linked_item->ID, "medium");
        }
    }

    $data = [
        "audit_session_id" => sanitize_text_field($_POST["audit_session_id"]),
        "stock_entry_id" => intval($_POST["stock_id"]),
        "unit_index" => intval($_POST["unit_index"]),
        "full_asset_id" => sanitize_text_field($_POST["full_asset_id"]),
        "is_found" => $state === "found" ? 1 : 0,
        "audit_condition" => $state === "found" ? $condition : "Not Found",
        "revision_notes" => sanitize_textarea_field($_POST["notes"]),
        "revision_photo_url" => $photo_url,
        "auditor_id" => get_current_user_id(),
        "audit_timestamp" => current_time("mysql"),
    ];

    $wpdb->replace($table_name, $data);
    wp_send_json_success(["message" => "Log saved."]);
}

/**
 * AJAX handler for uploading a photo for a "Needs Revision" item.
 * This new version temporarily disables thumbnail generation to save only the single, compressed image.
 */
add_action("wp_ajax_upload_audit_photo", "handle_ajax_upload_audit_photo");
function handle_ajax_upload_audit_photo()
{
    // Security check
    check_ajax_referer("audit_save_nonce", "nonce");

    if (empty($_FILES["photo"])) {
        wp_send_json_error(["message" => "No photo was uploaded."], 400);
    }

    // This is a temporary filter function to prevent WordPress from creating extra image sizes.
    // It tells WordPress that for this specific upload, there are no intermediate sizes to create.
    $disable_image_sizes = function ($sizes) {
        return [];
    };

    // Add the filter right before we handle the upload.
    add_filter("intermediate_image_sizes_advanced", $disable_image_sizes);

    // These files are required for media_handle_upload to work
    require_once ABSPATH . "wp-admin/includes/image.php";
    require_once ABSPATH . "wp-admin/includes/file.php";
    require_once ABSPATH . "wp-admin/includes/media.php";

    // media_handle_upload() will now process the upload, but the filter will stop it from creating extra files.
    $attachment_id = media_handle_upload("photo", 0);

    // IMPORTANT: We remove the filter immediately after the upload so that it doesn't affect
    // any other part of your website (e.g., featured image uploads).
    remove_filter("intermediate_image_sizes_advanced", $disable_image_sizes);

    // Check for errors during the upload
    if (is_wp_error($attachment_id)) {
        wp_send_json_error(
            ["message" => $attachment_id->get_error_message()],
            500
        );
    } else {
        // If successful, get the URL of the single, new media library image
        $image_url = wp_get_attachment_url($attachment_id);
        // And send it back to the JavaScript
        wp_send_json_success(["url" => $image_url]);
    }
}
