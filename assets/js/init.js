/**
 * Initialize WebSocket functionality
 * 
 * @package Startempire_Wire_Network_Websockets
 */

import WebSocketHandlers from './websocket-handlers';

document.addEventListener('DOMContentLoaded', () => {
    // Only initialize on admin pages
    if (!window.sewnWebsockets || !window.sewnWebsockets.wsUrl) {
        return;
    }

    const socket = new WebSocket(window.sewnWebsockets.wsUrl);
    window.sewn = window.sewn || {};
    window.sewn.websocket = new WebSocketHandlers(socket);
}); 