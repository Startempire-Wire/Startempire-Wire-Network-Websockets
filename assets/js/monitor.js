class WsMonitor {
    constructor() {
        this.dash = new Dashboard({
            containers: {
                connections: '#live-connections',
                bandwidth: '#network-bandwidth',
                errors: '#error-rates'
            },
            refreshInterval: 2000
        });

        this.initEventSource();
    }

    initEventSource() {
        const eventSource = new EventSource('/wp-json/sewn-ws/v1/events');

        eventSource.onmessage = (event) => {
            const data = JSON.parse(event.data);
            this.dash.update(data);
        };
    }
} 