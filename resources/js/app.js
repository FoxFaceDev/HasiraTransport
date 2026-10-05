import './bootstrap';

import Alpine from 'alpinejs';
import { gatekeeperQueueManager } from './gatekeeper';

window.Alpine = Alpine;
window.gatekeeperQueueManager = gatekeeperQueueManager;
window.printDataTable = function (sourceTable, title, rowAttribute, options = {}) {
    const table = sourceTable.cloneNode(true);
    const width = options.width || 1100;
    const fontSize = options.fontSize || 13;
    const pageMargin = options.pageMargin || 12;

    table.querySelectorAll('tr').forEach(row => row.lastElementChild?.remove());
    table.querySelectorAll('tbody tr').forEach(row => {
        if (!row.hasAttribute(rowAttribute) || row.style.display === 'none') {
            row.remove();
        }
    });

    const printWindow = window.open('', '_blank', `width=${width},height=750`);
    if (!printWindow) {
        window.alert('تکایە ڕێگە بە کردنەوەی پەنجەرەی چاپ بدە.');

        return;
    }

    printWindow.document.write(`<!doctype html><html dir="rtl"><head><meta charset="utf-8"><title>${title}</title><style>@page{size:landscape;margin:${pageMargin}mm}body{font-family:Arial,sans-serif;direction:rtl;color:#111}h1{text-align:center;font-size:22px;margin:0 0 18px}table{width:100%;border-collapse:collapse;font-size:${fontSize}px}th,td{border:1px solid #777;padding:7px;text-align:right}th{background:#e5e7eb;font-weight:700}.blocked-badge{display:inline-block;margin-right:6px;font-size:10px}</style></head><body><h1>${title}</h1>${table.outerHTML}</body></html>`);
    printWindow.document.close();
    printWindow.focus();
    window.setTimeout(() => printWindow.print(), 250);
};

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.getRegistrations()
            .then(registrations => Promise.all(
                registrations
                    .filter(registration => {
                        const worker = registration.active || registration.waiting || registration.installing;
                        return worker && new URL(worker.scriptURL).pathname === '/sw.js';
                    })
                    .map(registration => registration.unregister())
            ))
            .catch(error => console.error('Could not remove the old service worker:', error));
    });
}

if ('caches' in window) {
    caches.keys()
        .then(keys => Promise.all(keys.filter(key => key.startsWith('hasira-')).map(key => caches.delete(key))))
        .catch(error => console.error('Could not remove the old offline cache:', error));
}

if ('indexedDB' in window) {
    indexedDB.deleteDatabase('hasira-gatekeeper');
}

localStorage.removeItem('pendingSyncs');
localStorage.removeItem('queueStatuses');

function enableScrollableTable(tableWrapper) {
    const table = Array.from(tableWrapper.children).find(child => child instanceof HTMLTableElement);
    if (!table) {
        return;
    }

    tableWrapper.classList.add('table-scroll-viewport');
    tableWrapper.tabIndex = 0;
    tableWrapper.setAttribute('aria-label', 'خشتەیەکی خولپێدراو');
    sizeTableColumns(table);
}

function sizeTableColumns(table) {
    const headers = Array.from(table.querySelectorAll('thead tr:first-child > th'));

    headers.forEach((header, index) => {
        const label = header.textContent.replace(/\s+/g, ' ').trim();
        let columnClass = null;

        if (label === '#' || label === 'ڕیزبەندی' || label === 'کۆدی حەسیرە') {
            columnClass = 'table-col-rank';
        } else if (label.includes('تابلۆ') || label.includes('تەنکەر')) {
            columnClass = 'table-col-plate';
        } else if (label.includes('خاوەنی')) {
            columnClass = 'table-col-owner';
        } else if (label.includes('مۆبایل')) {
            columnClass = 'table-col-phone';
        } else if (
            label.includes('کاتی سلێمانی')
            || label.includes('کاتی بلۆککردن')
            || label.includes('کاتی سفرکردنەوە')
            || label.includes('بەرواری دروستکردن')
            || (label.includes('بەروار') && label.includes('کات'))
        ) {
            columnClass = 'table-col-datetime';
        } else if (label === 'بەروار') {
            columnClass = 'table-col-date';
        } else if (label === 'کات') {
            columnClass = 'table-col-time';
        }

        if (!columnClass) {
            return;
        }

        header.classList.add(columnClass);
        table.querySelectorAll(`tbody tr > :nth-child(${index + 1})`).forEach(cell => cell.classList.add(columnClass));
        table.querySelectorAll('template').forEach(template => {
            template.content.querySelectorAll(`tr > :nth-child(${index + 1})`).forEach(cell => cell.classList.add(columnClass));
        });
    });
}

document.querySelectorAll('.overflow-x-auto').forEach(enableScrollableTable);

Alpine.start();
