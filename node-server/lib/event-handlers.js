class EventHandlers {
    constructor(io, stats, rateLimiter, connectionManager) {
        this.io = io;
        this.stats = stats;
        this.rateLimiter = rateLimiter;
        this.connectionManager = connectionManager;
    }

    setupHandlers(socket) {
        // Message handling
        socket.on('message', async (data) => {
            await this.handleMessage(socket, data);
        });

        // Presence events
        socket.on('presence', async (data) => {
            await this.handlePresence(socket, data);
        });

        // Room management
        socket.on('join', async (data) => {
            await this.handleJoin(socket, data);
        });

        socket.on('leave', async (data) => {
            await this.handleLeave(socket, data);
        });

        // Status requests
        socket.on('status', async () => {
            await this.handleStatus(socket);
        });

        // Error handling
        socket.on('error', (error) => {
            this.handleError(socket, error);
        });
    }

    async handleMessage(socket, data) {
        try {
            const userId = socket.user.id;
            const userTier = socket.user.tier || 'free';

            // Check rate limit
            if (!await this.rateLimiter.checkLimit(userId, userTier)) {
                socket.emit('error', { message: 'Rate limit exceeded' });
                return;
            }

            // Validate message format
            if (!this.validateMessage(data)) {
                socket.emit('error', { message: 'Invalid message format' });
                return;
            }

            // Process message based on type
            switch (data.type) {
                case 'chat':
                    await this.handleChatMessage(socket, data);
                    break;
                case 'notification':
                    await this.handleNotification(socket, data);
                    break;
                default:
                    await this.handleBroadcast(socket, data);
            }

            // Track message in stats
            this.stats.trackMessage(userId);

        } catch (error) {
            console.error('Message handling error:', error);
            socket.emit('error', { message: 'Failed to process message' });
            this.stats.trackError(socket.user.id, error);
        }
    }

    async handlePresence(socket, data) {
        try {
            const userId = socket.user.id;

            switch (data.status) {
                case 'active':
                case 'away':
                case 'offline':
                    this.io.emit('presence', {
                        userId,
                        status: data.status,
                        timestamp: Date.now()
                    });
                    break;
                default:
                    socket.emit('error', { message: 'Invalid presence status' });
            }
        } catch (error) {
            console.error('Presence handling error:', error);
            this.stats.trackError(socket.user.id, error);
        }
    }

    async handleJoin(socket, data) {
        try {
            if (!data.room) {
                socket.emit('error', { message: 'Room name required' });
                return;
            }

            const success = this.connectionManager.joinRoom(socket, data.room);
            if (success) {
                socket.emit('joined', { room: data.room });

                // Notify room members
                socket.to(data.room).emit('member_joined', {
                    room: data.room,
                    userId: socket.user.id,
                    timestamp: Date.now()
                });
            }
        } catch (error) {
            console.error('Join handling error:', error);
            this.stats.trackError(socket.user.id, error);
        }
    }

    async handleLeave(socket, data) {
        try {
            if (!data.room) {
                socket.emit('error', { message: 'Room name required' });
                return;
            }

            const success = this.connectionManager.leaveRoom(socket, data.room);
            if (success) {
                socket.emit('left', { room: data.room });

                // Notify room members
                socket.to(data.room).emit('member_left', {
                    room: data.room,
                    userId: socket.user.id,
                    timestamp: Date.now()
                });
            }
        } catch (error) {
            console.error('Leave handling error:', error);
            this.stats.trackError(socket.user.id, error);
        }
    }

    async handleStatus(socket) {
        try {
            const stats = await this.stats.getStats();
            socket.emit('status', {
                ...stats,
                timestamp: Date.now()
            });
        } catch (error) {
            console.error('Status handling error:', error);
            this.stats.trackError(socket.user.id, error);
        }
    }

    handleError(socket, error) {
        console.error('Socket error:', error);
        this.stats.trackError(socket.user.id, error);
    }

    validateMessage(data) {
        return data &&
            typeof data === 'object' &&
            typeof data.content === 'string' &&
            data.content.length > 0 &&
            data.content.length <= 5000; // Maximum message length
    }

    async handleChatMessage(socket, data) {
        if (!data.room) {
            socket.emit('error', { message: 'Room required for chat messages' });
            return;
        }

        this.io.to(data.room).emit('message', {
            type: 'chat',
            content: data.content,
            sender: {
                id: socket.user.id,
                name: socket.user.name
            },
            room: data.room,
            timestamp: Date.now()
        });
    }

    async handleNotification(socket, data) {
        // Handle user-specific notifications
        const targetUsers = data.users || [];
        targetUsers.forEach(userId => {
            this.io.to(`user:${userId}`).emit('notification', {
                content: data.content,
                sender: socket.user.id,
                timestamp: Date.now()
            });
        });
    }

    async handleBroadcast(socket, data) {
        if (!data.room) {
            socket.emit('error', { message: 'Room required for broadcast' });
            return;
        }

        this.io.to(data.room).emit('broadcast', {
            content: data.content,
            sender: socket.user.id,
            room: data.room,
            timestamp: Date.now()
        });
    }
}

module.exports = EventHandlers; 