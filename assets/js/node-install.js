document.querySelectorAll('[data-install-method]').forEach(btn => {
    btn.addEventListener('click', async (e) => {
        const method = e.target.dataset.installMethod;
        const output = document.querySelector('.install-log');
        const container = document.querySelector('.install-output');

        container.style.display = 'block';
        output.textContent = 'Starting installation...\n';

        try {
            const response = await fetch(sewn_ws_admin.ajax_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `action=sewn_ws_install_node&method=${method}&nonce=${sewn_ws_admin.nonce}`
            });

            const data = await response.json();
            output.textContent += data.message;

            if (data.success) {
                location.reload(); // Refresh to detect new install
            }
        } catch (error) {
            output.textContent += `Error: ${error.message}`;
        }
    });
}); 