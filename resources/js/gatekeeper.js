function normalizedQueue(queue = {}) {
    return {
        status: queue.status || 'pending',
        scheduled_date: queue.scheduled_date || null,
        scheduled_time: queue.scheduled_time || null,
        note: queue.note || '',
        status_updated_at: queue.status_updated_at || queue.updated_at || null,
        updated_at: queue.updated_at || null,
    };
}

function localDateString() {
    const now = new Date();
    const offset = now.getTimezoneOffset() * 60000;

    return new Date(now.getTime() - offset).toISOString().split('T')[0];
}

export function gatekeeperQueueManager(initialSnapshot = {}, options = {}) {
    return {
        tankers: (initialSnapshot.tankers || []).map(tanker => ({ ...tanker, queue: normalizedQueue(tanker.queue) })),
        search: '',
        sortMode: options.sortMode || 'queue',
        exportBaseUrl: options.exportBaseUrl || '/gatekeeper/export',
        statusFilter: options.statusFilter || null,
        filterDate: options.filterDate || '',
        scheduleOnly: options.scheduleOnly || false,
        selectedDate: options.dateFilter || localDateString(),
        online: navigator.onLine,
        isResetting: false,
        showActionsModal: false,
        actionsTanker: null,
        showScheduleModal: false,
        schedulingTankerId: null,
        schedulingStatus: null,
        scheduleDate: localDateString(),
        scheduleTime: '5:30 بەیانی',

        init() {
            window.addEventListener('online', () => this.online = true);
            window.addEventListener('offline', () => this.online = false);
        },

        get visibleTankers() {
            const query = this.search.trim().toLowerCase();

            const visible = this.tankers.filter(tanker => {
                if (this.statusFilter && this.getStatus(tanker) !== this.statusFilter) return false;
                if (this.filterDate && ['green', 'yellow', 'departed'].includes(this.statusFilter)) {
                    if (tanker.queue?.scheduled_date !== this.filterDate) return false;
                }
                if (this.scheduleOnly) {
                    const status = this.getStatus(tanker);
                    if (!['green', 'yellow', 'departed'].includes(status)) return false;
                    if (tanker.queue?.scheduled_date !== this.selectedDate) return false;
                }
                if (!query) return true;

                return [
                    tanker.plate_number,
                    tanker.sequence_number,
                    tanker.sequence_owner,
                    tanker.sequence_owner_phone,
                    tanker.vin,
                    tanker.truck_type,
                    tanker.truck_model,
                    tanker.truck_color,
                    this.getBlockReason(tanker),
                ].some(value => String(value || '').toLowerCase().includes(query));
            });

            const sorted = visible.sort((first, second) => {
                if (this.sortMode === 'scheduled') {
                    const firstDate = first.queue?.scheduled_date || '9999-12-31';
                    const secondDate = second.queue?.scheduled_date || '9999-12-31';
                    const dateComparison = firstDate.localeCompare(secondDate);
                    if (dateComparison !== 0) return dateComparison;

                    const timeRank = value => {
                        const time = String(value || '');
                        if (time.startsWith('5:30')) return 0;
                        if (time.startsWith('12:00')) return 1;
                        return 2;
                    };
                    const firstTimeRank = timeRank(first.queue?.scheduled_time);
                    const secondTimeRank = timeRank(second.queue?.scheduled_time);
                    if (firstTimeRank !== secondTimeRank) return firstTimeRank - secondTimeRank;

                    const timeComparison = String(first.queue?.scheduled_time || '')
                        .localeCompare(String(second.queue?.scheduled_time || ''), undefined, { numeric: true });
                    if (timeComparison !== 0) return timeComparison;

                    return String(first.sequence_number || '').localeCompare(
                        String(second.sequence_number || ''),
                        undefined,
                        { numeric: true, sensitivity: 'base' },
                    );
                }

                if (['newest', 'oldest'].includes(this.sortMode)) {
                    const firstChangedAt = Date.parse(
                        first.queue?.status_updated_at || first.queue?.updated_at || first.created_at || '',
                    ) || 0;
                    const secondChangedAt = Date.parse(
                        second.queue?.status_updated_at || second.queue?.updated_at || second.created_at || '',
                    ) || 0;

                    if (this.sortMode === 'oldest') {
                        return firstChangedAt - secondChangedAt || Number(first.id) - Number(second.id);
                    }

                    return secondChangedAt - firstChangedAt || Number(second.id) - Number(first.id);
                }

                return String(first.sequence_number || '').localeCompare(
                    String(second.sequence_number || ''),
                    undefined,
                    { numeric: true, sensitivity: 'base' },
                );
            });

            if (this.statusFilter === 'yellow' && this.sortMode === 'scheduled') {
                sorted.forEach((tanker, index) => {
                    tanker.starts_new_schedule_day = index > 0
                        && sorted[index - 1].queue?.scheduled_date !== tanker.queue?.scheduled_date;
                });
            }

            return sorted;
        },

        get exportUrl() {
            const url = new URL(this.exportBaseUrl, window.location.origin);

            url.searchParams.set('sort', this.sortMode);
            if (this.search.trim()) url.searchParams.set('search', this.search.trim());
            if (this.statusFilter) url.searchParams.set('status', this.statusFilter);

            if (this.scheduleOnly) {
                url.searchParams.set('schedule', '1');
                url.searchParams.set('date', this.selectedDate);
            } else if (this.filterDate) {
                url.searchParams.set('date', this.filterDate);
            }

            return url.toString();
        },

        isBlocked(tanker) {
            return Boolean(tanker?.blocked_at);
        },

        getBlockReason(tanker) {
            return tanker?.blocked_at ? 'خەت بلۆککراوە' : '';
        },

        getStatus(tanker) {
            return tanker?.queue?.status || 'pending';
        },

        getStatusLabel(tanker) {
            if (this.getStatus(tanker) === 'green') return 'هاتن';
            if (this.getStatus(tanker) === 'yellow') return 'دواخستن';
            if (this.getStatus(tanker) === 'departed') return 'ڕۆیشتن';
            return '';
        },

        changeScheduleDate(date) {
            this.selectedDate = date || localDateString();

            const url = new URL(window.location.href);
            url.searchParams.set('date', this.selectedDate);
            window.history.replaceState({}, '', url);
        },

        changeFilterDate(date) {
            this.filterDate = date || '';

            const url = new URL(window.location.href);
            if (this.filterDate) {
                url.searchParams.set('date', this.filterDate);
            } else {
                url.searchParams.delete('date');
            }
            window.history.replaceState({}, '', url);
        },

        getRowClass(tanker) {
            if (this.isBlocked(tanker)) return 'blocked-row';
            const status = this.getStatus(tanker);
            if (status === 'green') return 'queue-row-green';
            if (status === 'red') return 'queue-row-red';
            if (status === 'yellow') return 'queue-row-yellow';
            if (status === 'departed') return 'queue-row-departed';
            return '';
        },

        getScheduleDayDividerClass(index, tanker) {
            if (this.statusFilter !== 'yellow' || this.sortMode !== 'scheduled' || index === 0) return '';
            return tanker.starts_new_schedule_day ? 'border-t-4 border-t-slate-500' : '';
        },

        getScheduleRowClass(tanker) {
            if (this.getStatus(tanker) === 'green') return 'queue-row-green';
            if (this.getStatus(tanker) === 'departed') return 'queue-row-departed';
            return 'queue-row-yellow';
        },

        openActionsModal(tanker) {
            this.actionsTanker = tanker;
            this.showActionsModal = true;
        },

        closeActionsModal() {
            this.showActionsModal = false;
            this.actionsTanker = null;
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

            const saved = await this.updateStatus(this.schedulingTankerId, this.schedulingStatus, {
                scheduled_date: this.scheduleDate,
                scheduled_time: this.scheduleTime,
            });
            if (saved) this.closeScheduleModal();
        },

        async updateStatus(tankerId, status, extraData = {}) {
            const payload = { status, ...extraData };
            const result = await this.send(`/gatekeeper/queue/${encodeURIComponent(tankerId)}`, payload);
            if (result) {
                this.applyChange('status', tankerId, {
                    ...payload,
                    scheduled_date: result.scheduled_date,
                    scheduled_time: result.scheduled_time,
                    status_updated_at: result.status_updated_at,
                });
            }
            return result;
        },

        async revertStatus(tankerId) {
            return this.updateStatus(tankerId, 'pending', {
                scheduled_date: null,
                scheduled_time: null,
            });
        },

        async updateNote(tankerId, note) {
            const payload = { note };
            const saved = await this.send(`/gatekeeper/queue/${encodeURIComponent(tankerId)}/note`, payload);
            if (saved) this.applyChange('note', tankerId, payload);
            return saved;
        },

        async editPhone(tanker) {
            const phone = prompt('ژمارەی مۆبایلی نوێ بنووسە:', tanker.sequence_owner_phone || '');
            if (phone === null || phone.trim() === String(tanker.sequence_owner_phone || '')) return;

            const payload = { sequence_owner_phone: phone.trim() || null };
            const result = await this.send(
                `/gatekeeper/tankers/${encodeURIComponent(tanker.id)}/phone`,
                payload,
                'PATCH',
            );
            if (result) {
                tanker.sequence_owner_phone = result.sequence_owner_phone;
                this.tankers = [...this.tankers];
            }
        },

        async setBlocked(tanker, blocked) {
            const question = blocked ? 'دڵنیای لە بلۆککردنی ئەم خەتە؟' : 'دڵنیای لە لابردنی بلۆکی ئەم خەتە؟';
            if (!confirm(question)) return;

            const result = await this.send(
                `/gatekeeper/tankers/${encodeURIComponent(tanker.id)}/block`,
                {},
                blocked ? 'PATCH' : 'DELETE',
            );
            if (result) {
                tanker.blocked_at = result.blocked_at;
                this.tankers = [...this.tankers];
                this.closeActionsModal();
            }
        },

        async send(url, payload, method = 'POST') {
            try {
                const response = await fetch(url, {
                    method,
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                    body: JSON.stringify(payload),
                });

                if (!response.ok) throw new Error(`Request failed with status ${response.status}.`);
                this.online = true;
                return await response.json();
            } catch (error) {
                console.error('Gatekeeper update failed:', error);
                this.online = navigator.onLine;
                alert('گۆڕانکارییەکە هەڵنەگیرا. تکایە ئینتەرنێت بپشکنە و دووبارە هەوڵ بدەوە.');
                return null;
            }
        },

        applyChange(type, tankerId, payload) {
            const index = this.tankers.findIndex(tanker => Number(tanker.id) === Number(tankerId));
            if (index === -1) return;

            const tanker = this.tankers[index];
            const queue = normalizedQueue(tanker.queue);

            if (type === 'status') {
                Object.assign(queue, payload);
            } else {
                queue.note = payload.note || '';
            }

            this.tankers[index] = { ...tanker, queue };
            this.tankers = [...this.tankers];
        },

        async resetQueue(form) {
            if (!confirm('دڵنیای لە سفرکردنەوەی هەموو سەرەکان؟ پێش سفرکردنەوە ڕاپۆرتێکی PDF دروست و دادەبەزێنرێت.')) return;

            if (!navigator.onLine) {
                alert('بۆ دروستکردنی ڕاپۆرت و سفرکردنەوە، پێویستە ئینتەرنێت بەرقەرار بێت.');
                return;
            }

            this.isResetting = true;

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    credentials: 'same-origin',
                    cache: 'no-store',
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

                window.location.reload();
            } catch (error) {
                console.error('Queue reset failed:', error);
                alert('ڕاپۆرت دروست نەکرا و سەرەکان سفر نەکرانەوە. تکایە دووبارە هەوڵ بدەوە.');
            } finally {
                this.isResetting = false;
            }
        },
    };
}
