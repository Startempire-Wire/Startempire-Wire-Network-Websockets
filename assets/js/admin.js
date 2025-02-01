// # Dashboard interactivity

// Import components
import { StatsDisplay } from './components/StatsDisplay.js';
import { ServerControls } from './components/ServerControls.js';
import { LogViewer } from './components/LogViewer.js';
import { TimeSeriesMetric } from './components/TimeSeriesMetric.js';

class WebSocketAdmin {
    constructor() {
        this.initializeComponents();
        this.bindEvents();
        this.startStatsPolling();
        this.initializeWebSocket();
        this.statsDisplay = new StatsDisplay();
        this.serverControls = new ServerControls();
        this.logViewer = new LogViewer({
            container: '#log-container',
            levelSelector: '#log-level',
            clearButton: '#clear-logs'
        });

        this.metrics = {
            connections: new TimeSeriesMetric('#connections-graph', {
                retention: 3600,  // 1 hour of data
                resolution: 10    // 10 second intervals
            }),
            memory: new TimeSeriesMetric('#memory-graph'),
            errors: new TimeSeriesMetric('#error-graph')
        };
    }

    initializeComponents() {
        this.statsDisplay = new StatsDisplay({
            connections: '#connection-count',
            bandwidth: '#bandwidth-usage',
            errorRate: '#error-rate'
        });

        this.serverControls = new ServerControls({
            startButton: '[data-action="start"]',
            stopButton: '[data-action="stop"]',
            restartButton: '[data-action="restart"]'
        });

        this.logViewer = new LogViewer('#server-logs', {
            levelSelector: '#log-level',
            clearButton: '#clear-logs'
        });
    }

    bindEvents() {
        // Server control events
        document.querySelectorAll('.sewn-ws-control').forEach(button => {
            button.addEventListener('click', async (e) => {
                const action = e.target.dataset.action;
                await this.handleServerAction(action);
            });
        });

        // Log filter events
        document.getElementById('log-level').addEventListener('change', (e) => {
            this.logViewer.setLevel(e.target.value);
        });

        // Clear logs
        document.getElementById('clear-logs').addEventListener('click', () => {
            this.logViewer.clear();
        });
    }

    async handleServerAction(action) {
        const button = document.querySelector(`[data-action="${action}"]`);
        const originalText = button.textContent;

        try {
            button.disabled = true;
            button.textContent = '...';

            const response = await fetch(sewn_ws_admin.ajax_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    action: 'sewn_ws_server_control',
                    command: action,
                    nonce: sewn_ws_admin.nonce
                })
            });

            const data = await response.json();

            if (data.success) {
                this.showNotice('success', data.message);
                this.updateServerStatus(data.status);
            } else {
                this.showNotice('error', data.message);
            }
        } catch (error) {
            this.showNotice('error', 'Server action failed: ' + error.message);
        } finally {
            button.disabled = false;
            button.textContent = originalText;
        }
    }

    showNotice(type, message) {
        const notice = document.createElement('div');
        notice.className = `notice notice-${type} is-dismissible`;
        notice.innerHTML = `<p>${message}</p>`;

        const wrap = document.querySelector('.wrap');
        wrap.insertBefore(notice, wrap.firstChild);

        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            notice.remove();
        }, 5000);
    }

    startStatsPolling() {
        setInterval(() => {
            this.updateStats();
        }, 2000);
    }

    async updateStats() {
        try {
            const response = await fetch(sewn_ws_admin.ajax_url + '?action=sewn_ws_get_stats');
            const data = await response.json();
            this.statsDisplay.update(data);
        } catch (error) {
            console.error('Failed to update stats:', error);
        }
    }

    updateServerStatus(status) {
        const statusElement = document.querySelector('.sewn-ws-status');
        statusElement.className = `sewn-ws-status ${status.toLowerCase()}`;
        statusElement.querySelector('.status-text').textContent = status;

        // Update button states
        document.querySelector('[data-action="start"]').disabled = status === 'Running';
        document.querySelector('[data-action="stop"]').disabled = status === 'Stopped';
    }

    initializeWebSocket() {
        const socket = io(`ws://${window.location.hostname}:${sewn_ws_admin.port}/admin`, {
            reconnection: true,
            reconnectionDelay: 1000,
            reconnectionDelayMax: 5000,
            reconnectionAttempts: 5
        });

        socket.on('stats_update', (data) => {
            this.updateMetrics(data);
            this.updateTierStats(data.tiers);
            this.checkThresholds(data);
        });

        socket.on('error_alert', (error) => {
            this.handleError(error);
        });

        // Handle reconnection
        socket.on('reconnect_attempt', (attempt) => {
            this.updateStatus('reconnecting');
        });

        socket.on('reconnect_failed', () => {
            this.updateStatus('disconnected');
            this.showAlert('Connection lost to WebSocket server');
        });
    }

    updateMetrics(data) {
        Object.entries(this.metrics).forEach(([key, metric]) => {
            metric.addDataPoint(data[key]);
        });

        // Update tier-specific stats
        this.updateTierStats(data.tiers);
    }

    checkThresholds(data) {
        const thresholds = {
            memory: 80,  // 80% memory usage
            errors: 50,  // 50 errors per minute
            connections: 1000 // 1000 concurrent connections
        };

        Object.entries(thresholds).forEach(([metric, threshold]) => {
            if (data[metric] > threshold) {
                this.showAlert(`${metric} threshold exceeded: ${data[metric]}`);
            }
        });
    }

    updateStatus(status) {
        const statusEl = document.querySelector('.sewn-ws-status');
        statusEl.className = `sewn-ws-status ${status}`;
    }

    showAlert(message) {
        const alert = document.createElement('div');
        alert.className = 'notice notice-error is-dismissible';
        alert.innerHTML = `<p>${message}</p>`;

        const wrap = document.querySelector('.wrap');
        wrap.insertBefore(alert, wrap.firstChild);

        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            alert.remove();
        }, 5000);
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.wsAdmin = new WebSocketAdmin();
});