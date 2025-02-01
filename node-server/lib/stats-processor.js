class StatsProcessor {
    constructor(io, connectionManager) {
        this.io = io;
        this.connectionManager = connectionManager;
        this.stats = {
            connections: new Map(),
            events: [],
            bandwidth: {
                in: 0,
                out: 0
            }
        };

        this.initializeMetrics();
    }

    initializeMetrics() {
        setInterval(() => this.broadcastMetrics(), 1000);
        this.setupEventListeners();
    }

    setupEventListeners() {
        this.io.on('connection', (socket) => {
            this.trackSocketMetrics(socket);
        });
    }

    trackSocketMetrics(socket) {
        const startTime = Date.now();
        let bytesTransferred = 0;

        socket.on('data', (data) => {
            bytesTransferred += data.length;
            this.updateBandwidthMetrics('in', data.length);
        });

        socket.on('disconnect', () => {
            this.stats.connections.delete(socket.id);
        });
    }

    broadcastMetrics() {
        const metrics = {
            connections: this.connectionManager.getConnectionStats(),
            bandwidth: this.stats.bandwidth,
            events: this.stats.events.slice(-100)
        };

        this.io.to('admin').emit('stats_update', metrics);
    }
}

module.exports = StatsProcessor; 