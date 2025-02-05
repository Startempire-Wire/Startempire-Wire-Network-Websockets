/**
 * Location: node-server/lib/admin-stats.js
 * Dependencies: socket.io, ConnectionManager
 * Classes: AdminStats
 * Purpose: Handles real-time statistics broadcasting to admin interfaces. Tracks active connections,
 * room usage, and system events for monitoring purposes. Integrates with connection manager for live updates.
 */

class AdminStats {
    constructor(io, connectionManager) {
        this.io = io;
        this.connectionManager = connectionManager;
        this.adminSockets = new Set();
    }

    handleAdminConnection(socket) {
        this.adminSockets.add(socket);
        this.sendInitialStats(socket);

        socket.on('disconnect', () => {
            this.adminSockets.delete(socket);
        });
    }

    broadcastStats() {
        const stats = {
            connections: this.connectionManager.getConnectionStats(),
            rooms: this.connectionManager.getRoomStats(),
            events: this.getRecentEvents()
        };

        this.adminSockets.forEach(socket => {
            socket.emit('stats_update', stats);
        });
    }
}

module.exports = AdminStats; 