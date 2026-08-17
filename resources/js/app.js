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

Alpine.start();
