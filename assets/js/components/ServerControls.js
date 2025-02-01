class ServerControls {
    constructor() {
        // Use existing buttons from dashboard.php
        this.buttons = {
            start: document.querySelector('[data-action="start"]'),
            stop: document.querySelector('[data-action="stop"]'),
            restart: document.querySelector('[data-action="restart"]')
        };

        this.bindEvents();
    }

    bindEvents() {
        Object.entries(this.buttons).forEach(([action, button]) => {
            button?.addEventListener('click', () => this.handleAction(action));
        });
    }

    async handleAction(action) {
        const button = this.buttons[action];
        if (!button) return;

        try {
            button.disabled = true;
            button.textContent = '...';

            const response = await fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'sewn_ws_server_control',
                    command: action,
                    nonce: sewn_ws_admin.nonce
                })
            });

            const result = await response.json();

            if (result.success) {
                this.updateStatus(action === 'stop' ? 'stopped' : 'running');
            } else {
                throw new Error(result.data.message);
            }
        } catch (error) {
            console.error(error);
            alert(`Server ${action} failed: ${error.message}`);
        } finally {
            button.disabled = false;
            button.textContent = action.charAt(0).toUpperCase() + action.slice(1);
        }
    }

    updateStatus(status) {
        const statusEl = document.querySelector('.sewn-ws-status');
        if (statusEl) {
            statusEl.className = `sewn-ws-status ${status}`;
            statusEl.querySelector('.status-text').textContent =
                status.charAt(0).toUpperCase() + status.slice(1);
        }

        // Update button states
        this.buttons.start.disabled = status === 'running';
        this.buttons.stop.disabled = status === 'stopped';
        this.buttons.restart.disabled = status === 'stopped';
    }
} 
} 