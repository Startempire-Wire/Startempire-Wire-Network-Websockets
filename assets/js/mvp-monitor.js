class MVPMonitor {
    constructor() {
        this.metrics = {
            connections: new TimeSeries(60),
            bandwidth: new TimeSeries(60)
        };

        this.socket = new ReconnectingWebSocket(
            `${WS_ADMIN.endpoint}?token=${WS_ADMIN.jwt}`
        );
    }

    init() {
        this.socket.onmessage = (event) => {
            const data = JSON.parse(event.data);
            this.updateConnections(data.connections);
            this.updateBandwidth(data.rx, data.tx);
            this.updateEventLog(data.events);
        };
    }
}