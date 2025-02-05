/**
 * Location: Admin dashboard statistics display
 * Dependencies: WebSocket API, Chart.js library
 * Variables: DashboardStats class, DOM elements for metrics display
 * 
 * Provides real-time visualization of WebSocket server metrics including active connections, message
 * throughput, and system resource usage. Updates dashboard widgets via WebSocket data stream.
 */

class DashboardStats {
    constructor() {
        this.connectionsCount = document.getElementById('live-connections-count');
        this.roomsList = document.getElementById('active-rooms-list');
        this.eventLog = document.getElementById('event-log');

        this.initWebSocket();
        this.initEventSource();
    }

    initWebSocket() {
        const ws = new WebSocket(`ws://${window.location.hostname}:${sewn_ws_admin.port}`);
        ws.onmessage = (event) => this.handleStatsUpdate(JSON.parse(event.data));
    }

    handleStatsUpdate(data) {
        this.updateConnectionsCount(data.connections);
        this.updateRoomsList(data.rooms);
        this.updateEventLog(data.events);
    }
} 