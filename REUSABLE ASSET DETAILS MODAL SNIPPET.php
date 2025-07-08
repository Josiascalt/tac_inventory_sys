<?php
/**
 * REUSABLE ASSET DETAILS MODAL SNIPPET
 * --------------------------------
 * PURPOSE: Creates a reusable pop-up modal for displaying asset details.
 * This snippet adds the modal HTML and controlling JavaScript to the footer of your site.
 * It works automatically with any button that has the class "view-details-btn" and the correct data attributes.
 * TYPE: PHP
 */

add_action('wp_footer', 'render_reusable_inventory_modal');

function render_reusable_inventory_modal() {
    // We only need to add this modal once on any given page.
    // The JavaScript will handle showing/hiding it as needed.
    ?>
    <style>
        .inventory-details-modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); }
        .inventory-details-modal-content { background-color: #fefefe; margin: 10% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 700px; border-radius: 8px; position: relative; box-shadow: 0 5px 15px rgba(0,0,0,0.3); animation-name: animatetop; animation-duration: 0.4s; }
        @keyframes animatetop { from {top: -300px; opacity: 0} to {top: 0; opacity: 1} }
        .inventory-details-modal-close { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
        .modal-details { margin-top: 20px; }
        .modal-details img {
            width: 100%;
            max-width: 400px; /* You can adjust this value */
            height: auto;
            display: block;
            margin: 0 auto 25px auto; /* Centers the image and adds space below */
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        } 
        .modal-details p {
            margin: 10px 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        .modal-details p:last-child {
            border-bottom: none;
        }
        .modal-details strong {
            color: #555;
        }
    </style>

    <!-- Modal Structure -->
    <div id="inventory-asset-detail-modal" class="inventory-details-modal">
        <div class="inventory-details-modal-content">
            <span class="inventory-details-modal-close">&times;</span>
            <h3 id="modal-title" style="text-align: center;">Asset Details</h3>
            <div class="modal-details">
                <div id="modal-image-container"></div>
                <div>
                    <p><strong>Brand:</strong> <span id="modal-brand"></span></p>
                    <p><strong>Description:</strong> <span id="modal-desc"></span></p>
                    <p><strong>Current Location:</strong> <span id="modal-location"></span></p>
                    <p><strong>Purchase Price:</strong> $<span id="modal-price"></span></p>
                    <p><strong>Purchase Date:</strong> <span id="modal-purchase-date"></span></p>
                    <p><strong>Usable Life:</strong> <span id="modal-usable-life"></span> years</p>
                    <p><strong>Depreciation Status:</strong> <span id="modal-dep-status"></span></p>
                    <p><strong>Depreciation End Date:</strong> <span id="modal-dep-end-date"></span></p>
                    <!-- Add this new paragraph for the mismatch link -->
                    <p id="modal-mismatch-container" style="margin-top: 20px; font-size: 0.9em; text-align: center;">
                        If the item above does not match, please <a href="#" id="modal-mismatch-link" style="color: #d9534f; font-weight: bold;">press here</a>.
                    </p>
                </div>
            </div>
        </div>
    </div>

   <script>
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('inventory-asset-detail-modal');
            const closeModalBtn = modal.querySelector('.inventory-details-modal-close');

            // Get all the spans where we will put the data
            const titleEl = document.getElementById('modal-title');
            const brandEl = document.getElementById('modal-brand');
            const descEl = document.getElementById('modal-desc');
            const locationEl = document.getElementById('modal-location');
            const priceEl = document.getElementById('modal-price');
            const purchaseDateEl = document.getElementById('modal-purchase-date');
            const usableLifeEl = document.getElementById('modal-usable-life');
            const depStatusEl = document.getElementById('modal-dep-status');
            const depEndDateEl = document.getElementById('modal-dep-end-date');
            const imgContainer = document.getElementById('modal-image-container');

            // Function to reset the modal to a "loading" state
            function resetModal() {
                titleEl.textContent = 'Loading...';
                brandEl.textContent = '...';
                descEl.textContent = '...';
                locationEl.textContent = '...';
                priceEl.textContent = '...';
                purchaseDateEl.textContent = '...';
                usableLifeEl.textContent = '...';
                depStatusEl.textContent = '...';
                depEndDateEl.textContent = '...';
                imgContainer.innerHTML = '<p>Loading image...</p>';
            }

            // Use a single event listener on the document body
            document.body.addEventListener('click', function(event) {
                // Find the closest button with the correct class
                const detailsButton = event.target.closest('.view-details-btn');
                if (detailsButton) {
                    
                    // Get the stock ID from the button
                    const stockId = detailsButton.dataset.stockId;
                    if (!stockId) return;

                    // Show the modal and put it in a loading state
                    resetModal();
                    modal.style.display = 'block';

                    // Prepare the data to send to the server
                    const formData = new FormData();
                    formData.append('action', 'get_asset_details');
                    formData.append('stock_id', stockId);
                    // We don't need a nonce here as it's a read-only action

                    // Fetch the details from our new "brain"
                    fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(result => {
                        if (result.success) {
                            const data = result.data;
                            // Populate the modal with the data from the server
                            titleEl.textContent = data.title + ' (' + detailsButton.dataset.assetId + ')';
                            brandEl.textContent = data.brand;
                            descEl.textContent = data.description;
                            locationEl.innerHTML = data.location; // Use innerHTML to render links if any
                            priceEl.textContent = data.price;
                            purchaseDateEl.textContent = data.purchase_date;
                            usableLifeEl.textContent = data.usable_life;
                            depStatusEl.textContent = data.dep_status;
                            depEndDateEl.textContent = data.dep_end_date;
                            
                            if (data.image_url) {
                                imgContainer.innerHTML = `<img src="${data.image_url}" alt="Asset Image">`;
                            } else {
                                imgContainer.innerHTML = '<p>No image available.</p>';
                            }

                        } else {
                            titleEl.textContent = 'Error';
                            descEl.textContent = 'Could not load item details.';
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching asset details:', error);
                        titleEl.textContent = 'Error';
                        descEl.textContent = 'A network error occurred.';
                    });
                }
            });

            // Close modal listeners
            closeModalBtn.onclick = function() { modal.style.display = 'none'; }
            window.onclick = function(event) {
                if (event.target == modal) {
                    modal.style.display = 'none';
                }
            }
        });
        </script>
    <?php
}
