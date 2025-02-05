/**
 * Location: Network monitoring interface
 * Dependencies: EventSource API, sewn_ws_admin localized object
 * Variables: WsMonitor class, DOM elements for monitoring panels
 * 
 * Implements real-time network monitoring through server-sent events. Tracks connection health, 
 * bandwidth usage, and error rates. Displays historical trends for performance analysis.
 */

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