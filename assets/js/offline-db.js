/*
=========================================
LUX EMPIRE — OFFLINE STORAGE (IndexedDB)
=========================================
Two stores:
  - houses: cached house records, upserted whenever a real fetch
    succeeds, so offline search/browse has real data to query.
  - drafts: queued form submissions (truck requests, and later
    bookings/listings) waiting to sync once back online.
Exposed as window.LuxOfflineDB — no build step, no imports, since
this project has no bundler.
=========================================
*/

(function () {

    const DB_NAME = 'lux_empire_offline';
    const DB_VERSION = 1;

    let dbPromise = null;

    function openDB() {

        if (dbPromise) {
            return dbPromise;
        }

        dbPromise = new Promise((resolve, reject) => {

            const request = indexedDB.open(DB_NAME, DB_VERSION);

            request.onupgradeneeded = (event) => {
                const db = event.target.result;

                if (!db.objectStoreNames.contains('houses')) {
                    db.createObjectStore('houses', { keyPath: 'id' });
                }

                if (!db.objectStoreNames.contains('drafts')) {
                    const draftStore = db.createObjectStore('drafts', { keyPath: 'id' });
                    draftStore.createIndex('status', 'status', { unique: false });
                }
            };

            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });

        return dbPromise;
    }

    function withStore(storeName, mode, callback) {

        return openDB().then((db) => new Promise((resolve, reject) => {

            const tx = db.transaction(storeName, mode);
            const store = tx.objectStore(storeName);

            const result = callback(store);

            tx.oncomplete = () => resolve(result);
            tx.onerror = () => reject(tx.error);
        }));
    }

    /* ===================== HOUSES ===================== */

    function upsertHouses(houses) {

        if (!Array.isArray(houses) || houses.length === 0) {
            return Promise.resolve();
        }

        return withStore('houses', 'readwrite', (store) => {
            houses.forEach((house) => store.put(house));
        });
    }

    function getAllHouses() {

        return openDB().then((db) => new Promise((resolve, reject) => {

            const tx = db.transaction('houses', 'readonly');
            const store = tx.objectStore('houses');
            const request = store.getAll();

            request.onsuccess = () => resolve(request.result || []);
            request.onerror = () => reject(request.error);
        }));
    }

    /*
     * Plain client-side filter over whatever's cached — deliberately
     * simple (no Haversine, no relaxed-match fallback) since this
     * only runs when there's no network to ask the real endpoint.
     */
    function searchCachedHouses(filters) {

        return getAllHouses().then((houses) => {

            const keyword = (filters.keyword || '').toLowerCase();

            return houses.filter((house) => {

                if (keyword) {
                    const haystack = (
                        (house.title || '') + ' ' +
                        (house.location || '') + ' ' +
                        (house.description || '')
                    ).toLowerCase();

                    if (haystack.indexOf(keyword) === -1) {
                        return false;
                    }
                }

                if (filters.min_price && Number(house.price) < Number(filters.min_price)) {
                    return false;
                }

                if (filters.max_price && Number(house.price) > Number(filters.max_price)) {
                    return false;
                }

                if (filters.house_type && house.house_type !== filters.house_type) {
                    return false;
                }

                if (filters.location && !(house.location || '').toLowerCase().includes(filters.location.toLowerCase())) {
                    return false;
                }

                if (filters.bedrooms && Number(house.bedrooms) < Number(filters.bedrooms)) {
                    return false;
                }

                if (filters.bathrooms && Number(house.bathrooms) < Number(filters.bathrooms)) {
                    return false;
                }

                return true;
            });
        });
    }

    /* ===================== DRAFTS ===================== */

    function saveDraft(draft) {

        const record = {
            id: draft.id || ('draft_' + Date.now() + '_' + Math.random().toString(36).slice(2)),
            type: draft.type,
            endpoint: draft.endpoint,
            payload: draft.payload,
            csrfToken: draft.csrfToken || null,
            createdAt: Date.now(),
            status: 'pending'
        };

        return withStore('drafts', 'readwrite', (store) => {
            store.put(record);
        }).then(() => record);
    }

    function getPendingDrafts() {

        return openDB().then((db) => new Promise((resolve, reject) => {

            const tx = db.transaction('drafts', 'readonly');
            const store = tx.objectStore('drafts');
            const index = store.index('status');
            const request = index.getAll('pending');

            request.onsuccess = () => resolve(request.result || []);
            request.onerror = () => reject(request.error);
        }));
    }

    function deleteDraft(id) {

        return withStore('drafts', 'readwrite', (store) => {
            store.delete(id);
        });
    }

    window.LuxOfflineDB = {
        upsertHouses,
        getAllHouses,
        searchCachedHouses,
        saveDraft,
        getPendingDrafts,
        deleteDraft
    };

})();
