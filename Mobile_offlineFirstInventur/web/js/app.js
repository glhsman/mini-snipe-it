import { db } from './db.js';
import { ASSET_MODELS } from './models.js';
import { COMPANIES } from './companies.js';

// DOM Elements
const companySection = document.getElementById('company-section');
const inventoryContent = document.getElementById('inventory-content');
const companySelect = document.getElementById('company-select');
const companyConfirmBtn = document.getElementById('company-confirm-btn');

const form = document.getElementById('asset-form');
const serialInput = document.getElementById('serial_number');
const modelSelect = document.getElementById('asset_model_id');
const locationInput = document.getElementById('location');
const commentInput = document.getElementById('comment');
const statusMsg = document.getElementById('status-msg');
const inventoryTableBody = document.querySelector('#inventory-table tbody');
const exportJsonBtn = document.getElementById('export-json');
const exportCsvBtn = document.getElementById('export-csv');
const scanBtn = document.getElementById('scan-btn');
const scannerModal = document.getElementById('scanner-modal');
const closeScannerBtn = document.getElementById('close-scanner');
const scannerStatus = document.getElementById('scanner-status');

// State
let lastSelectedModelId = '';
let currentCompany = null;
let editingAssetId = null; // Track if we're in edit mode
let html5QrCode = null; // Scanner instance

// Device Detection
const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);

// Initialize
async function init() {
    try {
        await db.open();

        // Ensure Company Selection is shown first
        companySection.style.display = 'block';
        inventoryContent.style.display = 'none';

        populateCompanySelect();
        populateModelSelect();

        // Show scan button on mobile devices
        if (isMobile && scanBtn) {
            scanBtn.style.display = 'flex';
            initScanner();
        }

        // Check if company was previously filtered? For now, always require selection on reload/init
        // specific requirement: "Beim Start soll eine Gesellschaft... geladen werden"
    } catch (err) {
        showStatus(`Database Error: ${err.message}`, 'error');
    }
}

// Company Selection Logic
function populateCompanySelect() {
    COMPANIES.forEach(comp => {
        const option = document.createElement('option');
        option.value = comp.id;
        option.textContent = comp.name;
        companySelect.appendChild(option);
    });

    companySelect.addEventListener('change', () => {
        companyConfirmBtn.disabled = !companySelect.value;
    });

    companyConfirmBtn.addEventListener('click', () => {
        const compId = parseInt(companySelect.value);
        currentCompany = COMPANIES.find(c => c.id === compId);

        if (currentCompany) {
            companySection.style.display = 'none';
            inventoryContent.style.display = 'block';
            document.querySelector('header h1').textContent = `Inventur: ${currentCompany.name}`;

            // Refresh table to show assets for this company (if implementing filter)
            // Or just show all? Requirement doesn't explicitly say "filter by company", 
            // but implies context. Let's filter or at least tag new assets with company.
            refreshTable();

            // Focus on serial input
            serialInput.focus();
        }
    });
}

// Populate Asset Models
function populateModelSelect() {
    ASSET_MODELS.forEach(model => {
        const option = document.createElement('option');
        option.value = model.id;
        option.textContent = model.name;
        modelSelect.appendChild(option);
    });
}

// Handle Form Submit
form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const serial = serialInput.value.trim();
    const modelId = modelSelect.value;
    const location = locationInput.value.trim();
    const comment = commentInput.value.trim();

    if (!serial || !modelId) {
        showStatus('Serial Number and Model are required.', 'error');
        return;
    }

    try {
        if (editingAssetId) {
            // UPDATE MODE
            const existingAsset = (await db.getAllAssets()).find(a => a.id === editingAssetId);
            const updatedAsset = {
                ...existingAsset,
                serial_number: serial,
                asset_model_id: parseInt(modelId),
                location: location,
                comment: comment,
                status: 'completed' // Reset to completed so it can be re-exported
            };
            await db.updateAsset(updatedAsset);
            showStatus(`Asset ${serial} updated.`, 'success');
            cancelEdit();
        } else {
            // ADD MODE
            const asset = {
                serial_number: serial,
                asset_model_id: parseInt(modelId),
                location: location,
                comment: comment,
                company_id: currentCompany.id,
                company_name: currentCompany.name,
                status: 'completed'
            };
            await db.addAsset(asset);
            showStatus(`Asset ${serial} saved.`, 'success');

            // Clear form but keep model
            serialInput.value = '';
            locationInput.value = '';
            commentInput.value = '';
            lastSelectedModelId = modelId;

            // Refocus serial for next scan
            serialInput.focus();
        }

        refreshTable();
    } catch (err) {
        if (err.message.includes('Duplicate')) {
            showStatus('Error: Duplicate Serial Number!', 'error');
        } else {
            showStatus(`Error saving: ${err.message}`, 'error');
        }
    }
});

// Prevent Tab/Enter accidental submits on barcodes? 
// Provided requirement: "Enter" and "Tab" keys must not trigger unintended actions
// Scanner usually sends "Enter" after scan. We WANT that to likely submit or move focus.
// Standard behavior of form submit on Enter is usually desired for single-input scanning workflows unless it triggers prematurely.
// Here we have multiple fields. If scanning into Serial, we might want to move to Model or just Submit if Model is pre-selected.
// Requirement says: "Save the asset explicitly via a button". This implies Enter should NOT auto-submit?
// "Speichern Sie das Asset ausdrücklich über eine Schaltfläche." -> Explicit save button.
// So I should PREVENT implicit submission on Enter in inputs.

// Handle Enter Key for Scanner/Keyboard Navigation
form.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
        e.preventDefault(); // Always prevent form submit on Enter in standard inputs

        const target = e.target;

        if (target === serialInput) {
            // After serial scan/entry, verify if we have a model.
            // If model is already selected (sticky), maybe skip to save? 
            // For now, let's just move to model select to be safe, or location.
            // Requirement usually: Scan Serial -> Check properties -> Scan/Enter next
            // Let's move to Model selection.
            modelSelect.focus();
            // Optional: Open dropdown? Not easy with standard select.
        } else if (target === modelSelect) {
            locationInput.focus();
        } else if (target === locationInput) {
            commentInput.focus();
        } else if (target === commentInput) {
            // On comment enter, maybe save? Or focus save button?
            // "Save the asset explicitly via a button"
            // Let's focus the submit button so another Enter triggers it (standard behavior)
            // or just submit() if we are confident.
            // Let's focus the button.
            document.querySelector('#asset-form button[type="submit"]').focus();
        }
    }
});


// Load and render table
async function refreshTable() {
    let assets = await db.getAllAssets();

    // Filter by company if selected
    if (currentCompany) {
        assets = assets.filter(a => a.company_id === currentCompany.id);
    }

    // Sort by newest first
    assets.sort((a, b) => new Date(b.timestamp) - new Date(a.timestamp));

    inventoryTableBody.innerHTML = '';

    assets.forEach(asset => {
        const row = document.createElement('tr');

        const modelName = ASSET_MODELS.find(m => m.id === asset.asset_model_id)?.name || 'Unknown';

        let statusLabel = asset.status || 'in_progress';
        let statusClass = 'status-default';
        if (statusLabel === 'completed') { statusLabel = 'Erfasst'; statusClass = 'status-success'; }
        if (statusLabel === 'transmitted') { statusLabel = 'Übertragen'; statusClass = 'status-info'; }

        row.innerHTML = `
            <td>${asset.serial_number}</td>
            <td>${modelName}</td>
            <td>${asset.location || ''}</td>
            <td><span class="badge ${statusClass}">${statusLabel}</span></td>
            <td>
                <button class="action-btn delete-btn" data-id="${asset.id}">Delete</button>
                <button class="action-btn edit-btn" data-id="${asset.id}">Edit</button>
            </td>
        `;
        inventoryTableBody.appendChild(row);
    });

    // Attach event listeners to new buttons
    document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.addEventListener('click', handleDelete);
    });
    document.querySelectorAll('.edit-btn').forEach(btn => {
        btn.addEventListener('click', handleEdit);
    });
}

// Actions
async function handleDelete(e) {
    if (!confirm('Are you sure you want to delete this asset?')) return;
    const id = parseInt(e.target.dataset.id);
    await db.deleteAsset(id);
    refreshTable();
}

async function handleEdit(e) {
    const id = parseInt(e.target.dataset.id);
    const assets = await db.getAllAssets();
    const asset = assets.find(a => a.id === id);

    if (!asset) return;

    // Populate form
    serialInput.value = asset.serial_number;
    modelSelect.value = asset.asset_model_id;
    locationInput.value = asset.location || '';
    commentInput.value = asset.comment || '';

    // Set edit mode
    editingAssetId = id;

    // Update button text
    const submitBtn = form.querySelector('button[type="submit"]');
    submitBtn.textContent = 'Aktualisieren';
    submitBtn.classList.add('editing');

    // Show cancel button if not exists
    if (!document.getElementById('cancel-edit-btn')) {
        const cancelBtn = document.createElement('button');
        cancelBtn.id = 'cancel-edit-btn';
        cancelBtn.type = 'button';
        cancelBtn.className = 'secondary';
        cancelBtn.textContent = 'Abbrechen';
        cancelBtn.addEventListener('click', cancelEdit);
        submitBtn.parentNode.insertBefore(cancelBtn, submitBtn.nextSibling);
    }

    // Scroll to form and focus
    serialInput.focus();
    form.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function cancelEdit() {
    editingAssetId = null;

    // Clear form
    serialInput.value = '';
    locationInput.value = '';
    commentInput.value = '';

    // Reset button
    const submitBtn = form.querySelector('button[type="submit"]');
    submitBtn.textContent = 'Speichern';
    submitBtn.classList.remove('editing');

    // Remove cancel button
    const cancelBtn = document.getElementById('cancel-edit-btn');
    if (cancelBtn) cancelBtn.remove();

    serialInput.focus();
}


// Export
exportJsonBtn.addEventListener('click', async () => {
    let assets = await db.getAllAssets();
    // Filter by company
    if (currentCompany) {
        assets = assets.filter(a => a.company_id === currentCompany.id);
    }

    // Filter for export: Only completed or in_progress, NOT transmitted
    const assetsToExport = assets.filter(a => a.status !== 'transmitted');

    if (assetsToExport.length === 0) {
        showStatus('No new data to export (all transmitted).', 'error');
        return;
    }

    const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(assetsToExport, null, 2));
    const downloadAnchorNode = document.createElement('a');
    downloadAnchorNode.setAttribute("href", dataStr);
    downloadAnchorNode.setAttribute("download", `inventory_${currentCompany ? currentCompany.name : 'all'}_${new Date().toISOString().slice(0, 10)}.json`);
    document.body.appendChild(downloadAnchorNode);
    downloadAnchorNode.click();
    downloadAnchorNode.remove();

    // Mark as transmitted
    const ids = assetsToExport.map(a => a.id);
    await db.updateAssetsStatus(ids, 'transmitted');
    refreshTable();
    showStatus(`Exported ${ids.length} assets and marked as transmitted.`, 'success');
});

exportCsvBtn.addEventListener('click', async () => {
    let assets = await db.getAllAssets();
    // Filter by company
    if (currentCompany) {
        assets = assets.filter(a => a.company_id === currentCompany.id);
    }

    // Filter for export: Only completed or in_progress, NOT transmitted
    const assetsToExport = assets.filter(a => a.status !== 'transmitted');

    if (assetsToExport.length === 0) {
        showStatus('No new data to export (all transmitted).', 'error');
        return;
    }

    const headers = ['id', 'serial_number', 'asset_model_id', 'location', 'comment', 'timestamp', 'status', 'company_name'];
    const csvContent = [
        headers.join(','),
        ...assetsToExport.map(row => headers.map(fieldName => JSON.stringify(row[fieldName] || '')).join(','))
    ].join('\n');

    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.setAttribute("href", url);
    link.setAttribute("download", `inventory_${currentCompany ? currentCompany.name : 'all'}_${new Date().toISOString().slice(0, 10)}.csv`);
    document.body.appendChild(link);
    link.click();

    // Mark as transmitted
    const ids = assetsToExport.map(a => a.id);
    await db.updateAssetsStatus(ids, 'transmitted');
    refreshTable();
    showStatus(`Exported ${ids.length} assets and marked as transmitted.`, 'success');
});


// Helpers
function showStatus(msg, type) {
    statusMsg.textContent = msg;
    statusMsg.className = `status-${type}`;
    setTimeout(() => {
        statusMsg.textContent = '';
        statusMsg.className = '';
    }, 3000);
}

// Scanner initialization and event handlers
function initScanner() {
    if (!scanBtn || !scannerModal) return;

    scanBtn.addEventListener('click', openScanner);
    closeScannerBtn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        closeScanner();
    });

    // Close on background click (iOS fallback)
    scannerModal.addEventListener('click', (e) => {
        if (e.target === scannerModal) {
            closeScanner();
        }
    });
}

function openScanner() {
    scannerModal.style.display = 'flex';
    scannerStatus.textContent = 'Kamera wird gestartet...';
    scannerStatus.className = 'scanner-status';

    // Check if we're on HTTPS or localhost
    const isSecureContext = window.isSecureContext || location.hostname === 'localhost' || location.hostname === '127.0.0.1';

    if (!isSecureContext) {
        scannerStatus.textContent = 'Fehler: HTTPS erforderlich für Kamera-Zugriff auf iOS. Bitte verwenden Sie HTTPS oder laden Sie ein Barcode-Bild hoch.';
        scannerStatus.className = 'scanner-status error';
        addFileInputFallback();
        return;
    }

    // Initialize scanner
    html5QrCode = new Html5Qrcode("scanner-container");

    const config = {
        fps: 10,
        qrbox: { width: 250, height: 250 },
        aspectRatio: 1.0
    };

    // Try to start camera
    html5QrCode.start(
        { facingMode: "environment" }, // Rear camera
        config,
        onScanSuccess,
        onScanError
    ).catch(err => {
        console.error('Scanner start error:', err);

        // Better error messages for iOS
        let errorMsg = 'Fehler beim Starten der Kamera. ';

        if (err.name === 'NotAllowedError') {
            errorMsg += 'Bitte erlauben Sie den Kamera-Zugriff in den Browser-Einstellungen.';
        } else if (err.name === 'NotFoundError') {
            errorMsg += 'Keine Kamera gefunden.';
        } else if (err.name === 'NotReadableError') {
            errorMsg += 'Kamera wird bereits verwendet.';
        } else {
            errorMsg += err.message || 'Unbekannter Fehler.';
        }

        scannerStatus.textContent = errorMsg;
        scannerStatus.className = 'scanner-status error';

        // Offer file input as fallback
        addFileInputFallback();
    });
}

function addFileInputFallback() {
    // Check if fallback already exists
    if (document.getElementById('file-input-fallback')) return;

    const fallbackDiv = document.createElement('div');
    fallbackDiv.id = 'file-input-fallback';
    fallbackDiv.style.padding = '1rem';
    fallbackDiv.style.textAlign = 'center';

    const label = document.createElement('label');
    label.style.display = 'block';
    label.style.marginBottom = '0.5rem';
    label.textContent = 'Alternativ: Barcode-Bild hochladen';

    const fileInput = document.createElement('input');
    fileInput.type = 'file';
    fileInput.accept = 'image/*';
    fileInput.capture = 'environment'; // Hint for mobile to use camera
    fileInput.style.display = 'block';
    fileInput.style.margin = '0 auto';

    fileInput.addEventListener('change', (e) => {
        const file = e.target.files[0];
        if (!file) return;

        scannerStatus.textContent = 'Bild wird analysiert...';
        scannerStatus.className = 'scanner-status';

        if (!html5QrCode) {
            html5QrCode = new Html5Qrcode("scanner-container");
        }

        html5QrCode.scanFile(file, true)
            .then(decodedText => {
                onScanSuccess(decodedText, null);
            })
            .catch(err => {
                scannerStatus.textContent = 'Kein Barcode im Bild gefunden.';
                scannerStatus.className = 'scanner-status error';
            });
    });

    fallbackDiv.appendChild(label);
    fallbackDiv.appendChild(fileInput);

    const scannerContainer = document.getElementById('scanner-container');
    scannerContainer.parentNode.insertBefore(fallbackDiv, scannerContainer.nextSibling);
}

function closeScanner() {
    console.log('closeScanner called');

    // Force stop camera immediately
    if (html5QrCode) {
        try {
            html5QrCode.stop()
                .then(() => {
                    console.log('Scanner stopped successfully');
                    html5QrCode.clear();
                    html5QrCode = null;
                })
                .catch(err => {
                    console.error('Scanner stop error:', err);
                    // Force clear anyway
                    try {
                        html5QrCode.clear();
                    } catch (e) {
                        console.error('Clear error:', e);
                    }
                    html5QrCode = null;
                });
        } catch (err) {
            console.error('Exception stopping scanner:', err);
            html5QrCode = null;
        }
    }

    // Remove fallback if exists
    const fallback = document.getElementById('file-input-fallback');
    if (fallback) fallback.remove();

    // Force hide modal immediately (don't wait for camera to stop)
    scannerModal.style.display = 'none';
    scannerStatus.textContent = '';

    // Clear scanner container
    const container = document.getElementById('scanner-container');
    if (container) {
        container.innerHTML = '';
    }
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

// Start
init();
