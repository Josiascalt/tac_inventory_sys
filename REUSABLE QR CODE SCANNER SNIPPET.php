<?php
/**
 * REUSABLE QR CODE SCANNER SNIPPET (V9 - Hybrid Approach)
 * --------------------------------
 * PURPOSE: Adds a high-performance QR code scanner to your site.
 * - For Android: Uses the high-performance nimiq/qr-scanner library.
 * - For iOS & others: Uses the reliable jsQR library as a fallback.
 * TYPE: PHP
 */

add_action('wp_footer', 'render_reusable_qr_scanner_modal');

function render_reusable_qr_scanner_modal() {
    ?>
    <style>
        #qr-scanner-modal { display: none; position: fixed; z-index: 1050; left: 0; top: 0; width: 100%; height: 100%; background-color: #000; }
        #qr-video-container { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 90%; max-width: 500px; }
        #qr-video-element { width: 100%; border-radius: 8px; border: 2px solid white; }
        #qr-close-btn { position: absolute; top: 15px; right: 20px; font-size: 2.5em; color: white; cursor: pointer; text-shadow: 0 0 5px rgba(0,0,0,0.5); z-index: 10; }
        #qr-feedback { text-align: center; color: white; font-size: 1.2em; position: absolute; bottom: 5%; width: 100%; left: 0; padding: 0 10px; }
    </style>

    <!-- QR Code Scanner Modal HTML -->
    <div id="qr-scanner-modal">
        <div id="qr-video-container">
            <video id="qr-video-element" playsinline></video>
        </div>
        <span id="qr-close-btn">&times;</span>
        <div id="qr-feedback">Point camera at a QR code</div>
        <!-- This canvas is only used by the jsQR fallback -->
        <canvas id="jsqr-canvas" style="display:none;"></canvas>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const scannerModal = document.getElementById('qr-scanner-modal');
        const closeScannerBtn = document.getElementById('qr-close-btn');
        const videoEl = document.getElementById('qr-video-element');
        const feedbackEl = document.getElementById('qr-feedback');

        let activeScanner = null; // Will hold the active scanner object or stream
        let errorTimeout = null;
        let currentLocationId = 0; // This variable will hold the location context

        // --- Device Detection ---
        const isAndroid = /android/i.test(navigator.userAgent);

        // --- Dynamic Script Loader ---
        function loadScript(src, id, callback) {
            if (document.getElementById(id)) {
                if (callback) callback();
                return;
            }
            const script = document.createElement('script');
            script.src = src;
            script.id = id;
            script.onload = () => { if (callback) callback(); };
            document.head.appendChild(script);
        }

        // Generic listener to open the scanner
        document.body.addEventListener('click', function(event) {
            if (event.target && event.target.classList.contains('js-start-qr-scan')) {
                // Get the location context from the button that was clicked
                currentLocationId = event.target.dataset.locationId || 0;
                startScanner();
            }
        });
        
        closeScannerBtn.addEventListener('click', closeScannerUI);

        function startScanner() {
            clearTimeout(errorTimeout);
            feedbackEl.style.color = 'white';
            feedbackEl.textContent = 'Point camera at a QR code';
            scannerModal.style.display = 'block';

            if (isAndroid) {
                loadScript('https://cdn.jsdelivr.net/npm/qr-scanner@1.4.2/qr-scanner.umd.min.js', 'nimiq-scanner-lib', startNimiqScanner);
            } else {
                loadScript('https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js', 'jsqr-lib', startJsQRScanner);
            }
        }

        function stopCameraStream() {
            clearTimeout(errorTimeout);
            if (activeScanner) {
                if (activeScanner.destroy) { // For Nimiq scanner
                    activeScanner.stop();
                    activeScanner.destroy();
                } else if (activeScanner.getTracks) { // For jsQR stream
                    activeScanner.getTracks().forEach(track => track.stop());
                }
                activeScanner = null;
            }
        }

        function closeScannerUI() {
            stopCameraStream(); // Always stop the camera when closing the UI.
            scannerModal.style.display = 'none';
        }

        function handleQrCode(id) {
            // Stop the camera stream immediately while we verify.
            stopCameraStream();
            
            feedbackEl.textContent = 'Verifying...';

            const formData = new FormData();
            formData.append('action', 'verify_qr_code');
            formData.append('nonce', '<?php echo wp_create_nonce("audit_save_nonce"); ?>');
            formData.append('scanned_id', id);
            formData.append('location_id', currentLocationId);
            
            fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(result => {
                if (!result.success) {
                    throw new Error(result.data.message || 'Unknown server error.');
                }

                // Handle the different verdicts from the server
                switch (result.data.status) {
                    case 'perfect_match':
                        // Fire the event for the other page to handle and close the scanner
                        const event = new CustomEvent('qrCodeScanned', { detail: { id: id } });
                        document.dispatchEvent(event);
                        closeScannerUI(); // Only close on perfect match
                        break;
                    
                    case 'location_mismatch':
                        feedbackEl.textContent = result.data.message; // e.g., "This item belongs in Library."
                        feedbackEl.style.color = '#ffc107'; // Yellow for warning
                        errorTimeout = setTimeout(resumeScanning, 2000); // Resume scanning after 4 seconds
                        break;

                    case 'unknown_item':
                    default:
                        feedbackEl.textContent = result.data.message || `[${id}] is not recognized as part of TAC assets.`;
                        feedbackEl.style.color = '#f44336'; // Red for error
                        errorTimeout = setTimeout(resumeScanning, 2000);
                        break;
                }
            })
            .catch(error => {
                feedbackEl.textContent = 'Network error. Please try again.';
                feedbackEl.style.color = '#f44336';
                errorTimeout = setTimeout(startScanner, 3000);
            });
        }

        function resumeScanning() {
            feedbackEl.textContent = 'Point camera at a QR code';
            feedbackEl.style.color = 'white';
            startScanner(); // Restart the scanner cleanly
        }
        
        function startNimiqScanner() {
            QrScanner.WORKER_PATH = 'https://cdn.jsdelivr.net/npm/qr-scanner@1.4.2/qr-scanner-worker.min.js';
            const qrScanner = new QrScanner( videoEl, result => handleQrCode(result.data), { highlightScanRegion: true, highlightCodeOutline: true });
            activeScanner = qrScanner;
            qrScanner.start().catch(err => {
                alert("Could not start camera. Please ensure you have given permission.");
                closeScannerUI();
            });
        }
        
        function startJsQRScanner() {
            const canvas = document.getElementById('jsqr-canvas');
            const context = canvas.getContext('2d');
            let animationFrameId;

            navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment" } })
                .then(function(stream) {
                    videoEl.srcObject = stream;
                    videoEl.play();
                    activeScanner = stream;
                    animationFrameId = requestAnimationFrame(tick);
                })
                .catch(function(err) {
                    alert("Could not start camera. Please ensure you have given permission.");
                    closeScannerUI();
                });
            
            function tick() {
                if (videoEl.readyState === videoEl.HAVE_ENOUGH_DATA) {
                    canvas.height = videoEl.videoHeight;
                    canvas.width = videoEl.videoWidth;
                    context.drawImage(videoEl, 0, 0, canvas.width, canvas.height);
                    const imageData = context.getImageData(0, 0, canvas.width, canvas.height);
                    const code = jsQR(imageData.data, imageData.width, imageData.height);
                    if (code) {
                        cancelAnimationFrame(animationFrameId);
                        handleQrCode(code.data);
                        return;
                    }
                }
                if (activeScanner) {
                    animationFrameId = requestAnimationFrame(tick);
                }
            }
        }
    });
    </script>
    <?php
}
