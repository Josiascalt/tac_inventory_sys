<?php
/**
 * REUSABLE QR CODE SCANNER SNIPPET
 * --------------------------------
 * PURPOSE: Adds a self-contained, reusable QR code scanner to your site.
 * - To use, simply add the class "js-start-qr-scan" to any button.
 * - When a code is scanned, it fires a global 'qrCodeScanned' event that other scripts can listen for.
 * TYPE: PHP
 */

add_action('wp_footer', 'render_reusable_qr_scanner_modal');

function render_reusable_qr_scanner_modal() {
    ?>
    <!-- QR Code Scanner Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
    
    <style>
        #qr-scanner-modal { display: none; position: fixed; z-index: 1050; left: 0; top: 0; width: 100%; height: 100%; background-color: #000; transition: background-color 0.3s ease; }
        #qr-scanner-content { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 90%; max-width: 500px; }
        #qr-close-btn { 
            position: absolute; 
            top: 15px; 
            right: 20px; 
            font-size: 2.5em; 
            color: white; 
            cursor: pointer; 
            text-shadow: 0 0 5px rgba(0,0,0,0.5); 
            z-index: 10; /* Add this line */
        }
        #qr-close-btn { position: absolute; top: 15px; right: 20px; font-size: 2.5em; color: white; cursor: pointer; text-shadow: 0 0 5px rgba(0,0,0,0.5); }
        #qr-feedback { text-align: center; color: white; font-size: 1.2em; margin-top: 15px; }
        #qr-scanner-modal.scanner-success { background-color: #4caf50; }
        #qr-success-overlay { display: none; position: absolute; top: 0; left: 0; right: 0; bottom: 0; justify-content: center; align-items: center; font-size: 8em; color: white; font-weight: bold; }
    </style>

    <!-- QR Code Scanner Modal HTML -->
    <div id="qr-scanner-modal">
        <div id="qr-scanner-content">
            <span id="qr-close-btn">&times;</span>
            <video id="qr-video" playsinline></video>
            <div id="qr-feedback">Point camera at a QR code</div>
        </div>
        <div id="qr-success-overlay">OK!</div>
        <canvas id="qr-canvas" style="display:none;"></canvas>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // --- Get all DOM elements first ---
            const scannerModal = document.getElementById('qr-scanner-modal');
            const closeScannerBtn = document.getElementById('qr-close-btn');
            const video = document.getElementById('qr-video');
            const feedbackEl = document.getElementById('qr-feedback');
            const successOverlay = document.getElementById('qr-success-overlay');

            // --- Declare shared variables in the top-level scope ---
            let stream = null;
            let scanAnimation = null;
            let errorTimeout = null;

            // --- Attach Event Listeners ---
            document.body.addEventListener('click', function(event) {
                if (event.target && event.target.classList.contains('js-start-qr-scan')) {
                    startScanner();
                }
            });
            
            closeScannerBtn.addEventListener('click', stopScanner);

            // --- Define Functions ---
            function startScanner() {
                // Reset UI from any previous scan
                clearTimeout(errorTimeout);
                scannerModal.classList.remove('scanner-success');
                successOverlay.style.display = 'none';
                video.style.display = 'block';
                feedbackEl.style.color = 'white';
                feedbackEl.textContent = 'Point camera at a QR code';
                feedbackEl.style.display = 'block';

                navigator.mediaDevices.getUserMedia({ 
                    video: { 
                        facingMode: "environment",
                        width: { ideal: 1920 },
                        height: { ideal: 1080 }
                    } 
                })
                .then(function(s) {
                    stream = s;
                    video.srcObject = stream;
                    video.play();
                    scannerModal.style.display = 'block';
                    scanAnimation = requestAnimationFrame(tick);
                })
                .catch(function(err) {
                    alert("Could not access camera. Please ensure you have given permission.");
                });
            }

            function stopScanner() {
                // This is a simplified function for testing.
                // It's only job is to hide the modal.

                // Get the modal element directly inside the function to be safe.
                const modal = document.getElementById('qr-scanner-modal');
                if (modal) {
                    modal.style.display = 'none';
                }

                // We are temporarily disabling the camera-stopping logic.
                // The camera light on your device might stay on after closing, which is expected for this test.
                if (stream) {
                    stream.getTracks().forEach(track => track.stop());
                }
                cancelAnimationFrame(scanAnimation);
                clearTimeout(errorTimeout);
            }

            function tick() {
                if (video.readyState === video.HAVE_ENOUGH_DATA) {
                    const canvas = document.getElementById('qr-canvas').getContext('2d');
                    canvas.canvas.height = video.videoHeight;
                    canvas.canvas.width = video.videoWidth;
                    canvas.drawImage(video, 0, 0, canvas.canvas.width, canvas.canvas.height);
                    const imageData = canvas.getImageData(0, 0, canvas.canvas.width, canvas.canvas.height);
                    const code = jsQR(imageData.data, imageData.width, imageData.height);

                    if (code) {
                        // Stop scanning while we process the code
                        cancelAnimationFrame(scanAnimation);
                        handleQrCode(code.data);
                        return;
                    }
                }
                scanAnimation = requestAnimationFrame(tick);
            }

            function handleQrCode(id) {
                // Ask the page if this ID is valid by firing a cancellable event
                const event = new CustomEvent('qrCodeScanned', { 
                    detail: { id: id },
                    cancelable: true
                });
                const wasHandledSuccessfully = document.dispatchEvent(event);

                if (wasHandledSuccessfully) {
                    // If the page handled it, show the green "OK!" screen
                    video.style.display = 'none';
                    feedbackEl.style.display = 'none';
                    successOverlay.style.display = 'flex';
                    scannerModal.classList.add('scanner-success');
                    setTimeout(stopScanner, 1000); // Close automatically
                } else {
                    // If the page vetoed it (item not found), show an error message
                    feedbackEl.textContent = `Error: Item ${id} not found in this location.`;
                    feedbackEl.style.color = '#f44336';

                    // After a delay, reset the message and resume scanning
                    errorTimeout = setTimeout(() => {
                        feedbackEl.textContent = 'Point camera at a QR code';
                        feedbackEl.style.color = 'white';
                        scanAnimation = requestAnimationFrame(tick); // Resume scanning
                    }, 3000);
                }
            }
        });
    </script>
    <?php
}
