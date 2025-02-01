class ConnectionManager {
    constructor(io, stats) {
        this.io = io;
        this.stats = stats;
        this.connections = new Map();
        this.rooms = new Map();
    }

    handleConnection(socket) {
        const userId = socket.user.id;
        const userTier = socket.user.tier || 'free';

        // Store connection details
        this.connections.set(socket.id, {
            userId,
            userTier,
            connectedAt: Date.now(),
            rooms: new Set()
        });

        // Join user-specific room
        socket.join(`user:${userId}`);

        // Track connection in stats
        this.stats.trackConnection(userId);

        // Emit presence event
        this.io.emit('presence', {
            event: 'join',
            userId,
            timestamp: Date.now()
        });

        console.log(`User ${userId} connected`);
    }

    handleDisconnection(socket) {
        const connection = this.connections.get(socket.id);
        if (!connection) return;

        const { userId, rooms } = connection;

        // Leave all rooms
        rooms.forEach(room => {
            this.leaveRoom(socket, room);
        });

        // Remove from connections
        this.connections.delete(socket.id);

        // Track disconnection in stats
        this.stats.trackDisconnection(userId);

        // Emit presence event
        this.io.emit('presence', {
            event: 'leave',
            userId,
            timestamp: Date.now()
        });

        console.log(`User ${userId} disconnected`);
    }

    joinRoom(socket, room) {
        const connection = this.connections.get(socket.id);
        if (!connection) return false;

        socket.join(room);
        connection.rooms.add(room);

        // Track room membership
        if (!this.rooms.has(room)) {
            this.rooms.set(room, new Set());
        }
        this.rooms.get(room).add(socket.id);

        return true;
    }

    leaveRoom(socket, room) {
        const connection = this.connections.get(socket.id);
        if (!connection) return false;

        socket.leave(room);
        connection.rooms.delete(room);

        // Update room membership
        const roomMembers = this.rooms.get(room);
        if (roomMembers) {
            roomMembers.delete(socket.id);
            if (roomMembers.size === 0) {
                this.rooms.delete(room);
            }
        }

        return true;
    }

    getRoomMembers(room) {
        const members = this.rooms.get(room);
        if (!members) return [];

        return Array.from(members)
            .map(socketId => {
                const connection = this.connections.get(socketId);
                return connection ? connection.userId : null;
            })
            .filter(userId => userId !== null);
    }

    getUserConnections(userId) {
        return Array.from(this.connections.entries())
            .filter(([_, connection]) => connection.userId === userId)
            .map(([socketId]) => socketId);
    }

    getConnectionStats() {
        return {
            totalConnections: this.connections.size,
            totalRooms: this.rooms.size,
            connections: Array.from(this.connections.entries()).map(([socketId, connection]) => ({
                socketId,
                userId: connection.userId,
                connectedAt: connection.connectedAt,
                rooms: Array.from(connection.rooms)
            }))
        };
    }
}

module.exports = ConnectionManager; 