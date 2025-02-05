/**
 * LOCATION: assets/js/components/StatsDisplay.js
 * DEPENDENCIES: Chart.js, WebSocket connection
 * VARIABLES: sewn_ws_admin.statsEndpoint
 * CLASSES: StatsDisplay (metrics visualization)
 * 
 * Visualizes real-time network metrics including connections, rooms, and system resources. Supports WebRing
 * content distribution monitoring by correlating server load with membership-tier activity patterns. Updates
 * synchronously with network authentication events.
 */

class StatsDisplay {
    constructor(selectors) {
        this.elements = {
            connections: document.querySelector('#live-connections-count'),
            rooms: document.querySelector('#active-rooms-list'),
            memory: document.querySelector('#memory-usage'),
            uptime: document.querySelector('#uptime')
        };

        this.graphs = {
            connections: this.initGraph('connections-graph', {
                color: '#0073aa',
                maxPoints: 20
            }),
            bandwidth: this.initGraph('bandwidth-graph', {
                color: '#46b450',
                maxPoints: 20
            }),
            errors: this.initGraph('errors-graph', {
                color: '#dc3232',
                maxPoints: 20
            })
        };

        this.history = {
            connections: [],
            bandwidth: [],
            errors: []
        };

        this.connectWebSocket();
    }

    connectWebSocket() {
        const socket = io(`ws://${window.location.hostname}:${sewn_ws_admin.port}/admin`);

        socket.on('stats_update', (stats) => {
            this.updateDisplay(stats);
        });
    }

    updateDisplay(stats) {
        if (this.elements.connections) {
            this.elements.connections.textContent = stats.connections;
        }
        if (this.elements.rooms) {
            this.elements.rooms.innerHTML = stats.rooms
                .map(room => `<div class="room-item">${room}</div>`)
                .join('');
        }
        if (this.elements.memory) {
            const memoryInMB = Math.round(stats.memory.heapUsed / 1024 / 1024);
            this.elements.memory.textContent = `${memoryInMB} MB`;
        }
        if (this.elements.uptime) {
            this.elements.uptime.textContent =
                Math.round(stats.uptime / 60) + ' minutes';
        }
    }

    initGraph(elementId, options) {
        return new Chart(document.getElementById(elementId).getContext('2d'), {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    data: [],
                    borderColor: options.color,
                    borderWidth: 2,
                    fill: false,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true },
                    x: { display: false }
                },
                animation: false
            }
        });
    }

    update(data) {
        // Keep existing connection tracking
        this.metrics.connections.add(data.connections);
        this.metrics.bandwidth.add(data.bandwidth);

        // Remove tier-specific updates
        document.getElementById('live-connections').textContent = data.connections;
        document.getElementById('message-throughput').textContent =
            `${data.message_rate}/s | ${data.bandwidth} MBps`;
    }

    updateHistory(metric, value) {
        this.history[metric].push(value);
        if (this.history[metric].length > 20) {
            this.history[metric].shift();
        }
    }

    updateGraphs() {
        const labels = [...Array(20)].map((_, i) => i.toString());

        Object.keys(this.graphs).forEach(metric => {
            const graph = this.graphs[metric];
            graph.data.labels = labels;
            graph.data.datasets[0].data = this.history[metric];
            graph.update();
        });
    }

    formatBandwidth(bytes) {
        const units = ['B/s', 'KB/s', 'MB/s', 'GB/s'];
        let value = bytes;
        let unitIndex = 0;

        while (value >= 1024 && unitIndex < units.length - 1) {
            value /= 1024;
            unitIndex++;
        }

        return `${value.toFixed(1)} ${units[unitIndex]}`;
    }

    async refreshStats() {
        try {
            const response = await fetch('/wp-json/sewn-ws/v1/stats', {
                headers: {
                    'Authorization': `Bearer ${sewn_ws_admin.jwt}`
                }
            });

            const data = await response.json();
            this.updateConnectionsChart(data.connections);
            this.updateBandwidthChart(data.throughput);
            this.updateSystemStats(data.system);

        } catch (error) {
            console.error('Stats fetch failed:', error);
        }
    }

    connectToStatsSocket() {
        this.socket = new WebSocket(`wss://${window.location.host}/ws-admin`);

        this.socket.onmessage = (event) => {
            this.updateDisplays(JSON.parse(event.data));
            this.updateSystemStats(event.data.system);
        };
    }

    updateSystemStats(stats) {
        document.getElementById('cpu-usage').textContent = `${stats.cpu}%`;
        document.getElementById('memory-usage').textContent = `${stats.memory} MB`;
    }
} 