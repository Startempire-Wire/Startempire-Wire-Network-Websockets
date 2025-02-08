/**
 * LOCATION: assets/js/admin.js
 * DEPENDENCIES: WebSocket API, AbortController, fetch API
 * VARIABLES: sewn_ws_admin (localized object), SEWN_WS_SERVER_STATUS_RUNNING
 * CLASSES: WebSocketAdmin (manages server controls)
 * 
 * Central client-side controller for WebSocket server operations. Handles Dashboard interactivity (start/stop/restart commands) while maintaining
 * synchronization with network authentication states. Implements real-time monitoring to support the WebRing content
 * distribution system's low-latency requirements.
 */

// Remove ES6 imports and use regular script includes
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
        this.serverStatus = 'uninitialized'; // Set initial state to uninitialized
        this.socket = null; // Initialize socket as null
        this.hasRefreshed = false;
        this.statsInterval = null;

        this.initializeComponents();
        this.bindEvents();
        this.updateServerStatus('uninitialized'); // Show uninitialized state by default

        // Start initial status check
        this.checkInitialStatus();

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
        this.statsDisplay = {
            update: function (data) {
                // Basic stats display implementation
                const stats = document.getElementById('server-stats');
                if (stats) {
                    stats.innerHTML = `
                        <p>Connections: ${data.connections || 0}</p>
                        <p>Memory Usage: ${data.memory || 0}</p>
                        <p>Errors: ${data.errors || 0}</p>
                    `;
                }
            }
        };

        this.serverControls = {
            startButton: '[data-action="start"]',
            stopButton: '[data-action="stop"]',
            restartButton: '[data-action="restart"]'
        };

        this.logViewer = {
            container: '#log-container',
            levelSelector: '#log-level',
            clearButton: '#clear-logs',
            filterLogs: function () {
                console.log('Filter logs called');
            },
            clearLogs: function () {
                console.log('Clear logs called');
            }
        };

        this.metrics = {
            connections: {
                retention: 3600,  // 1 hour of data
                resolution: 10    // 10 second intervals
            },
            memory: {
                retention: 3600,  // 1 hour of data
                resolution: 10    // 10 second intervals
            },
            errors: {
                retention: 3600,  // 1 hour of data
                resolution: 10    // 10 second intervals
            }
        };
    }

    bindEvents() {
        // Global click handler for action buttons
        document.addEventListener('click', (e) => {
            const button = e.target.closest('[data-action]');
            if (button && button.dataset.action) {
                e.preventDefault();
                this.handleServerAction(button.dataset.action);
            }
        });

        // Check if elements exist before adding listeners
        const logLevel = document.querySelector('#log-level');
        const clearLogs = document.querySelector('#clear-logs');
        const emergencyStop = document.getElementById('emergency-stop');

        // Only add listeners if elements exist
        if (logLevel) {
            logLevel.addEventListener('change', () => this.logViewer.filterLogs());
        }

        if (clearLogs) {
            clearLogs.addEventListener('click', () => this.logViewer.clearLogs());
        }

        if (emergencyStop) {
            emergencyStop.addEventListener('click', () => {
                this.cancelPendingRequests();
            });
        }
    }

    async handleServerAction(action) {
        if (this.pendingRequests.has(action)) {
            console.log('Request already in progress...');
            return;
        }

        const controller = new AbortController();
        this.pendingRequests.set(action, controller);

        const button = document.querySelector(`[data-action="${action}"]`);
        try {
            button.disabled = true;
            this.updateServerStatus('starting');

            console.log(`Sending ${action} request to server...`);

            const response = await fetch(sewn_ws_admin.ajax_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    action: 'sewn_ws_server_control',
                    command: action,
                    nonce: sewn_ws_admin.nonce
                }),
                signal: controller.signal
            });

            console.log('Server response received:', response.status);

            const data = await response.json();
            console.log('Response data:', data);

            if (!response.ok) {
                throw new Error(data.message || `Server request failed with status ${response.status}`);
            }

            if (data.error) {
                throw new Error(data.error);
            }

            // Extract status from the nested response structure
            let serverStatus = 'error';

            if (data.success && data.data) {
                if (data.data.result?.status) {
                    serverStatus = data.data.result.status;
                } else if (data.data.result?.message?.includes('successfully')) {
                    serverStatus = 'running';
                } else if (data.data.status?.running !== undefined) {
                    serverStatus = data.data.status.running ? 'running' : 'stopped';
                } else if (data.data.message?.includes('success')) {
                    serverStatus = 'running';
                }
            }

            console.log('Extracted server status:', serverStatus);
            this.updateServerStatus(serverStatus);

            this.showNotice('success', `Server ${action} successful`);

        } catch (error) {
            console.error('Full error details:', error);
            this.showNotice('error', `Server ${action} failed: ${error.message}`);
            this.updateServerStatus('error');
        } finally {
            this.pendingRequests.delete(action);
            button.disabled = false;
            this.updateButtonStates();
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

        // Add a small delay before starting polling to allow server to fully start
        await new Promise(resolve => setTimeout(resolve, 2000));

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

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();
                console.log('Stats response:', data);

                if (data.success && data.data) {
                    // Check if status is explicitly set
                    if (data.data.status) {
                        this.serverStatus = data.data.status;
                    } else {
                        // Determine status from running flag
                        this.serverStatus = data.data.running ? 'running' : 'stopped';
                    }

                    // Only update UI status if not in a transitional state
                    if (this.serverStatus !== 'starting') {
                        this.updateServerStatus(this.serverStatus);
                    }

                    // Update stats display
                    this.updateStats(data.data);

                    // Reset error count on successful update
                    this.statsErrorCount = 0;
                } else {
                    throw new Error(data.data?.message || 'Invalid response format');
                }
            } catch (error) {
                console.error('Stats polling error:', error);
                this.statsErrorCount++;

                // If we've hit the max error count, stop polling
                if (this.statsErrorCount >= this.statsMaxErrors) {
                    console.error('Max stats errors reached, stopping polling');
                    this.stopStatsPolling();
                    this.showNotice('error', 'Stats updates stopped due to errors');
                }
            }
        };

        // Initial fetch
        await fetchStats();

        // Start polling
        this.statsInterval = setInterval(fetchStats, this.pollingBaseDelay);
    }

    stopStatsPolling() {
        if (this.statsInterval) {
            clearInterval(this.statsInterval);
            this.statsInterval = null;
        }
    }

    updateStats(data) {
        console.log('Updating stats with:', data);

        // Update metrics display with animations
        const elements = {
            connections: document.getElementById('live-connections-count'),
            memory: document.getElementById('memory-usage'),
            messageRate: document.getElementById('message-throughput'),
            channelMessages: document.getElementById('channel-messages'),
            channelSubscribers: document.getElementById('channel-subscribers'),
            channelErrors: document.getElementById('channel-errors')
        };

        // Animate connections update
        if (elements.connections && data.connections !== undefined) {
            this.updateMetricsWithAnimation(elements.connections, data.connections);
        }

        // Update memory with animation and formatting
        if (elements.memory && data.memory_formatted) {
            const memoryMB = data.memory_formatted.heapUsed;
            this.updateMetricsWithAnimation(elements.memory, memoryMB);
            elements.memory.textContent = `${memoryMB.toFixed(2)} MB`;
        }

        // Update message rate with animation
        if (elements.messageRate && data.message_rate) {
            const rate = data.message_rate.in + data.message_rate.out;
            this.updateMetricsWithAnimation(elements.messageRate, rate);
            elements.messageRate.textContent = `${rate.toLocaleString()} msg/s`;
        }

        // Update channel metrics with animations
        if (elements.channelMessages && data.total_messages !== undefined) {
            this.updateMetricsWithAnimation(elements.channelMessages, data.total_messages);
        }

        if (elements.channelSubscribers && data.connections !== undefined) {
            this.updateMetricsWithAnimation(elements.channelSubscribers, data.connections);
        }

        if (elements.channelErrors && data.errors !== undefined) {
            this.updateMetricsWithAnimation(elements.channelErrors, data.errors);
        }

        // Update status classes
        const metricsContainer = document.querySelector('.metrics-grid');
        if (metricsContainer) {
            metricsContainer.classList.toggle('server-running', data.status === 'running');
        }

        // Add visual feedback for updates
        const cards = document.querySelectorAll('.metric-card');
        cards.forEach(card => {
            card.classList.add('updating');
            setTimeout(() => card.classList.remove('updating'), 300);
        });
    }

    updateMetricsWithAnimation(element, newValue, duration = 500) {
        const start = parseInt(element.textContent) || 0;
        const change = newValue - start;
        const startTime = performance.now();

        function animate(currentTime) {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);

            // Easing function for smooth animation
            const easeOut = progress === 1 ? 1 : 1 - Math.pow(2, -10 * progress);
            const current = Math.round(start + (change * easeOut));

            element.textContent = current.toLocaleString();

            if (progress < 1) {
                requestAnimationFrame(animate);
            }
        }

        requestAnimationFrame(animate);
    }

    formatMemory(bytes) {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    formatUptime(seconds) {
        if (seconds === 0) return 'Not running';
        const days = Math.floor(seconds / 86400);
        const hours = Math.floor((seconds % 86400) / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const remainingSeconds = seconds % 60;

        const parts = [];
        if (days > 0) parts.push(`${days}d`);
        if (hours > 0) parts.push(`${hours}h`);
        if (minutes > 0) parts.push(`${minutes}m`);
        if (remainingSeconds > 0) parts.push(`${remainingSeconds}s`);

        return parts.join(' ');
    }

    updateServerStatus(status) {
        console.log('Updating server status to:', status);

        // Don't update if we're in a transitional state and getting a stopped status
        if (this.serverStatus === 'starting' && status === 'stopped') {
            console.log('Ignoring stopped status while starting');
            return;
        }

        // Update internal state
        this.serverStatus = status;

        // Update status indicator
        const statusElement = document.querySelector('.sewn-ws-status');
        const statusText = document.querySelector('.sewn-ws-status .status-text');

        if (statusElement && statusText) {
            // Remove all status classes
            statusElement.classList.remove('running', 'stopped', 'starting', 'error', 'uninitialized');

            // Add new status class
            statusElement.classList.add(status);

            // Update status text
            let displayText = 'Unknown';
            switch (status) {
                case 'running':
                    displayText = 'Running';
                    break;
                case 'stopped':
                    displayText = 'Stopped';
                    break;
                case 'starting':
                    displayText = 'Starting...';
                    break;
                case 'error':
                    displayText = 'Error';
                    break;
                case 'uninitialized':
                    displayText = 'Not Initialized';
                    break;
            }
            statusText.textContent = displayText;
        }

        // Update button states
        this.updateButtonStates();

        // Start or stop stats polling based on status
        if (status === 'running') {
            this.startStatsPolling();
        } else if (status !== 'starting') {
            this.stopStatsPolling();
        }
    }

    initializeWebSocket() {
        // If socket already exists and is connected, don't reinitialize
        if (this.socket?.connected) {
            return;
        }

        try {
            // Get environment info from localized data
            const { dev_mode, site_protocol, port } = sewn_ws_admin;

            // Use HTTP if in dev mode or HTTPS otherwise
            const wsProtocol = dev_mode ? 'ws' : 'wss';
            const httpProtocol = dev_mode ? 'http' : 'https';

            // Configure Socket.IO with error handling
            this.socket = io(`${httpProtocol}://${window.location.hostname}:${port}/admin`, {
                reconnection: true,
                reconnectionDelay: 1000,
                reconnectionDelayMax: 5000,
                reconnectionAttempts: 5,
                transports: dev_mode ? ['polling', 'websocket'] : ['websocket'],
                timeout: 20000, // 20 second timeout
                autoConnect: false // Don't connect automatically
            });

            this.socket.on('connect_error', (error) => {
                console.warn('WebSocket connection error:', error);
                // Don't show alert for connection errors unless explicitly trying to connect
                if (this.serverStatus === 'running') {
                    this.showAlert('WebSocket connection failed. Server might not be running.');
                }
            });

            // Only connect if we're supposed to be running
            if (this.serverStatus === 'running') {
                this.socket.connect();
            }

            this.socket.on('stats_update', (data) => {
                try {
                    this.updateMetrics(data);
                    this.updateTierStats(data.tiers);
                    this.checkThresholds(data);
                } catch (error) {
                    console.error('Error processing stats update:', error);
                }
            });

            this.socket.on('error_alert', (error) => {
                this.handleError(error);
            });

            // Handle reconnection
            this.socket.on('reconnect_attempt', (attempt) => {
                this.updateStatus('reconnecting');
            });

            this.socket.on('reconnect_failed', () => {
                this.updateStatus('disconnected');
                if (this.serverStatus === 'running') {
                    this.showAlert('Connection lost to WebSocket server');
                }
            });

        } catch (error) {
            console.error('Error initializing WebSocket:', error);
            if (this.serverStatus === 'running') {
                this.showAlert('Failed to initialize WebSocket connection');
            }
        }
    }

    updateMetrics(data) {
        // Update metrics display directly
        const connections = document.getElementById('live-connections-count');
        const memory = document.getElementById('memory-usage');
        const messageRate = document.getElementById('message-throughput');
        const channelMessages = document.getElementById('channel-messages');
        const channelSubscribers = document.getElementById('channel-subscribers');
        const channelErrors = document.getElementById('channel-errors');

        if (connections) connections.textContent = data.connections || 0;
        if (memory) memory.textContent = this.formatBytes(data.memory || 0);
        if (messageRate) messageRate.textContent = `${data.message_rate || 0} msg/s`;
        if (channelMessages) channelMessages.textContent = data.total_messages || 0;
        if (channelSubscribers) channelSubscribers.textContent = data.subscribers || 0;
        if (channelErrors) channelErrors.textContent = data.errors || 0;

        // Store metrics history if needed
        Object.entries(this.metrics).forEach(([key, metric]) => {
            if (!metric.history) metric.history = [];
            metric.history.push({
                timestamp: Date.now(),
                value: data[key] || 0
            });

            // Keep only last hour of data (3600 seconds)
            const oneHourAgo = Date.now() - (metric.retention * 1000);
            metric.history = metric.history.filter(item => item.timestamp > oneHourAgo);
        });
    }

    formatBytes(bytes) {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
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

        // Close WebSocket connection if it exists
        if (this.socket) {
            this.socket.close();
            this.socket = null;
        }

        WebSocketAdmin.instance = null;
    }

    async checkInitialStatus() {
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

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();

            if (data.success && data.data) {
                const status = data.data.status?.running ? 'running' : 'stopped';
                this.updateServerStatus(status);
                this.updateStats(data.data);
            } else {
                throw new Error(data.data?.message || 'Invalid response format');
            }
        } catch (error) {
            console.error('Initial status check failed:', error);
            this.updateServerStatus('error');
            this.showNotice('error', 'Failed to get server status');
        }
    }

    updateButtonStates() {
        const startButton = document.querySelector('[data-action="start"]');
        const stopButton = document.querySelector('[data-action="stop"]');
        const restartButton = document.querySelector('[data-action="restart"]');

        if (!startButton || !stopButton || !restartButton) return;

        const isRunning = this.serverStatus === 'running';
        const isStarting = this.serverStatus === 'starting';
        const isStopped = this.serverStatus === 'stopped';
        const isError = this.serverStatus === 'error';

        // Start button
        startButton.disabled = isRunning || isStarting;
        startButton.classList.toggle('running', isRunning);
        startButton.classList.toggle('starting', isStarting);

        // Stop button
        stopButton.disabled = !isRunning;
        stopButton.classList.toggle('running', isRunning);

        // Restart button
        restartButton.disabled = !isRunning || isStarting;
        restartButton.classList.toggle('running', isRunning);
        restartButton.classList.toggle('starting', isStarting);

        // Update button text based on state
        startButton.textContent = isStarting ? 'Starting...' : 'Start Server';
        stopButton.textContent = isRunning ? 'Stop Server' : 'Stop Server';
        restartButton.textContent = isStarting ? 'Restarting...' : 'Restart Server';
    }

    handleError(error) {
        console.error('Server error:', error);

        const errorContainer = document.createElement('div');
        errorContainer.className = 'error-notification';
        errorContainer.innerHTML = `
            <div class="error-content">
                <h4>Error Detected</h4>
                <p>${error.message}</p>
                <small>${new Date(error.timestamp).toLocaleTimeString()}</small>
            </div>
        `;

        document.body.appendChild(errorContainer);
        setTimeout(() => errorContainer.remove(), 5000);
    }
}

// Initialize on DOMContentLoaded
document.addEventListener('DOMContentLoaded', () => {
    if (!window.wsAdmin) {
        window.wsAdmin = new WebSocketAdmin();
    }
});