// Scanner initialization and event handlers
function initScanner() {
    if (!scanBtn || !scannerModal) return;

    scanBtn.addEventListener('click', openScanner);
    closeScannerBtn.addEventListener('click', closeScanner);
}

function openScanner() {
    scannerModal.style.display = 'flex';
    scannerStatus.textContent = 'Kamera wird gestartet...';
    scannerStatus.className = 'scanner-status';

    // Initialize scanner
    html5QrCode = new Html5Qrcode("scanner-container");

    const config = {
        fps: 10,
        qrbox: { width: 250, height: 250 },
        aspectRatio: 1.0
    };

    html5QrCode.start(
        { facingMode: "environment" }, // Rear camera
        config,
        onScanSuccess,
        onScanError
    ).catch(err => {
        scannerStatus.textContent = `Fehler: ${err}`;
        scannerStatus.className = 'scanner-status error';
        console.error('Scanner start error:', err);
    });
}

function closeScanner() {
    if (html5QrCode) {
        html5QrCode.stop().then(() => {
            html5QrCode.clear();
            html5QrCode = null;
        }).catch(err => {
            console.error('Scanner stop error:', err);
        });
    }
    scannerModal.style.display = 'none';
    scannerStatus.textContent = '';
}

function onScanSuccess(decodedText, decodedResult) {
    // Populate serial number field
    serialInput.value = decodedText;

    scannerStatus.textContent = `Erfolgreich: ${decodedText}`;
    scannerStatus.className = 'scanner-status success';

    // Close scanner after short delay
    setTimeout(() => {
        closeScanner();
        // Focus next field (model select)
        modelSelect.focus();
    }, 500);
}

function onScanError(errorMessage) {
    // Ignore scan errors (happens continuously while scanning)
    // Only log critical errors
    if (errorMessage.includes('NotFoundException')) {
        // Normal - no barcode in frame
        return;
    }
    console.warn('Scan error:', errorMessage);
}

// Export scanner functions to be accessible from app.js
export { initScanner, openScanner, closeScanner };
