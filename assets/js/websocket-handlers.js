/**
 * WebSocket Event Handlers
 * 
 * @package Startempire_Wire_Network_Websockets
 */

class WebSocketHandlers {
    /**
     * Constructor
     * 
     * @param {WebSocket} socket WebSocket connection instance
     */
    constructor(socket) {
        this.socket = socket;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 5;
        this.reconnectDelay = 1000;
        this.events = new Map();

        this.bindEvents();
    }

    /**
     * Bind WebSocket events
     */
    bindEvents() {
        // Connection events
        this.socket.addEventListener('open', () => this.handleOpen());
        this.socket.addEventListener('close', () => this.handleClose());
        this.socket.addEventListener('error', (error) => this.handleError(error));
        this.socket.addEventListener('message', (event) => this.handleMessage(event));

        // Custom events
        this.on('stats', (data) => this.handleStats(data));
        this.on('log', (data) => this.handleLog(data));
        this.on('alert', (data) => this.handleAlert(data));
    }

    /**
     * Handle WebSocket open
     */
    handleOpen() {
        console.log('WebSocket connected');
        this.reconnectAttempts = 0;
        this.emit('ready', { timestamp: Date.now() });

        // Request initial stats
        this.requestStats();
    }

    /**
     * Handle WebSocket close
     */
    handleClose() {
        console.log('WebSocket disconnected');

        if (this.reconnectAttempts < this.maxReconnectAttempts) {
            setTimeout(() => this.reconnect(), this.getReconnectDelay());
        } else {
            this.handleFatalError('Maximum reconnection attempts reached');
        }
    }

    /**
     * Handle WebSocket error
     * 
     * @param {Event} error Error event
     */
    handleError(error) {
        console.error('WebSocket error:', error);
        this.emit('error', {
            message: error.message || 'Unknown error',
            timestamp: Date.now()
        });
    }

    /**
     * Handle incoming messages
     * 
     * @param {MessageEvent} event Message event
     */
    handleMessage(event) {
        try {
            const data = JSON.parse(event.data);

            if (data.type && this.events.has(data.type)) {
                this.events.get(data.type).forEach(callback => callback(data.payload));
            }
        } catch (error) {
            console.error('Failed to parse message:', error);
        }
    }

    /**
     * Handle stats update
     * 
     * @param {Object} stats Server statistics
     */
    handleStats(stats) {
        // Update dashboard displays
        if (window.sewn && window.sewn.dashboard) {
            window.sewn.dashboard.updateStats(stats);
        }

        // Schedule next stats update
        setTimeout(() => this.requestStats(), 5000);
    }

    /**
     * Handle log message
     * 
     * @param {Object} log Log data
     */
    handleLog(log) {
        if (window.sewn && window.sewn.logViewer) {
            window.sewn.logViewer.appendLog(log);
        }
    }

    /**
     * Handle alert message
     * 
     * @param {Object} alert Alert data
     */
    handleAlert(alert) {
        // Show admin notice
        if (window.sewn && window.sewn.adminNotices) {
            window.sewn.adminNotices.showAlert(alert);
        }
    }

    /**
     * Register event handler
     * 
     * @param {string} type Event type
     * @param {Function} callback Event callback
     */
    on(type, callback) {
        if (!this.events.has(type)) {
            this.events.set(type, new Set());
        }
        this.events.get(type).add(callback);
    }

    /**
     * Emit event
     * 
     * @param {string} type Event type
     * @param {*} payload Event payload
     */
    emit(type, payload) {
        if (this.socket.readyState === WebSocket.OPEN) {
            this.socket.send(JSON.stringify({ type, payload }));
        }
    }

    /**
     * Request server stats
     */
    requestStats() {
        this.emit('stats_request', { timestamp: Date.now() });
    }

    /**
     * Attempt to reconnect
     */
    reconnect() {
        this.reconnectAttempts++;
        console.log(`Reconnection attempt ${this.reconnectAttempts}/${this.maxReconnectAttempts}`);

        // Create new WebSocket connection
        const newSocket = new WebSocket(this.socket.url);
        this.socket = newSocket;
        this.bindEvents();
    }

    /**
     * Get reconnection delay with exponential backoff
     * 
     * @return {number} Delay in milliseconds
     */
    getReconnectDelay() {
        return Math.min(1000 * Math.pow(2, this.reconnectAttempts), 30000);
    }

    /**
     * Handle fatal error
     * 
     * @param {string} message Error message
     */
    handleFatalError(message) {
        console.error('Fatal WebSocket error:', message);

        if (window.sewn && window.sewn.adminNotices) {
            window.sewn.adminNotices.showError({
                message,
                persistent: true
            });
        }
    }
}

// Export for use in other modules
export default WebSocketHandlers; 