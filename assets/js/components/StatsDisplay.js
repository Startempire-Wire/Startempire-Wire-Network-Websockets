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
        // Update numeric displays
        this.elements.connections.textContent = data.connections;
        this.elements.bandwidth.textContent = this.formatBandwidth(data.bandwidth);
        this.elements.errorRate.textContent = `${data.errorRate}%`;

        // Update history
        this.updateHistory('connections', data.connections);
        this.updateHistory('bandwidth', data.bandwidth);
        this.updateHistory('errors', data.errorRate);

        // Update graphs
        this.updateGraphs();
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
} 