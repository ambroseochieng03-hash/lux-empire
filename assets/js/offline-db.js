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

            request.onsuccess = () => {

                const db = request.result;

                /*
                 * The browser can close this connection at any time —
                 * tab backgrounding, back/forward-cache eviction,
                 * memory pressure. Without this, dbPromise keeps
                 * resolving to a dead connection forever, and every
                 * future .transaction() call throws InvalidStateError
                 * synchronously (which shows up as an unhandled
                 * rejection, since it happens inside a Promise
                 * executor with no surrounding try/catch). Dropping
                 * the cache here means the next call just reopens.
                 */
                db.onclose = () => {
                    dbPromise = null;
                };

                // Another tab wants to upgrade the schema — close
                // cleanly so it can, and reset so this tab reopens
                // fresh the next time it needs the DB.
                db.onversionchange = () => {
                    db.close();
                    dbPromise = null;
                };

                resolve(db);
            };

            request.onerror = () => reject(request.error);
        });

        return dbPromise;
    }

    /*
     * Runs callback inside a transaction on storeName. If the cached
     * connection turns out to be dead (InvalidStateError, thrown
     * synchronously by db.transaction()), drops the cache and
     * retries exactly once with a fresh connection rather than
     * failing outright.
     */
    function withStore(storeName, mode, callback, isRetry) {

        return openDB().then((db) => new Promise((resolve, reject) => {

            let tx;

            try {
                tx = db.transaction(storeName, mode);
            } catch (err) {

                if (err && err.name === 'InvalidStateError' && !isRetry) {
                    dbPromise = null;
                    withStore(storeName, mode, callback, true).then(resolve, reject);
                    return;
                }

                reject(err);
                return;
            }

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

        return withStore('houses', 'readonly', (store) => {

            return new Promise((resolve, reject) => {
                const request = store.getAll();
                request.onsuccess = () => resolve(request.result || []);
                request.onerror = () => reject(request.error);
            });

        }).then((maybePromise) => maybePromise);
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

        return withStore('drafts', 'readonly', (store) => {

            return new Promise((resolve, reject) => {
                const index = store.index('status');
                const request = index.getAll('pending');
                request.onsuccess = () => resolve(request.result || []);
                request.onerror = () => reject(request.error);
            });

        }).then((maybePromise) => maybePromise);
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