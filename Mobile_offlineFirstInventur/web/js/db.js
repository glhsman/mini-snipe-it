const DB_NAME = 'inventory_db';
const DB_VERSION = 1;
const STORE_ASSETS = 'assets';

export class InventoryDB {
    constructor() {
        this.db = null;
    }

    async open() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(DB_NAME, DB_VERSION);

            request.onerror = (event) => {
                console.error('Database error:', event.target.error);
                reject(event.target.error);
            };

            request.onsuccess = (event) => {
                this.db = event.target.result;
                resolve(this.db);
            };

            request.onupgradeneeded = (event) => {
                const db = event.target.result;

                // Assets Store
                // Key: id (autoIncrement)
                // Indexes: serial_number (unique)
                if (!db.objectStoreNames.contains(STORE_ASSETS)) {
                    const objectStore = db.createObjectStore(STORE_ASSETS, { keyPath: 'id', autoIncrement: true });
                    objectStore.createIndex('serial_number', 'serial_number', { unique: true });
                    objectStore.createIndex('status', 'status', { unique: false });
                }
            };
        });
    }

    async addAsset(asset) {
        if (!this.db) await this.open();

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([STORE_ASSETS], 'readwrite');
            const store = transaction.objectStore(STORE_ASSETS);

            // Check for duplicate serial number manually before adding (double safety)
            const serialIndex = store.index('serial_number');
            const checkRequest = serialIndex.get(asset.serial_number);

            checkRequest.onsuccess = () => {
                if (checkRequest.result) {
                    reject(new Error(`Duplicate Serial Number: ${asset.serial_number}`));
                    return;
                }

                // Proceed to add
                const addRequest = store.add({
                    ...asset,
                    timestamp: new Date().toISOString(),
                    status: asset.status || 'in_progress'
                });

                addRequest.onsuccess = () => resolve(addRequest.result);
                addRequest.onerror = (e) => reject(e.target.error);
            };

            checkRequest.onerror = (e) => reject(e.target.error);
        });
    }

    async getAllAssets() {
        if (!this.db) await this.open();

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([STORE_ASSETS], 'readonly');
            const store = transaction.objectStore(STORE_ASSETS);
            const request = store.getAll();

            request.onsuccess = () => resolve(request.result);
            request.onerror = (e) => reject(e.target.error);
        });
    }

    async updateAsset(asset) {
        if (!this.db) await this.open();

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([STORE_ASSETS], 'readwrite');
            const store = transaction.objectStore(STORE_ASSETS);
            const request = store.put(asset); // put updates if key exists

            request.onsuccess = () => resolve(request.result);
            request.onerror = (e) => reject(e.target.error);
        });
    }

    async deleteAsset(id) {
        if (!this.db) await this.open();

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([STORE_ASSETS], 'readwrite');
            const store = transaction.objectStore(STORE_ASSETS);
            const request = store.delete(id);

            request.onsuccess = () => resolve(request.result);
            request.onerror = (e) => reject(e.target.error);
        });
    }

    async updateAssetsStatus(ids, newStatus) {
        if (!this.db) await this.open();

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([STORE_ASSETS], 'readwrite');
            const store = transaction.objectStore(STORE_ASSETS);

            ids.forEach(id => {
                const getRequest = store.get(id);
                getRequest.onsuccess = () => {
                    const asset = getRequest.result;
                    if (asset) {
                        asset.status = newStatus;
                        store.put(asset);
                    }
                };
            });

            transaction.oncomplete = () => resolve();
            transaction.onerror = (e) => reject(e.target.error);
        });
    }
}

export const db = new InventoryDB();
