/**
 * LOCATION: assets/js/components/LogViewer.js
 * DEPENDENCIES: WebSocket connection, DOM API
 * VARIABLES: sewn_ws_admin.logsEndpoint
 * CLASSES: LogViewer (real-time log manager)
 * 
 * Provides real-time monitoring of server logs with level-based filtering. Essential for debugging network
 * synchronization issues and monitoring WebRing content distribution patterns. Integrates with membership
 * system to enforce log access permissions.
 */

class LogViewer {
    constructor(containerSelector, options) {
        this.container = document.querySelector(containerSelector);
        this.levelSelector = document.querySelector(options.levelSelector);
        this.clearButton = document.querySelector(options.clearButton);

        this.state = {
            logs: [],
            currentLevel: 'all',
            maxLogs: 1000
        };

        this.bindEvents();
        this.startLogPolling();
    }

    bindEvents() {
        this.levelSelector.addEventListener('change', (e) => {
            this.setLevel(e.target.value);
        });

        this.clearButton.addEventListener('click', () => {
            this.clear();
        });
    }

    setLevel(level) {
        this.state.currentLevel = level;
        this.render();
    }

    clear() {
        this.state.logs = [];
        this.render();
    }

    async startLogPolling() {
        setInterval(async () => {
            await this.fetchLogs();
        }, 2000);
    }

    async fetchLogs() {
        try {
            const response = await fetch(sewn_ws_admin.ajax_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    action: 'sewn_ws_get_logs',
                    nonce: sewn_ws_admin.nonce
                })
            });

            const data = await response.json();

            if (data.success) {
                this.addLogs(data.logs);
            }
        } catch (error) {
            console.error('Failed to fetch logs:', error);
        }
    }

    addLogs(newLogs) {
        this.state.logs = [...this.state.logs, ...newLogs]
            .slice(-this.state.maxLogs);
        this.render();
    }

    render() {
        const filteredLogs = this.filterLogs();
        const html = filteredLogs.map(log => this.formatLogEntry(log)).join('\n');

        this.container.innerHTML = html;
        this.container.scrollTop = this.container.scrollHeight;
    }

    filterLogs() {
        if (this.state.currentLevel === 'all') {
            return this.state.logs;
        }
        return this.state.logs.filter(log => log.level === this.state.currentLevel);
    }

    formatLogEntry(log) {
        const timestamp = new Date(log.timestamp).toLocaleTimeString();
        const levelClass = `log-${log.level.toLowerCase()}`;

        return `
            <div class="log-entry ${levelClass}">
                <span class="log-timestamp">[${timestamp}]</span>
                <span class="log-level">[${log.level}]</span>
                <span class="log-message">${this.escapeHtml(log.message)}</span>
            </div>
        `;
    }

    escapeHtml(unsafe) {
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
} 