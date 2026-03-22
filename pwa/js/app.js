import { db } from './db.js';
import { ASSET_MODELS } from './models.js';
import { COMPANIES } from './companies.js';

// DOM Elements
const loginSection = document.getElementById('login-section');
const loginForm = document.getElementById('login-form');
const loginUsernameInput = document.getElementById('login-username');
const loginPasswordInput = document.getElementById('login-password');
const loginStatusMsg = document.getElementById('login-status-msg');

const companySection = document.getElementById('company-section');
const inventoryContent = document.getElementById('inventory-content');
const companySelect = document.getElementById('company-select');
const companyConfirmBtn = document.getElementById('company-confirm-btn');

const form = document.getElementById('asset-form');
const serialInput = document.getElementById('serial_number');
const modelSelect = document.getElementById('asset_model_id');
const modelCacheHint = document.getElementById('model-cache-hint');
const locationInput = document.getElementById('location');
const commentInput = document.getElementById('comment');
const statusMsg = document.getElementById('status-msg');
const inventoryTableBody = document.querySelector('#inventory-table tbody');
const syncNowBtn = document.getElementById('sync-now-btn');
const scanBtn = document.getElementById('scan-btn');
const scannerModal = document.getElementById('scanner-modal');
const closeScannerBtn = document.getElementById('close-scanner');
const scannerStatus = document.getElementById('scanner-status');

// State
let lastSelectedModelId = '';
let currentCompany = null;
let editingAssetId = null; // Track if we're in edit mode
let html5QrCode = null; // Scanner instance
let inventoryUiInitialized = false;
let serialLookupDebounceTimer = null;
let assetModelsCatalog = [...ASSET_MODELS];
let lastDeletedAsset = null;
let statusTimeoutId = null;
let undoDeleteTimeoutId = null;

const MODEL_CACHE_KEY = 'inventory_pwa_asset_models_cache_v1';

// Device Detection
const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);

function setLoginStatus(msg, type) {
    if (!loginStatusMsg) {
        return;
    }
    loginStatusMsg.textContent = msg;
    loginStatusMsg.className = `status-${type}`;
}

function formatHintTimestamp(isoString) {
    if (!isoString) {
        return null;
    }

    const date = new Date(isoString);
    if (Number.isNaN(date.getTime())) {
        return null;
    }

    return date.toLocaleString('de-DE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function setModelCacheHint(message) {
    if (!modelCacheHint) {
        return;
    }
    modelCacheHint.textContent = message;
}

function showLoginSection() {
    if (loginSection) {
        loginSection.style.display = 'block';
    }
    companySection.style.display = 'none';
    inventoryContent.style.display = 'none';
}

function showCompanySection() {
    if (loginSection) {
        loginSection.style.display = 'none';
    }
    companySection.style.display = 'block';
    inventoryContent.style.display = 'none';
}

async function fetchBootstrapMeta() {
    try {
        const response = await fetch(apiUrl('bootstrap/meta.php'), {
            method: 'GET',
            credentials: 'include',
            cache: 'no-store'
        });

        if (response.ok) {
            return { authenticated: true };
        }

        if (response.status === 401 || response.status === 403) {
            return { authenticated: false, reason: 'auth_required' };
        }

        return { authenticated: false, reason: `http_${response.status}` };
    } catch (err) {
        return { authenticated: false, reason: 'unreachable' };
    }
}

async function loginWithCredentials(username, password) {
    const response = await fetch(apiUrl('auth/login.php'), {
        method: 'POST',
        credentials: 'include',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ username, password })
    });

    let data = null;
    try {
        data = await response.json();
    } catch (err) {
        data = null;
    }

    return { response, data };
}

function readCachedModelCatalog() {
    try {
        const raw = localStorage.getItem(MODEL_CACHE_KEY);
        if (!raw) {
            return null;
        }

        const parsed = JSON.parse(raw);
        if (!parsed || !Array.isArray(parsed.items)) {
            return null;
        }

        const items = parsed.items
            .filter((item) => item && item.id != null && item.name)
            .map((item) => ({ id: Number(item.id), name: String(item.name) }))
            .filter((item) => Number.isFinite(item.id) && item.name.trim() !== '')
            .sort((a, b) => a.name.localeCompare(b.name));

        if (items.length === 0) {
            return null;
        }

        return {
            items,
            cachedAt: parsed.cached_at || null
        };
    } catch (err) {
        console.warn('[Model Cache] Failed to read cache', err);
        return null;
    }
}

function writeCachedModelCatalog(items) {
    try {
        localStorage.setItem(MODEL_CACHE_KEY, JSON.stringify({
            cached_at: new Date().toISOString(),
            items
        }));
    } catch (err) {
        console.warn('[Model Cache] Failed to write cache', err);
    }
}

async function refreshModelCatalogFromApi() {
    try {
        const response = await fetch(apiUrl('asset-models.php'), {
            method: 'GET',
            credentials: 'include',
            cache: 'no-store'
        });

        if (!response.ok) {
            return false;
        }

        const data = await response.json();
        if (!data || !data.success || !Array.isArray(data.items)) {
            return false;
        }

        const items = data.items
            .filter((item) => item && item.id != null && item.name)
            .map((item) => ({ id: Number(item.id), name: String(item.name) }))
            .filter((item) => Number.isFinite(item.id) && item.name.trim() !== '')
            .sort((a, b) => a.name.localeCompare(b.name));

        if (items.length === 0) {
            return false;
        }

        assetModelsCatalog = items;
        writeCachedModelCatalog(items);
        console.log(`[Model Cache] Updated from API (${items.length} models)`);
        return true;
    } catch (err) {
        console.warn('[Model Cache] API refresh failed, keeping cached models', err);
        return false;
    }
}

async function initializeInventoryUiOnce() {
    if (inventoryUiInitialized) {
        return;
    }

    populateCompanySelect();

    const cachedModels = readCachedModelCatalog();
    if (cachedModels) {
        assetModelsCatalog = cachedModels.items;
        const cacheTime = formatHintTimestamp(cachedModels.cachedAt);
        if (cacheTime) {
            setModelCacheHint(`Modellliste aus Offline-Cache geladen (Stand: ${cacheTime})`);
        } else {
            setModelCacheHint('Modellliste aus Offline-Cache geladen');
        }
        console.log(`[Model Cache] Loaded ${cachedModels.items.length} cached models`);
    } else {
        setModelCacheHint('Modellliste aus App-Standard geladen');
    }

    populateModelSelect();

    // Refresh model catalog from server when possible, keep local cache for offline mode.
    const refreshed = await refreshModelCatalogFromApi();
    if (refreshed) {
        populateModelSelect();
        setModelCacheHint('Modellliste online aktualisiert');
    }

    if (isMobile && scanBtn) {
        scanBtn.style.display = 'flex';
        initScanner();
    }

    if (syncNowBtn) {
        syncNowBtn.addEventListener('click', handleSyncNowClick);
    }

    inventoryUiInitialized = true;
}

function apiUrl(relativePath) {
    return new URL(`../public/api/mobile/v1/${relativePath}`, window.location.href).toString();
}

async function lookupAssetBySerialOrTag(code) {
    const normalized = (code || '').trim();
    if (!normalized) {
        return null;
    }

    try {
        const lookupUrl = apiUrl(`assets/lookup.php?code=${encodeURIComponent(normalized)}`);
        console.log(`[Asset Lookup] Querying: ${lookupUrl}`);
        
        const response = await fetch(lookupUrl, {
            method: 'GET',
            credentials: 'include',
            cache: 'no-store'
        });

        console.log(`[Asset Lookup] Response status: ${response.status}`);

        const rawText = await response.text();
        let result = null;

        try {
            result = rawText ? JSON.parse(rawText) : null;
        } catch (parseErr) {
            const snippet = rawText ? rawText.slice(0, 300) : '';
            console.error(`[Asset Lookup] Non-JSON response (${response.status}): ${snippet}`);
            return { state: 'unavailable', model: null, error: 'invalid_api_response' };
        }

        if (!response.ok) {
            console.error(`[Asset Lookup] HTTP Error ${response.status}:`, result || rawText);
            
            if (response.status === 401 || response.status === 403) {
                console.error('[Asset Lookup] Authentication failed - please check login');
            }
            
            return { state: 'unavailable', model: null, error: `HTTP ${response.status}` };
        }

        console.log(`[Asset Lookup] Result:`, result);
        
        if (!result.success) {
            console.warn(`[Asset Lookup] API returned success=false:`, result);
            return { state: 'unavailable', model: null, error: result.message };
        }

        if (!result.found) {
            console.log(`[Asset Lookup] Asset not found in database`);
            return { state: 'not_found', model: null };
        }

        if (!result.model || !result.model.id) {
            console.warn(`[Asset Lookup] Model data incomplete:`, result.model);
            return { state: 'unavailable', model: null, error: 'Model data incomplete' };
        }

        console.log(`[Asset Lookup] Asset found: ${result.model.name}`);
        return { state: 'found', model: result.model };
    } catch (err) {
        console.error(`[Asset Lookup] Exception:`, err);
        return { state: 'unavailable', model: null, error: err.message };
    }
}

async function autoSelectModelForCode(code, options = {}) {
    const { silent = false } = options;
    const lookup = await lookupAssetBySerialOrTag(code);

    if (!lookup || !lookup.state) {
        return false;
    }

    if (lookup.state === 'not_found') {
        modelSelect.value = '';
        modelSelect.dispatchEvent(new Event('change'));
        if (!silent) {
            showStatus('Kein Asset zu Seriennummer/Asset-Tag gefunden.', 'error');
        }
        return false;
    }

    if (lookup.state !== 'found') {
        modelSelect.value = '';
        modelSelect.dispatchEvent(new Event('change'));
        if (!silent) {
            const errorDetail = lookup.error ? ` (${lookup.error})` : '';
            showStatus(`Asset-Lookup nicht möglich${errorDetail}. Seriennummer manuell eingeben.`, 'error');
        }
        return false;
    }

    const model = lookup.model;
    const normalizeModelName = (value) => (value || '')
        .toString()
        .trim()
        .toLowerCase()
        .replace(/\s+/g, ' ')
        .replace(/[()\[\].,\-_/]+/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();

    const apiNameRaw = model.name || '';
    const apiName = normalizeModelName(apiNameRaw);
    const modelId = String(model.id);
    const modelOptions = Array.from(modelSelect.options).filter((opt) => opt.value !== '');

    const optionByExactName = apiName
        ? modelOptions.find((opt) => normalizeModelName(opt.textContent) === apiName)
        : null;

    const optionByIncludesName = apiName
        ? modelOptions.find((opt) => {
            const localName = normalizeModelName(opt.textContent);
            return localName.includes(apiName) || apiName.includes(localName);
        })
        : null;

    const optionById = modelOptions.find((opt) => opt.value === modelId);

    // Name match has priority to avoid wrong model selections when IDs drift.
    const chosenOption = optionByExactName || optionByIncludesName || optionById || null;

    if (!chosenOption) {
        modelSelect.value = '';
        modelSelect.dispatchEvent(new Event('change'));
        if (!silent) {
            showStatus('Gefundenes Modell ist nicht im Dropdown vorhanden.', 'error');
        }
        return false;
    }

    // Hard safety check: if API returned a name and chosen option is clearly different,
    // do not select anything rather than selecting a wrong model.
    if (apiName) {
        const chosenName = normalizeModelName(chosenOption.textContent);
        const namesCompatible = chosenName === apiName || chosenName.includes(apiName) || apiName.includes(chosenName);
        if (!namesCompatible) {
            console.warn('[Asset Lookup] Rejected mismatching model selection', {
                apiModelId: modelId,
                apiModelName: apiNameRaw,
                chosenValue: chosenOption.value,
                chosenText: chosenOption.textContent
            });
            modelSelect.value = '';
            modelSelect.dispatchEvent(new Event('change'));
            if (!silent) {
                showStatus('Modell erkannt, aber lokale Modellliste passt nicht. Bitte manuell auswählen.', 'error');
            }
            return false;
        }
    }

    console.log('[Asset Lookup] Applying model selection', {
        apiModelId: modelId,
        apiModelName: apiNameRaw,
        selectedValue: chosenOption.value,
        selectedText: chosenOption.textContent.trim()
    });

    modelSelect.value = chosenOption.value;
    modelSelect.dispatchEvent(new Event('change'));
    if (!silent) {
        showStatus(`Modell automatisch gesetzt: ${chosenOption.textContent.trim()}`, 'success');
    }
    return true;
}

function isUnsyncedAsset(asset) {
    return asset.status !== 'transmitted';
}

async function checkStartupSyncAndCleanup() {
    const allAssets = await db.getAllAssets();

    if (allAssets.length === 0) {
        return;
    }

    const unsyncedAssets = allAssets.filter(isUnsyncedAsset);

    if (unsyncedAssets.length === 0) {
        await db.clearAllAssets();
        showStatus('Alle Daten sind synchronisiert. Lokale Datenbank wurde geleert.', 'success');
        return;
    }

    showStatus(`${unsyncedAssets.length} nicht synchronisierte Datensätze vorhanden.`, 'error');
}

async function probeOnlineConnection() {
    if (!navigator.onLine) {
        return { ok: false, reason: 'offline' };
    }

    try {
        const response = await fetch(apiUrl('bootstrap/meta.php'), {
            method: 'GET',
            credentials: 'include',
            cache: 'no-store'
        });

        if (response.ok) {
            return { ok: true };
        }

        // 401/403 means server is reachable, but session is missing/insufficient.
        if (response.status === 401 || response.status === 403) {
            return { ok: true, authRequired: true };
        }

        return { ok: false, reason: `http_${response.status}` };
    } catch (err) {
        return { ok: false, reason: 'network_error', details: err instanceof Error ? err.message : String(err) };
    }
}

function buildSyncPayload(asset) {
    return {
        client_id: `${asset.company_id || 0}-${asset.id}-${asset.serial_number}`,
        serial_number: asset.serial_number,
        asset_model_id: asset.asset_model_id ?? null,
        location: asset.location ?? null,
        comment: asset.comment ?? null,
        company_id: asset.company_id ?? null,
        company_name: asset.company_name ?? null,
        timestamp: asset.timestamp ?? null,
        status: asset.status ?? 'completed'
    };
}

async function syncUnsyncedAssets() {
    const allAssets = await db.getAllAssets();
    const unsyncedAssets = allAssets.filter(isUnsyncedAsset);

    if (unsyncedAssets.length === 0) {
        showStatus('Keine offenen Datensätze. Alles ist synchronisiert.', 'success');
        return;
    }

    const payload = {
        items: unsyncedAssets.map(buildSyncPayload)
    };

    const response = await fetch(apiUrl('sync/inventory.php'), {
        method: 'POST',
        credentials: 'include',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(payload)
    });

    if (!response.ok) {
        if (response.status === 401 || response.status === 403) {
            showStatus('Sync nicht möglich: Bitte zuerst im System anmelden.', 'error');
            return;
        }
        throw new Error(`Sync fehlgeschlagen (${response.status})`);
    }

    const result = await response.json();
    if (!result.success) {
        throw new Error(result.error || 'Sync fehlgeschlagen');
    }

    const ids = unsyncedAssets.map(a => a.id);
    await db.updateAssetsStatus(ids, 'transmitted');
    await refreshTable();
    showStatus(`${ids.length} Datensätze erfolgreich synchronisiert.`, 'success');
}

async function handleSyncNowClick() {
    if (!syncNowBtn) {
        return;
    }

    syncNowBtn.disabled = true;
    const originalText = syncNowBtn.textContent;
    syncNowBtn.textContent = 'Prüfe Verbindung...';

    try {
        const probe = await probeOnlineConnection();
        if (!probe.ok) {
            if (probe.reason === 'offline') {
                showStatus('Keine Onlineverbindung verfügbar. Bitte Verbindung prüfen.', 'error');
            } else if (probe.reason && probe.reason.startsWith('http_')) {
                showStatus(`Server erreichbar, aber API antwortet mit ${probe.reason.replace('http_', '')}. Bitte URL/Setup prüfen.`, 'error');
            } else {
                showStatus('Server/API nicht erreichbar. Bitte XAMPP-URL und Pfad prüfen.', 'error');
            }
            return;
        }

        syncNowBtn.textContent = 'Synchronisiere...';
        await syncUnsyncedAssets();
    } catch (err) {
        showStatus(`Sync-Fehler: ${err.message}`, 'error');
    } finally {
        syncNowBtn.disabled = false;
        syncNowBtn.textContent = originalText;
    }
}

// Initialize
async function init() {
    try {
        await db.open();
        await checkStartupSyncAndCleanup();

        const session = await fetchBootstrapMeta();
        if (session.authenticated) {
            await initializeInventoryUiOnce();
            showCompanySection();
            return;
        }

        showLoginSection();
        if (session.reason === 'unreachable') {
            setLoginStatus('Server/API nicht erreichbar. Bitte XAMPP und URL prüfen.', 'error');
        } else if (session.reason && session.reason.startsWith('http_')) {
            setLoginStatus(`Serverantwort: ${session.reason.replace('http_', '')}.`, 'error');
        }
    } catch (err) {
        showStatus(`Database Error: ${err.message}`, 'error');
    }
}

// Login Form Handler
loginForm.addEventListener('submit', async (e) => {
    e.preventDefault();

    const username = loginUsernameInput.value.trim();
    const password = loginPasswordInput.value.trim();

    if (!username || !password) {
        setLoginStatus('Username und Passwort erforderlich.', 'error');
        return;
    }

    const loginBtn = loginForm.querySelector('button[type="submit"]');
    const originalText = loginBtn.textContent;
    loginBtn.disabled = true;
    loginBtn.textContent = 'Anmelden...';

    try {
        const { response, data } = await loginWithCredentials(username, password);

        if (response.ok && data && data.success) {
            // Login successful - initialize UI and show company selection
            setLoginStatus('Erfolgreich angemeldet.', 'success');
            
            // Clear form inputs
            loginUsernameInput.value = '';
            loginPasswordInput.value = '';

            // Initialize inventory UI and show company section
            setTimeout(async () => {
                await initializeInventoryUiOnce();
                showCompanySection();
            }, 500);
        } else {
            // Login failed
            const errorMsg = data?.message || 'Anmeldung fehlgeschlagen. Bitte überprüfen Sie Ihre Anmeldedaten.';
            setLoginStatus(errorMsg, 'error');
        }
    } catch (err) {
        setLoginStatus(`Fehler: ${err.message}`, 'error');
    } finally {
        loginBtn.disabled = false;
        loginBtn.textContent = originalText;
    }
});

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
    // Keep placeholder option and re-render list from the active catalog.
    modelSelect.innerHTML = '<option value="">-- Modell wählen --</option>';

    assetModelsCatalog.forEach(model => {
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
                status: 'completed'
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
            autoSelectModelForCode(serialInput.value, { silent: true }).finally(() => {
                modelSelect.focus();
            });
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

// Convert serial input to uppercase on every input
serialInput.addEventListener('input', (e) => {
    e.target.value = e.target.value.toUpperCase();

    const code = e.target.value.trim();

    // Prevent stale manual selection from being shown while a new serial is being resolved.
    if (code.length >= 4) {
        modelSelect.value = '';
        modelSelect.dispatchEvent(new Event('change'));
    }

    if (serialLookupDebounceTimer) {
        clearTimeout(serialLookupDebounceTimer);
    }

    // Trigger lookup while typing so users get immediate model auto-detection.
    if (code.length >= 4) {
        serialLookupDebounceTimer = setTimeout(() => {
            autoSelectModelForCode(code, { silent: true });
        }, 250);
    }
});

serialInput.addEventListener('blur', () => {
    autoSelectModelForCode(serialInput.value, { silent: true });
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
        row.dataset.assetId = String(asset.id);

        const modelName = assetModelsCatalog.find(m => m.id === asset.asset_model_id)?.name || 'Unknown';

        let statusKey = asset.status || 'in_progress';
        let statusLabel = 'In Bearbeitung';
        let statusIcon = '⏳';
        let statusIconClass = 'status-icon-default';

        if (statusKey === 'completed') {
            statusLabel = 'Erfasst';
            statusIcon = '✓';
            statusIconClass = 'status-icon-success';
        }

        if (statusKey === 'transmitted') {
            statusLabel = 'Übertragen';
            statusIcon = '⇪';
            statusIconClass = 'status-icon-info';
        }

        row.innerHTML = `
            <td>${asset.serial_number}</td>
            <td>${modelName}</td>
            <td>${asset.location || ''}</td>
            <td>
                <span class="status-icon ${statusIconClass}" title="${statusLabel}" aria-label="${statusLabel}">${statusIcon}</span>
            </td>
            <td>
                <button class="action-btn delete-btn" data-id="${asset.id}">Delete</button>
                <button class="action-btn edit-btn" data-id="${asset.id}">Edit</button>
            </td>
        `;
        inventoryTableBody.appendChild(row);

        if (isMobile) {
            attachMobileSwipeActions(row, asset.id);
        }
    });

    // Attach event listeners to new buttons
    document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.addEventListener('click', handleDelete);
    });
    document.querySelectorAll('.edit-btn').forEach(btn => {
        btn.addEventListener('click', handleEdit);
    });
}

function attachMobileSwipeActions(row, assetId) {
    let touchStartX = 0;
    let touchStartY = 0;

    row.addEventListener('touchstart', (e) => {
        const touch = e.changedTouches[0];
        touchStartX = touch.clientX;
        touchStartY = touch.clientY;
    }, { passive: true });

    row.addEventListener('touchend', async (e) => {
        const touch = e.changedTouches[0];
        const deltaX = touch.clientX - touchStartX;
        const deltaY = touch.clientY - touchStartY;

        // Require a clear horizontal gesture to avoid accidental triggers while scrolling.
        if (Math.abs(deltaX) < 90 || Math.abs(deltaX) < Math.abs(deltaY) * 1.5) {
            return;
        }

        if (deltaX > 0) {
            await editAssetById(assetId);
            showStatus('Wisch rechts erkannt: Bearbeiten geöffnet.', 'success');
            return;
        }

        await deleteAssetById(assetId, { skipConfirm: true, fromSwipe: true });
    }, { passive: true });
}

// Actions
async function handleDelete(e) {
    const id = parseInt(e.target.dataset.id);
    await deleteAssetById(id, { skipConfirm: false, fromSwipe: false });
}

async function handleEdit(e) {
    const id = parseInt(e.target.dataset.id);
    await editAssetById(id);
}

async function editAssetById(id) {
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

async function deleteAssetById(id, options = {}) {
    const { skipConfirm = false, fromSwipe = false } = options;

    if (!skipConfirm && !confirm('Scan wirklich löschen?')) {
        return false;
    }

    const assets = await db.getAllAssets();
    const asset = assets.find((a) => a.id === id);
    if (!asset) {
        return false;
    }

    lastDeletedAsset = { ...asset };
    await db.deleteAsset(id);
    await refreshTable();
    showUndoDeletePrompt(asset.serial_number, fromSwipe);
    return true;
}

async function restoreLastDeletedAsset() {
    if (!lastDeletedAsset) {
        return;
    }

    const assetToRestore = { ...lastDeletedAsset };
    lastDeletedAsset = null;

    if (undoDeleteTimeoutId) {
        clearTimeout(undoDeleteTimeoutId);
        undoDeleteTimeoutId = null;
    }

    await db.updateAsset(assetToRestore);
    await refreshTable();
    showStatus(`Löschen rückgängig: ${assetToRestore.serial_number}`, 'success');
}

function showUndoDeletePrompt(serial, fromSwipe = false) {
    if (!statusMsg) {
        return;
    }

    if (statusTimeoutId) {
        clearTimeout(statusTimeoutId);
        statusTimeoutId = null;
    }
    if (undoDeleteTimeoutId) {
        clearTimeout(undoDeleteTimeoutId);
        undoDeleteTimeoutId = null;
    }

    const swipeHint = fromSwipe ? ' (per Wischgeste)' : '';
    statusMsg.className = 'status-info';
    statusMsg.innerHTML = `Scan ${serial} gelöscht${swipeHint}. <button type="button" id="undo-delete-btn" class="status-inline-btn">Rückgängig</button>`;

    const undoBtn = document.getElementById('undo-delete-btn');
    if (undoBtn) {
        undoBtn.addEventListener('click', () => {
            restoreLastDeletedAsset();
        }, { once: true });
    }

    undoDeleteTimeoutId = setTimeout(() => {
        lastDeletedAsset = null;
        statusMsg.textContent = '';
        statusMsg.className = '';
        undoDeleteTimeoutId = null;
    }, 7000);
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


// Helpers
function showStatus(msg, type) {
    if (statusTimeoutId) {
        clearTimeout(statusTimeoutId);
        statusTimeoutId = null;
    }
    if (undoDeleteTimeoutId) {
        clearTimeout(undoDeleteTimeoutId);
        undoDeleteTimeoutId = null;
    }

    statusMsg.textContent = msg;
    statusMsg.className = `status-${type}`;
    statusTimeoutId = setTimeout(() => {
        statusMsg.textContent = '';
        statusMsg.className = '';
        statusTimeoutId = null;
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
    setTimeout(async () => {
        closeScanner();
        await autoSelectModelForCode(decodedText, { silent: true });
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
