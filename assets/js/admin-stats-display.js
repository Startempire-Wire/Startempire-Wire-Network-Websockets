/**
 * LOCATION: assets/js/admin-stats-display.js  
 * DEPENDENCIES: Chart.js, Moment.js  
 * VARIABLES: statsData  
 * CLASSES: StatsRenderer  
 *
 * Visualizes network performance metrics and connection analytics. Correlates server load with membership tier activity patterns. Maintains historical data for capacity planning.
 */

class AdminStatsDisplay {
    constructor() {
        this.charts = new Map();
        this.initializeCharts();
        this.connectToStatsSocket();
    }

    initializeCharts() {
        // Connection chart
        this.charts.set('connections', new Chart(
            document.getElementById('connections-chart'),
            this.getChartConfig('Connections')
        ));

        // Bandwidth chart
        this.charts.set('bandwidth', new Chart(
            document.getElementById('bandwidth-chart'),
            this.getChartConfig('Bandwidth (KB/s)')
        ));
    }

    connectToStatsSocket() {
        const socket = io(`ws://${window.location.hostname}:${sewn_ws_admin.port}/admin`);

        socket.on('stats_update', (data) => {
            this.updateDisplays(data);
        });
    }

    updateDisplays(data) {
        this.updateConnectionsChart(data.connections);
        this.updateBandwidthChart(data.bandwidth);
        this.updateEventLog(data.events);
    }
} 