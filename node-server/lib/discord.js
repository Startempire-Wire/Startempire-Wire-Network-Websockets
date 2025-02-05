/**
 * Location: node-server/lib/discord.js
 * Dependencies: discord.js, WebRTC
 * Classes: DiscordHandler
 * Purpose: Integrates Discord features including chat synchronization and live streaming.
 * Handles role syncing between WordPress membership tiers and Discord server roles.
 */

// Discord Webhook handler
const { Client, WebhookClient } = require('discord.js');
const RateLimit = require('./rate-limiter');

class DiscordHandler {
    constructor(io, config) {
        this.io = io;
        this.config = config;
        this.setupDiscordClient();
    }

    setupDiscordClient() {
        if (!this.config.discord?.botToken) {
            console.log('Discord integration not configured');
            return;
        }

        this.client = new Client({
            intents: ['GUILDS', 'GUILD_MESSAGES', 'GUILD_MEMBERS']
        });

        this.webhook = new WebhookClient({
            url: this.config.discord.webhookUrl
        });

        this.client.on('ready', () => {
            console.log('Discord client ready');
        });

        this.setupEventHandlers();
    }

    setupEventHandlers() {
        this.io.of('/discord').on('connection', (socket) => {
            this.handleSocketConnection(socket);
        });
    }

    handleSocketConnection(socket) {
        const capabilities = socket.auth?.capabilities || ['connect'];

        socket.on('stream_start', (data) => {
            if (capabilities.includes('stream')) {
                this.handleStreamStart(socket, data);
            }
        });

        socket.on('chat_message', (data) => {
            this.handleChatMessage(socket, data);
        });

        socket.on('presence_update', (data) => {
            this.handlePresenceUpdate(socket, data);
        });
    }

    async handleStreamStart(socket, data) {
        try {
            const { channelId, streamOptions } = data;
            const userId = socket.auth.userId;

            // Create or get voice channel
            const channel = await this.getOrCreateVoiceChannel(channelId);

            // Generate WebRTC signaling data
            const rtcConfig = await this.generateRTCConfig(userId, channel);

            // Start stream with quality based on user tier
            const streamConfig = await this.getStreamConfig(socket.auth.capabilities);

            // Notify Discord about stream start
            await this.webhook.send({
                content: `🎥 Stream started by ${socket.auth.username}`,
                embeds: [{
                    title: data.title || 'New Stream',
                    description: data.description || '',
                    url: `https://discord.com/channels/${channel.guild.id}/${channel.id}`
                }]
            });

            // Emit stream configuration to client
            socket.emit('stream_ready', {
                rtcConfig,
                streamConfig,
                channelId: channel.id
            });

        } catch (error) {
            console.error('Stream start error:', error);
            socket.emit('stream_error', {
                message: 'Failed to start stream',
                code: error.code
            });
        }
    }

    async handleChatMessage(socket, data) {
        try {
            const rateLimit = new RateLimit('chat', socket.auth.capabilities);

            if (!await rateLimit.checkLimit(socket.auth.userId)) {
                throw new Error('Rate limit exceeded');
            }

            // Process message content
            const processedMessage = await this.processMessage(data.content, socket.auth);

            // Send to Discord
            await this.webhook.send({
                content: processedMessage,
                username: socket.auth.username,
                avatarURL: socket.auth.avatar,
                threadId: data.threadId
            });

            // Broadcast to WebSocket clients
            socket.broadcast.to(data.channelId).emit('chat_message', {
                userId: socket.auth.userId,
                username: socket.auth.username,
                content: processedMessage,
                timestamp: Date.now()
            });

        } catch (error) {
            console.error('Chat message error:', error);
            socket.emit('chat_error', {
                message: 'Failed to send message',
                code: error.code
            });
        }
    }

    async handlePresenceUpdate(socket, data) {
        try {
            const { status, activity } = data;
            const userId = socket.auth.userId;

            // Update Discord presence
            await this.client.guilds.cache.forEach(async (guild) => {
                const member = await guild.members.fetch(userId);
                if (member) {
                    await member.setPresence({
                        status,
                        activities: [{
                            name: activity.name,
                            type: activity.type
                        }]
                    });
                }
            });

            // Sync roles if needed
            if (data.roleUpdate) {
                await this.syncRoles(socket.auth);
            }

            // Broadcast presence update
            socket.broadcast.emit('presence_update', {
                userId,
                status,
                activity
            });

        } catch (error) {
            console.error('Presence update error:', error);
            socket.emit('presence_error', {
                message: 'Failed to update presence',
                code: error.code
            });
        }
    }

    // Helper methods
    async getOrCreateVoiceChannel(channelId) {
        // Implementation for voice channel management
        const guild = this.client.guilds.cache.first();
        let channel = guild.channels.cache.get(channelId);

        if (!channel) {
            channel = await guild.channels.create('stream-' + Date.now(), {
                type: 'GUILD_VOICE',
                permissionOverwrites: [
                    {
                        id: guild.id,
                        allow: ['VIEW_CHANNEL'],
                        deny: ['CONNECT']
                    }
                ]
            });
        }

        return channel;
    }

    async generateRTCConfig(userId, channel) {
        // Generate WebRTC configuration
        return {
            iceServers: [
                { urls: process.env.STUN_SERVER },
                {
                    urls: process.env.TURN_SERVER,
                    username: process.env.TURN_USERNAME,
                    credential: process.env.TURN_CREDENTIAL
                }
            ],
            channelId: channel.id,
            userId
        };
    }

    async getStreamConfig(capabilities) {
        // Get stream quality settings based on user capabilities
        const quality = capabilities.includes('premium_stream') ? 'high' : 'standard';

        return {
            quality,
            maxBitrate: quality === 'high' ? 6000000 : 2500000,
            codec: quality === 'high' ? 'h264' : 'vp8'
        };
    }

    async processMessage(content, auth) {
        // Process message content (mentions, emojis, etc)
        return content.replace(/@(\w+)/g, (match, username) => {
            const user = this.client.users.cache.find(u => u.username === username);
            return user ? `<@${user.id}>` : match;
        });
    }

    async syncRoles(auth) {
        // Sync Discord roles with WordPress/MemberPress roles
        const guild = this.client.guilds.cache.first();
        const member = await guild.members.fetch(auth.userId);

        if (member) {
            const roles = await this.getRolesForCapabilities(auth.capabilities);
            await member.roles.set(roles);
        }
    }
}

module.exports = DiscordHandler;