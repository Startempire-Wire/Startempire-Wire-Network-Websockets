jQuery(document).ready($ => {
    const $installCards = $('.install-method-card');
    const $statusOutput = $('<div class="install-progress"></div>');

    $installCards.after($statusOutput);

    $('.install-action').on('click', function () {
        const $card = $(this).closest('.install-method-card');
        const method = $card.data('method');

        $statusOutput.html(`
            <div class="progress-bar">
                <div class="progress"></div>
            </div>
            <div class="console-output"></div>
        `);

        const xhr = $.post(ajaxurl, {
            action: 'sewn_ws_install_node',
            method: method,
            nonce: sewn_ws_admin.nonce
        }, response => {
            if (response.success) {
                $statusOutput.html(`
                    <div class="notice notice-success">
                        <p>✅ Installation complete!</p>
                        <pre>${response.message}</pre>
                    </div>
                `);
            } else {
                $statusOutput.html(`
                    <div class="notice notice-error">
                        <p>❌ Installation failed</p>
                        <pre>${response.message}</pre>
                    </div>
                `);
            }
        });

        // Real-time output streaming
        xhr.onreadystatechange = () => {
            if (xhr.readyState > 3) return;
            $statusOutput.find('.console-output').append(
                `<div class="console-line">${xhr.responseText}</div>`
            );
        };
    });
}); 