// Dashboard interactivity
// Import components
import { StatsDisplay } from './components/StatsDisplay.js';
import { ServerControls } from './components/ServerControls.js';
import { LogViewer } from './components/LogViewer.js';
import { TimeSeriesMetric } from './components/TimeSeriesMetric.js';

class WebSocketAdmin {
    static instance = null;

    constructor() {
        if (WebSocketAdmin.instance) {
            return WebSocketAdmin.instance;
        }

        this.pendingRequests = new Map();
        this.abortController = null;
        this.statsErrorCount = 0;
        this.statsMaxErrors = 3;
        this.pollingBaseDelay = 10000; // 10 seconds
        this.serverStatus = 'stopped'; // Track server state
        this.initializeComponents();
        this.bindEvents();
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

        // Add nonce verification
        this.nonce = sewn_ws_admin?.nonce || '';
        if (!this.nonce) {
            console.error('WebSocket admin nonce not found');
            this.showAlert('Configuration error: Missing security token');
            return;
        }

        WebSocketAdmin.instance = this;
        this._boundBeforeUnload = () => this.destroy();
        window.addEventListener('beforeunload', this._boundBeforeUnload);
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
        document.addEventListener('click', (e) => {
            const button = e.target.closest('[data-action]');
            if (button && button.dataset.action) {
                e.preventDefault();
                this.handleServerAction(button.dataset.action);
            }
        });

        // Keep existing log viewer handlers
        document.querySelector('#log-level').addEventListener('change', () => this.logViewer.filterLogs());
        document.querySelector('#clear-logs').addEventListener('click', () => this.logViewer.clearLogs());

        // Add emergency stop button handler
        document.getElementById('emergency-stop').addEventListener('click', () => {
            this.cancelPendingRequests();
        });
    }

    async handleServerAction(action) {
        // Cancel any existing request for this action
        if (this.pendingRequests.has(action)) {
            this.pendingRequests.get(action).abort();
        }

        const controller = new AbortController();
        this.pendingRequests.set(action, controller);

        const button = document.querySelector(`[data-action="${action}"]`);
        try {
            button.disabled = true;
            button.innerHTML = `<span class="button-text">${action}ping...</span> 
                              <span class="loading-dots"></span>`;

            const response = await fetch(sewn_ws_admin.ajax_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-WP-Nonce': this.nonce
                },
                body: new URLSearchParams({
                    action: 'sewn_ws_server_control',
                    command: action,
                    nonce: this.nonce
                })
            });

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.message || 'Server request failed');
            }

            const data = await response.json();
            this.updateServerStatus(data.status);

            // Control stats polling based on new status
            if (data.status === 'Running') {
                this.startStatsPolling();
            } else {
                this.stopStatsPolling();
            }

        } catch (error) {
            const errorMessage = `Server ${action} failed:\n` +
                `- ${error.message || 'Unknown error'}\n` +
                `- PHP: ${error.php_version || 'unknown'}\n` +
                `- Node Path: ${error.node_path || 'undefined'}`;

            console.error('Server control error:', error);
            this.showAlert(errorMessage);
        } finally {
            this.pendingRequests.delete(action);
            button.disabled = false;
            button.innerHTML = `<span class="button-text">${action.charAt(0).toUpperCase() + action.slice(1)} Server</span>`;
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

    async startStatsPolling() {
        // Add cleanup before restarting
        if (this.statsInterval) {
            clearInterval(this.statsInterval);
        }

        const fetchStats = async () => {
            try {
                const response = await fetch(sewn_ws_admin.ajax_url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'sewn_ws_get_stats',
                        nonce: sewn_ws_admin.nonce
                    })
                });

                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);

                const data = await response.json();
                this.statsErrorCount = 0; // Reset error counter
                this.updateMetrics(data);

            } catch (error) {
                console.error('Stats polling error:', error);
                if (++this.statsErrorCount >= this.statsMaxErrors) {
                    console.warn('Max stats errors reached - stopping polling');
                    clearInterval(this.statsInterval);
                }
            }
        };

        // Initial fetch with backoff
        const initialDelay = Math.min(
            this.pollingBaseDelay * Math.pow(2, this.statsErrorCount),
            300000 // Max 5 minutes
        );

        this.statsInterval = setInterval(() => {
            fetchStats().catch(() => { }); // Prevent unhandled promise rejection
        }, initialDelay);
    }

    stopStatsPolling() {
        clearInterval(this.statsInterval);
        this.statsErrorCount = 0;
    }

    updateServerStatus(status) {
        const statusElement = document.querySelector('.sewn-ws-status');
        const statusConstants = window.sewnWebsockets.constants;

        statusElement.className = `sewn-ws-status ${status.toLowerCase()}`;
        statusElement.querySelector('.status-text').textContent = status;

        if (status === statusConstants.STATUS_ERROR) {
            this.showAlert(window.sewnWebsockets.i18n.serverError);
        }

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

    async connectRealTimeFeed() {
        this.socket = new WebSocket(`wss://${location.host}/sewn-ws`);

        this.socket.onmessage = (event) => {
            const data = JSON.parse(event.data);
            this.statsDisplay.update(data);

            // New MVP-critical integrations
            if (data.type === 'member_activity') {
                this.updateMemberMap(data.members);
            }
            if (data.type === 'content_distribution') {
                this.updateContentFlow(data.metrics);
            }
        };
    }

    cancelPendingRequests() {
        this.pendingRequests.forEach(controller => controller.abort());
        this.pendingRequests.clear();
    }

    destroy() {
        this.stopStatsPolling();
        this.cancelPendingRequests();
        window.removeEventListener('beforeunload', this._boundBeforeUnload);
        WebSocketAdmin.instance = null;

        // Cleanup metrics
        Object.values(this.metrics).forEach(metric => metric.destroy());
    }

    async checkInitialStatus() {
        try {
            const response = await fetch(sewn_ws_admin.ajax_url, {
                method: 'POST',
                body: new URLSearchParams({
                    action: 'sewn_ws_get_status',
                    nonce: sewn_ws_admin.nonce
                })
            });

            const data = await response.json();
            this.updateServerStatus(data.status);

            // Only start polling if server was already running
            if (data.status === 'Running') {
                this.startStatsPolling();
            }
        } catch (error) {
            console.error('Initial status check failed:', error);
        }
    }
}

// Initialize without auto-starting anything
document.addEventListener('DOMContentLoaded', () => {
    if (!window.wsAdmin) {
        window.wsAdmin = new WebSocketAdmin();
        // Manual server status check instead of auto-polling
        window.wsAdmin.checkInitialStatus();
    }
});