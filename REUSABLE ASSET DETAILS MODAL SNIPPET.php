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
        .inventory-details-modal-content { background-color: #fefefe; margin: 10% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 600px; border-radius: 8px; position: relative; box-shadow: 0 5px 15px rgba(0,0,0,0.3); animation-name: animatetop; animation-duration: 0.4s; }
        @keyframes animatetop { from {top: -300px; opacity: 0} to {top: 0; opacity: 1} }
        .inventory-details-modal-close { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
        .modal-details { display: grid; grid-template-columns: 1fr 2fr; gap: 15px; align-items: start; }
        .modal-details img { width: 100%; height: auto; border-radius: 4px; }
    </style>

    <!-- Modal Structure -->
    <div id="inventory-asset-detail-modal" class="inventory-details-modal">
        <div class="inventory-details-modal-content">
            <span class="inventory-details-modal-close">&times;</span>
            <h3 id="modal-title">Asset Details</h3>
            <div class="modal-details">
                <div id="modal-image-container"></div>
                <div>
                    <p><strong>Brand:</strong> <span id="modal-brand"></span></p>
                    <p><strong>Description:</strong> <span id="modal-desc"></span></p>
                    <p><strong>Purchase Price:</strong> $<span id="modal-price"></span></p>
                    <p><strong>Purchase Date:</strong> <span id="modal-purchase-date"></span></p>
                    <p><strong>Usable Life:</strong> <span id="modal-usable-life"></span> years</p>
                    <p><strong>Depreciation Status:</strong> <span id="modal-dep-status"></span></p>
                    <p><strong>Depreciation End Date:</strong> <span id="modal-dep-end-date"></span></p>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('inventory-asset-detail-modal');
        const closeModal = modal.querySelector('.inventory-details-modal-close');

        // Use a single event listener on the document body to catch clicks on any 'view-details-btn'
        document.body.addEventListener('click', function(event) {
            if (event.target && event.target.classList.contains('view-details-btn')) {
                const button = event.target;
                
                // Populate modal from the button's data attributes
                document.getElementById('modal-title').textContent = button.dataset.title + ' (' + button.dataset.assetId + ')';
                document.getElementById('modal-brand').textContent = button.dataset.brand;
                document.getElementById('modal-desc').textContent = button.dataset.desc;
                document.getElementById('modal-price').textContent = button.dataset.price;
                document.getElementById('modal-purchase-date').textContent = button.dataset.purchaseDate;
                document.getElementById('modal-usable-life').textContent = button.dataset.usableLife;
                document.getElementById('modal-dep-status').textContent = button.dataset.depStatus;
                document.getElementById('modal-dep-end-date').textContent = button.dataset.depEndDate;
                
                const imgContainer = document.getElementById('modal-image-container');
                const imageUrl = button.dataset.imageUrl;
                if (imageUrl) {
                    imgContainer.innerHTML = `<img src="${imageUrl}" alt="Asset Image">`;
                } else {
                    imgContainer.innerHTML = '<p>No image available.</p>';
                }

                // Show modal
                modal.style.display = 'block';
            }
        });

        // Close modal listeners
        closeModal.onclick = function() { modal.style.display = 'none'; }
        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    });
    </script>
    <?php
}
