/**
 * LOCATION: assets/js/init.js
 * DEPENDENCIES: WebSocket API, DOM Events
 * VARIABLES: sewnWebsockets.wsUrl
 * CLASSES: WebSocketHandlers (connection manager)
 * 
 * Initializes WebSocket connections for real-time browser communication. Supports WebRing content distribution
 * features and membership-tier synchronized updates. Implements network authentication handshake sequence
 * during connection establishment.
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