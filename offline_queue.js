/*
  GoldFruits Offline Queue (IndexedDB)
  - Guarda operaciones cuando no hay internet
  - Sincroniza automáticamente cuando vuelve la conexión

  Nota: Esto NO reemplaza al backend (PHP/MySQL). Es un “offline-first” para registro.
*/

(() => {
  const DB_NAME = 'goldfruits_offline';
  const DB_VERSION = 1;
  const STORE = 'queue';

  function openDB() {
    return new Promise((resolve, reject) => {
      const req = indexedDB.open(DB_NAME, DB_VERSION);
      req.onupgradeneeded = (e) => {
        const db = e.target.result;
        if (!db.objectStoreNames.contains(STORE)) {
          const store = db.createObjectStore(STORE, { keyPath: 'local_id' });
          store.createIndex('created_at', 'created_at');
        }
      };
      req.onsuccess = () => resolve(req.result);
      req.onerror = () => reject(req.error);
    });
  }

  async function withStore(mode, fn) {
    const db = await openDB();
    return new Promise((resolve, reject) => {
      const tx = db.transaction(STORE, mode);
      const store = tx.objectStore(STORE);
      let out;
      tx.oncomplete = () => resolve(out);
      tx.onerror = () => reject(tx.error);
      out = fn(store);
    });
  }

  function uid() {
    return (crypto?.randomUUID?.() || ('local-' + Date.now() + '-' + Math.random().toString(16).slice(2)));
  }

  async function addToQueue(operation) {
    const item = {
      local_id: uid(),
      created_at: new Date().toISOString(),
      endpoint: operation.endpoint,
      fields: operation.fields,
      photos: operation.photos || [],
    };
    await withStore('readwrite', (store) => store.put(item));
    return item.local_id;
  }

  async function listQueue() {
    return await withStore('readonly', (store) => {
      return new Promise((resolve, reject) => {
        const req = store.getAll();
        req.onsuccess = () => resolve(req.result || []);
        req.onerror = () => reject(req.error);
      });
    });
  }

  async function removeFromQueue(local_id) {
    await withStore('readwrite', (store) => store.delete(local_id));
  }

  function buildFormDataFromItem(item) {
    const fd = new FormData();
    for (const [k, v] of Object.entries(item.fields || {})) {
      // Evitar undefined/null
      if (v === undefined || v === null) continue;
      fd.append(k, String(v));
    }

    // Fotos (blob)
    (item.photos || []).forEach((p) => {
      const blob = p.blob instanceof Blob ? p.blob : null;
      if (blob) {
        fd.append('fotos_pesadas[]', blob, p.name || 'foto.jpg');
      }
    });

    return fd;
  }

  async function syncQueue(options = {}) {
    const endpointDefault = options.endpoint || 'guardar_goldfruits.php';
    const onStatus = typeof options.onStatus === 'function' ? options.onStatus : null;

    if (!navigator.onLine) return { synced: 0, failed: 0, total: 0 };

    const items = await listQueue();
    let synced = 0;
    let failed = 0;

    for (const item of items) {
      const endpoint = item.endpoint || endpointDefault;
      try {
        if (onStatus) onStatus({ stage: 'sending', item });
        const fd = buildFormDataFromItem(item);
        const res = await fetch(endpoint, { method: 'POST', body: fd });
        if (!res.ok) throw new Error('HTTP ' + res.status);

        // Si el backend responde error en texto, igual lo tratamos como error
        const txt = await res.text();
        if (/^error\s*:/i.test(txt.trim())) throw new Error(txt.trim());

        await removeFromQueue(item.local_id);
        synced++;
        if (onStatus) onStatus({ stage: 'done', item, serverText: txt });
      } catch (e) {
        failed++;
        if (onStatus) onStatus({ stage: 'failed', item, error: String(e) });
        // Si falla una, seguimos con las demás
      }
    }

    return { synced, failed, total: items.length };
  }

  async function queueAcopioFromCurrentForm(formEl, listP) {
    // fields base
    const fd = new FormData(formEl);
    const fields = {};
    for (const [k, v] of fd.entries()) {
      // No hay inputs tipo file en el form (se agregan manualmente)
      fields[k] = v;
    }

    // Guardar detalle_pesadas_json igual que online
    fields['detalle_pesadas_json'] = JSON.stringify(
      (listP || []).map((x) => ({ jabas: x.j, peso: x.p, origen: x.origen, categoria: x.cat }))
    );

    const photos = (listP || []).map((x) => ({
      name: x.f?.name || 'foto.jpg',
      type: x.f?.type || 'image/jpeg',
      blob: x.f instanceof Blob ? x.f : null,
    })).filter(p => p.blob);

    const local_id = await addToQueue({ endpoint: 'guardar_goldfruits.php', fields, photos });
    return { local_id };
  }

  // Exponer API simple
  window.GF_OFFLINE = {
    addToQueue,
    listQueue,
    removeFromQueue,
    syncQueue,
    queueAcopioFromCurrentForm,
  };
})();
