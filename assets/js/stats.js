class WsStats {
    constructor() {
        this.connections = 0;
        this.initEventSource();
    }

    initEventSource() {
        const eventSource = new EventSource('/wp-json/sewn-ws/v1/stats-stream');

        eventSource.onmessage = (e) => {
            const data = JSON.parse(e.data);
            this.updateDisplay(data);
        };
    }

    updateDisplay(data) {
        document.querySelector('.connection-count').textContent = data.connections;
        document.querySelector('.bandwidth').textContent = `${data.bandwidth}KB/s`;
    }
} 