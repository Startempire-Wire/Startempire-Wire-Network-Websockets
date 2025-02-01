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