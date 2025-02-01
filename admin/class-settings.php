<?php

add_action('admin_init', function() {
    register_setting('sewn_ws_options', 'sewn_ws_port');
    
    add_settings_section(
        'server_config',
        __('Server Configuration', 'sewn-ws'),
        null,
        'sewn-ws-settings'
    );

    add_settings_field(
        'ws_port',
        __('WebSocket Port', 'sewn-ws'),
        function() {
            $port = get_option('sewn_ws_port', 8080);
            echo "<input name='sewn_ws_port' value='$port'>";
        },
        'sewn-ws-settings',
        'server_config'
    );
});

add_action('admin_menu', 'sewn_ws_register_menu', 20);

function sewn_ws_register_menu() {
    error_log('[SEWN] Admin menu hook triggered');
    
    // Verify capability first
    if (!current_user_can('manage_options')) {
        error_log('[SEWN] User lacks manage_options capability');
        return;
    }

    error_log('[SEWN] Attempting to register main menu');
    
    add_menu_page(
        __('WebSocket Server', 'sewn-ws'),
        __('WebSocket', 'sewn-ws'),
        'manage_options',
        'sewn-ws-dashboard',
        'sewn_ws_render_dashboard',
        'dashicons-networking',
        80
    );
    
    error_log('[SEWN] Main menu registered');
    
    add_submenu_page(
        'sewn-ws-dashboard',
        __('Settings', 'sewn-ws'),
        __('Settings', 'sewn-ws'),
        'manage_options',
        'sewn-ws-settings',
        'sewn_ws_render_settings'
    );
    
    error_log('[SEWN] Submenu items registered');
}

function sewn_ws_render_dashboard() {
    error_log('[SEWN] Rendering dashboard');
    // Load dashboard view
    include plugin_dir_path(__FILE__) . '../admin/views/dashboard.php';
}

function sewn_ws_render_settings() {
    error_log('[SEWN] Rendering settings');
    ?>
    <div class="wrap">
        <h1>WebSocket Server Settings</h1>
        <form method="post" action="options.php">
            <?php 
            settings_fields('sewn_ws_options');
            do_settings_sections('sewn-ws-settings');
            submit_button(); 
            ?>
        </form>
    </div>
    <?php
}
