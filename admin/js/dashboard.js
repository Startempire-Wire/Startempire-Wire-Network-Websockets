// Initialize socket connection and charts
let socket;
let connectionChart;
let memoryChart;
let lastStats = {};

// Store previous values for trend calculation
let previousValues = {
    connections: 0,
    messageRate: 0,
    subscribers: 0,
    errors: 0
};

// Initialize Chart.js graphs
function initializeCharts() {
    const connectionCtx = document.getElementById('connection-graph').getContext('2d');
    const memoryCtx = document.getElementById('memory-graph').getContext('2d');

    // Connection history chart
    connectionChart = new Chart(connectionCtx, {
        type: 'line',
        data: {
            labels: Array(20).fill(''),
            datasets: [{
                label: 'Connections',
                data: Array(20).fill(0),
                borderColor: '#3498db',
                backgroundColor: 'rgba(52, 152, 219, 0.1)',
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: { beginAtZero: true },
                x: { display: false }
            },
            animation: {
                duration: 300
            }
        }
    });

    // Memory usage chart
    memoryChart = new Chart(memoryCtx, {
        type: 'line',
        data: {
            labels: Array(20).fill(''),
            datasets: [{
                label: 'Memory (MB)',
                data: Array(20).fill(0),
                borderColor: '#2ecc71',
                backgroundColor: 'rgba(46, 204, 113, 0.1)',
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: { beginAtZero: true },
                x: { display: false }
            },
            animation: {
                duration: 300
            }
        }
    });
}

// Calculate and display trends
function updateTrend(element, currentValue, previousValue) {
    const trendIndicator = element.nextElementSibling;
    if (!trendIndicator) return;

    const diff = currentValue - previousValue;
    if (diff > 0) {
        trendIndicator.innerHTML = '↑';
        trendIndicator.className = 'trend-indicator trend-up';
    } else if (diff < 0) {
        trendIndicator.innerHTML = '↓';
        trendIndicator.className = 'trend-indicator trend-down';
    } else {
        trendIndicator.innerHTML = '';
        trendIndicator.className = 'trend-indicator';
    }
}

// Add shimmer effect when updating values
function addUpdateEffect(element) {
    element.closest('.metric-card').classList.add('updating');
    setTimeout(() => {
        element.closest('.metric-card').classList.remove('updating');
    }, 1000);
}

// Format numbers for display
function formatNumber(num) {
    if (num >= 1000000) {
        return (num / 1000000).toFixed(1) + 'M';
    } else if (num >= 1000) {
        return (num / 1000).toFixed(1) + 'K';
    }
    return num.toString();
}

// Update charts with new data
function updateCharts(stats) {
    // Update connection chart
    connectionChart.data.datasets[0].data.shift();
    connectionChart.data.datasets[0].data.push(stats.connections);
    connectionChart.update('none');

    // Update memory chart
    const memoryMB = Math.round(stats.memory / 1024 / 1024);
    memoryChart.data.datasets[0].data.shift();
    memoryChart.data.datasets[0].data.push(memoryMB);
    memoryChart.update('none');
}

// Update all stats with smooth transitions
function updateStats(stats) {
    // Store current values for trend calculation
    const currentValues = {
        connections: stats.connections || 0,
        messageRate: stats.messageRate || 0,
        subscribers: stats.subscribers || 0,
        errors: stats.errors || 0
    };

    // Update connection count
    const connectionsElement = document.getElementById('live-connections-count');
    if (connectionsElement) {
        addUpdateEffect(connectionsElement);
        connectionsElement.textContent = formatNumber(currentValues.connections);
        updateTrend(connectionsElement, currentValues.connections, previousValues.connections);
    }

    // Update message rate
    const messageRateElement = document.getElementById('message-throughput');
    if (messageRateElement) {
        addUpdateEffect(messageRateElement);
        messageRateElement.textContent = `${formatNumber(currentValues.messageRate)}/s`;
        updateTrend(messageRateElement, currentValues.messageRate, previousValues.messageRate);
    }

    // Update memory usage
    const memoryElement = document.getElementById('memory-usage');
    if (memoryElement) {
        addUpdateEffect(memoryElement);
        const memoryMB = Math.round(stats.memory / 1024 / 1024);
        memoryElement.textContent = `${memoryMB} MB`;
    }

    // Update channel stats
    const channelMessagesElement = document.getElementById('channel-messages');
    if (channelMessagesElement) {
        addUpdateEffect(channelMessagesElement);
        channelMessagesElement.textContent = formatNumber(stats.totalMessages || 0);
    }

    const channelSubscribersElement = document.getElementById('channel-subscribers');
    if (channelSubscribersElement) {
        addUpdateEffect(channelSubscribersElement);
        channelSubscribersElement.textContent = formatNumber(currentValues.subscribers);
        updateTrend(channelSubscribersElement, currentValues.subscribers, previousValues.subscribers);
    }

    const channelErrorsElement = document.getElementById('channel-errors');
    if (channelErrorsElement) {
        addUpdateEffect(channelErrorsElement);
        channelErrorsElement.textContent = formatNumber(currentValues.errors);
        updateTrend(channelErrorsElement, currentValues.errors, previousValues.errors);
    }

    // Update charts
    updateCharts(stats);

    // Store current values for next update
    previousValues = currentValues;
}

// Initialize WebSocket connection
function initializeSocket() {
    const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
    const host = window.location.hostname;
    const port = window.SEWN_WS_PORT || 49200; // Default to IANA Dynamic Port range (49152-65535)

    // Update indicators based on initial server status
    updateIndicators(window.SEWN_WS_INITIAL_STATUS?.status || 'uninitialized');

    socket = io(`${protocol}//${host}:${port}/admin`, {
        transports: ['websocket'],
        upgrade: false
    });

    socket.on('connect', () => {
        console.log('Connected to WebSocket server');
        updateIndicators('running');
    });

    socket.on('disconnect', () => {
        console.log('Disconnected from WebSocket server');
        updateIndicators('stopped');
    });

    socket.on('error', () => {
        console.log('WebSocket connection error');
        updateIndicators('error');
    });

    socket.on('stats', (stats) => {
        updateStats(stats);
        // Update indicators based on server status from stats
        if (stats.status) {
            updateIndicators(stats.status);
        }
    });

    // Request detailed stats every second
    setInterval(() => {
        if (socket.connected) {
            socket.emit('getDetailedStats');
        }
    }, 1000);
}

// Function to update all real-time indicators
function updateIndicators(status) {
    const indicators = document.querySelectorAll('.real-time-indicator');

    // Remove all status classes first
    indicators.forEach(indicator => {
        indicator.classList.remove('running', 'stopped', 'error', 'uninitialized', 'starting');
        indicator.classList.add(status);
    });

    // Update indicator colors based on status
    const colors = {
        running: '#28a745',
        stopped: '#dc3545',
        error: '#dc3545',
        uninitialized: '#6c757d',
        starting: '#ffc107'
    };

    // Apply appropriate animations based on status
    indicators.forEach(indicator => {
        indicator.style.background = colors[status] || colors.uninitialized;

        // Clear any existing animations
        indicator.style.animation = 'none';

        // Add appropriate animation based on status
        if (status === 'running') {
            indicator.style.animation = 'blink 1s infinite';
        } else if (status === 'starting' || status === 'error') {
            indicator.style.animation = 'pulse 1s infinite';
        }
    });
}

// Initialize everything when the page loads
document.addEventListener('DOMContentLoaded', () => {
    initializeCharts();
    initializeSocket();

    // Add control button handlers
    document.querySelectorAll('.sewn-ws-controls button').forEach(button => {
        button.addEventListener('click', (e) => {
            const action = e.target.dataset.action;
            socket.emit('control', { action });

            // Add loading state
            e.target.classList.add('loading');
            setTimeout(() => {
                e.target.classList.remove('loading');
            }, 2000);
        });
    });
}); 