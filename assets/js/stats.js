/**
 * Location: Statistics display components
 * Dependencies: EventSource API, DOM manipulation utilities
 * Variables: WsStats class, DOM elements for stats display
 * 
 * Collects and displays real-time server statistics through server-sent events. Updates connection
 * counters and bandwidth metrics. Implements basic data smoothing for display consistency.
 */

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