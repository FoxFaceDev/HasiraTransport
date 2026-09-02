const DATABASE_NAME = 'hasira-gatekeeper';
const DATABASE_VERSION = 1;

function requestResult(request) {
    return new Promise((resolve, reject) => {
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

function openDatabase() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DATABASE_NAME, DATABASE_VERSION);

        request.onupgradeneeded = () => {
            const database = request.result;

            if (!database.objectStoreNames.contains('tankers')) {
                database.createObjectStore('tankers', { keyPath: 'id' });
            }

            if (!database.objectStoreNames.contains('outbox')) {
                const outbox = database.createObjectStore('outbox', { keyPath: 'operation_uuid' });
                outbox.createIndex('created_at', 'client_created_at');
            }

            if (!database.objectStoreNames.contains('meta')) {
                database.createObjectStore('meta', { keyPath: 'key' });
            }
        };

        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
        request.onblocked = () => reject(new Error('The offline database is blocked by another tab.'));
    });
}

function transactionDone(transaction) {
    return new Promise((resolve, reject) => {
        transaction.oncomplete = () => resolve();
        transaction.onerror = () => reject(transaction.error);
        transaction.onabort = () => reject(transaction.error || new Error('Offline database transaction aborted.'));
    });
}

class GatekeeperStore {
    async init() {
        this.database = await openDatabase();
    }

    async readState() {
        const transaction = this.database.transaction(['tankers', 'outbox', 'meta'], 'readonly');
        const done = transactionDone(transaction);
        const tankersRequest = transaction.objectStore('tankers').getAll();
        const outboxRequest = transaction.objectStore('outbox').getAll();
        const syncedAtRequest = transaction.objectStore('meta').get('last_synced_at');
        const [tankers, outbox, syncedAt] = await Promise.all([
            requestResult(tankersRequest),
            requestResult(outboxRequest),
            requestResult(syncedAtRequest),
        ]);

        await done;

        return {
            tankers,
            outbox: outbox.sort((a, b) => a.client_created_at.localeCompare(b.client_created_at)),
            lastSyncedAt: syncedAt?.value || null,
        };
    }

    async replaceSnapshot(snapshot) {
        const transaction = this.database.transaction(['tankers', 'meta'], 'readwrite');
        const tankerStore = transaction.objectStore('tankers');
        tankerStore.clear();

        for (const tanker of snapshot.tankers || []) {
            tankerStore.put(tanker);
        }

        transaction.objectStore('meta').put({
            key: 'last_synced_at',
            value: snapshot.synced_at || new Date().toISOString(),
        });

        await transactionDone(transaction);
    }

    async enqueue(operation) {
        const transaction = this.database.transaction('outbox', 'readwrite');
        transaction.objectStore('outbox').put(operation);
        await transactionDone(transaction);
    }

    async pendingOperations() {
        const transaction = this.database.transaction('outbox', 'readonly');
        const done = transactionDone(transaction);
        const operations = await requestResult(transaction.objectStore('outbox').getAll());
        await done;
        return operations.sort((a, b) => a.client_created_at.localeCompare(b.client_created_at));
    }

    async removeOperations(operationUuids) {
        if (!operationUuids.length) return;

        const transaction = this.database.transaction('outbox', 'readwrite');
        const store = transaction.objectStore('outbox');
        operationUuids.forEach(operationUuid => store.delete(operationUuid));
        await transactionDone(transaction);
    }
}

function newUuid() {
    if (crypto.randomUUID) return crypto.randomUUID();

    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, character => {
        const random = Math.random() * 16 | 0;
        const value = character === 'x' ? random : (random & 0x3 | 0x8);
        return value.toString(16);
    });
}

function normalizedQueue(queue = {}) {
    return {
        status: queue.status || 'pending',
        scheduled_date: queue.scheduled_date || null,
        scheduled_time: queue.scheduled_time || null,
        note: queue.note || '',
        updated_at: queue.updated_at || null,
    };
}

function snapshotTimestamp(snapshot) {
    const value = Date.parse(snapshot?.synced_at || '');
    return Number.isNaN(value) ? 0 : value;
}

function localDateString() {
    const now = new Date();
    const offset = now.getTimezoneOffset() * 60000;

    return new Date(now.getTime() - offset).toISOString().split('T')[0];
}

export function gatekeeperQueueManager(initialSnapshot = {}, options = {}) {
    return {
        store: new GatekeeperStore(),
        tankers: (initialSnapshot.tankers || []).map(tanker => ({ ...tanker, queue: normalizedQueue(tanker.queue) })),
        search: '',
        statusFilter: options.statusFilter || null,
        scheduleOnly: options.scheduleOnly || false,
        selectedDate: options.dateFilter || localDateString(),
        online: navigator.onLine,
        syncing: false,
        ready: false,
        pendingCount: 0,
        lastSyncedAt: initialSnapshot.synced_at || null,
        syncError: '',
        authRequired: false,
        isResetting: false,
        showScheduleModal: false,
        schedulingTankerId: null,
        schedulingStatus: null,
        scheduleDate: localDateString(),
        scheduleTime: '5:30 بەیانی',
        syncTimer: null,

        async init() {
            window.addEventListener('online', () => {
                this.online = true;
                this.syncNow();
            });
            window.addEventListener('offline', () => this.online = false);

            try {
                await this.store.init();
                await this.migrateLegacyChanges();

                let localState = await this.store.readState();
                const initialIsNewer = snapshotTimestamp(initialSnapshot) > Date.parse(localState.lastSyncedAt || '');

                if (!localState.tankers.length || initialIsNewer) {
                    await this.store.replaceSnapshot(initialSnapshot);
                    localState = await this.store.readState();
                }

                this.tankers = localState.tankers.map(tanker => ({
                    ...tanker,
                    queue: normalizedQueue(tanker.queue),
                }));
                this.lastSyncedAt = localState.lastSyncedAt || this.lastSyncedAt;
                this.applyPendingOperations(localState.outbox);
                this.pendingCount = localState.outbox.length;
                this.ready = true;

                if (navigator.onLine) await this.syncNow();
                this.syncTimer = window.setInterval(() => {
                    if (navigator.onLine && document.visibilityState === 'visible') this.syncNow();
                }, 30000);
            } catch (error) {
                console.error('Offline database initialization failed:', error);
                this.syncError = 'هەڵگرتنی ئۆفلاین بەردەست نییە لەم وێبگەڕەدا.';
                this.ready = true;
            }
        },

        get visibleTankers() {
            const query = this.search.trim().toLowerCase();

            return this.tankers.filter(tanker => {
                if (this.statusFilter && this.getStatus(tanker) !== this.statusFilter) return false;
                if (this.scheduleOnly) {
                    const status = this.getStatus(tanker);
                    if (!['green', 'yellow'].includes(status)) return false;
                    if (tanker.queue?.scheduled_date !== this.selectedDate) return false;
                }
                if (!query) return true;

                return [
                    tanker.plate_number,
                    tanker.sequence_number,
                    tanker.sequence_owner,
                    tanker.sequence_owner_phone,
                    tanker.vin,
                    tanker.truck_color,
                    this.getBlockReason(tanker),
                ].some(value => String(value || '').toLowerCase().includes(query));
            });
        },

        isBlocked(tanker) {
            return Boolean(tanker?.blocked_at);
        },

        getBlockReason(tanker) {
            if (tanker?.blocked_at) return 'خەت بلۆککراوە';
            return '';
        },

        getStatus(tanker) {
            return tanker?.queue?.status || 'pending';
        },

        getStatusLabel(tanker) {
            if (this.getStatus(tanker) === 'green') return 'هاتن';
            if (this.getStatus(tanker) === 'yellow') return 'دواخستن';
            return '';
        },

        changeScheduleDate(date) {
            this.selectedDate = date || localDateString();

            const url = new URL(window.location.href);
            url.searchParams.set('date', this.selectedDate);
            window.history.replaceState({}, '', url);
        },

        getRowClass(tanker) {
            if (this.isBlocked(tanker)) return 'blocked-row';
            const status = this.getStatus(tanker);
            if (status === 'green') return 'queue-row-green';
            if (status === 'red') return 'queue-row-red';
            if (status === 'yellow') return 'queue-row-yellow';
            return '';
        },

        getScheduleRowClass(tanker) {
            return this.getStatus(tanker) === 'green' ? 'queue-row-green' : 'queue-row-yellow';
        },

        openScheduleModal(tankerId, status) {
            this.schedulingTankerId = tankerId;
            this.schedulingStatus = status;
            this.scheduleDate = localDateString();
            this.scheduleTime = '5:30 بەیانی';
            this.showScheduleModal = true;
        },

        closeScheduleModal() {
            this.showScheduleModal = false;
            this.schedulingTankerId = null;
            this.schedulingStatus = null;
        },

        async confirmSchedule() {
            if (!this.scheduleDate) return;

            await this.updateStatus(this.schedulingTankerId, this.schedulingStatus, {
                scheduled_date: this.scheduleDate,
                scheduled_time: this.scheduleTime,
            });
            this.closeScheduleModal();
        },

        async updateStatus(tankerId, status, extraData = {}) {
            await this.queueOperation('status', tankerId, { status, ...extraData });
        },

        async revertStatus(tankerId) {
            await this.updateStatus(tankerId, 'pending', {
                scheduled_date: null,
                scheduled_time: null,
            });
        },

        async updateNote(tankerId, note) {
            await this.queueOperation('note', tankerId, { note });
        },

        async queueOperation(type, tankerId, payload) {
            const operation = {
                operation_uuid: newUuid(),
                type,
                tanker_id: Number(tankerId),
                payload,
                client_created_at: new Date().toISOString(),
            };

            this.applyOperation(operation);

            try {
                await this.store.enqueue(operation);
                this.pendingCount = (await this.store.pendingOperations()).length;
                if (navigator.onLine) this.syncNow();
            } catch (error) {
                console.error('Could not save the offline operation:', error);
                this.syncError = 'گۆڕانکارییەکە لە ئامێرەکە هەڵنەگیرا.';
            }
        },

        applyOperation(operation) {
            const index = this.tankers.findIndex(tanker => Number(tanker.id) === Number(operation.tanker_id));
            if (index === -1) return;

            const tanker = this.tankers[index];
            const queue = normalizedQueue(tanker.queue);

            if (operation.type === 'status') {
                Object.assign(queue, operation.payload);
            } else if (operation.type === 'note') {
                queue.note = operation.payload.note || '';
            }

            this.tankers[index] = { ...tanker, queue };
            this.tankers = [...this.tankers];
        },

        applyPendingOperations(operations) {
            operations.forEach(operation => this.applyOperation(operation));
        },

        async syncNow() {
            if (this.syncing || !this.store.database) return;

            this.syncing = true;
            this.syncError = '';
            this.authRequired = false;

            try {
                const operations = await this.store.pendingOperations();
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const response = await fetch(options.syncUrl || '/gatekeeper/sync', {
                    method: operations.length ? 'POST' : 'GET',
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: operations.length ? JSON.stringify({ operations }) : undefined,
                });

                if (response.status === 401 || response.status === 419) {
                    this.authRequired = true;
                    throw new Error('Authentication is required before synchronization.');
                }

                if (!response.ok) throw new Error(`Synchronization failed with status ${response.status}.`);

                const result = await response.json();
                const snapshot = result.snapshot || result;
                await this.store.removeOperations(result.accepted || []);
                await this.store.replaceSnapshot(snapshot);

                const state = await this.store.readState();
                this.tankers = state.tankers.map(tanker => ({
                    ...tanker,
                    queue: normalizedQueue(tanker.queue),
                }));
                this.applyPendingOperations(state.outbox);
                this.pendingCount = state.outbox.length;
                this.lastSyncedAt = snapshot.synced_at || result.synced_at || new Date().toISOString();
                this.online = true;

                if (this.pendingCount > 0) {
                    window.setTimeout(() => this.syncNow(), 250);
                }
            } catch (error) {
                console.error('Gatekeeper synchronization failed:', error);
                this.online = false;
                this.pendingCount = (await this.store.pendingOperations()).length;
                this.syncError = this.authRequired
                    ? 'بۆ هاوکاتکردنەوە پێویستە دووبارە بچیتە ژوورەوە.'
                    : 'ئینتەرنێت بەردەست نییە؛ گۆڕانکارییەکان لەم ئامێرە هەڵگیراون.';
            } finally {
                this.syncing = false;
            }
        },

        async migrateLegacyChanges() {
            const legacyRaw = localStorage.getItem('pendingSyncs');
            if (!legacyRaw) return;

            try {
                const legacyOperations = JSON.parse(legacyRaw);
                for (const item of legacyOperations) {
                    const payload = item.payload || item;
                    const type = item.type || (Object.prototype.hasOwnProperty.call(payload, 'note') ? 'note' : 'status');
                    await this.store.enqueue({
                        operation_uuid: newUuid(),
                        type,
                        tanker_id: Number(payload.tanker_id),
                        payload: type === 'note'
                            ? { note: payload.note || '' }
                            : {
                                status: payload.status,
                                ...(payload.scheduled_date ? { scheduled_date: payload.scheduled_date } : {}),
                                ...(payload.scheduled_time ? { scheduled_time: payload.scheduled_time } : {}),
                            },
                        client_created_at: new Date().toISOString(),
                    });
                }

                localStorage.removeItem('pendingSyncs');
                localStorage.removeItem('queueStatuses');
            } catch (error) {
                console.error('Legacy offline changes could not be migrated:', error);
            }
        },

        formattedLastSync() {
            if (!this.lastSyncedAt) return 'هێشتا هاوکات نەکراوەتەوە';
            return new Intl.DateTimeFormat('ku', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(this.lastSyncedAt));
        },

        async resetQueue(form) {
            if (!confirm('دڵنیای لە سفرکردنەوەی هەموو سەرەکان؟ پێش سفرکردنەوە ڕاپۆرتێکی PDF دروست و دادەبەزێنرێت.')) return;

            if (!navigator.onLine) {
                alert('بۆ دروستکردنی ڕاپۆرت و سفرکردنەوە، پێویستە ئینتەرنێت بەرقەرار بێت.');
                return;
            }

            this.isResetting = true;

            try {
                await new Promise(resolve => setTimeout(resolve, 1100));
                await this.syncNow();
                if (this.pendingCount > 0) throw new Error('Pending changes could not be synchronized.');

                const response = await fetch(form.action, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/pdf',
                    },
                });
                if (!response.ok) throw new Error('The report could not be created.');

                const blob = await response.blob();
                const disposition = response.headers.get('Content-Disposition') || '';
                const nameMatch = disposition.match(/filename="?([^";]+)"?/i);
                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = nameMatch ? nameMatch[1] : 'tanker_queue_report.pdf';
                document.body.appendChild(link);
                link.click();
                link.remove();
                URL.revokeObjectURL(link.href);

                await this.syncNow();
            } catch (error) {
                console.error('Queue reset failed:', error);
                alert('ڕاپۆرت دروست نەکرا و سەرەکان سفر نەکرانەوە. تکایە دووبارە هەوڵ بدەوە.');
            } finally {
                this.isResetting = false;
            }
        },
    };
}
