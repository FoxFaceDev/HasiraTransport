import './bootstrap';

import Alpine from 'alpinejs';
import { gatekeeperQueueManager } from './gatekeeper-offline';

window.Alpine = Alpine;
window.gatekeeperQueueManager = gatekeeperQueueManager;

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(error => {
            console.error('Service worker registration failed:', error);
        });
    });
}

document.addEventListener('submit', event => {
    const form = event.target;
    if (form instanceof HTMLFormElement && form.action.endsWith('/logout')) {
        navigator.serviceWorker?.controller?.postMessage({ type: 'CLEAR_PRIVATE_CACHE' });
    }
});

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

        if (label === '#' || label === 'ڕیزبەندی') {
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
