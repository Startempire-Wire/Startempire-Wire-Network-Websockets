/**
 * Location: node-server/role-sync.js
 * Dependencies: WebSocket server, WordPress roles
 * Variables/Classes: io, logRoleChange
 * Purpose: Synchronizes membership tier permissions across active WebSocket connections in real-time. Propagates role updates through the network while maintaining connection-specific access controls.
 */

io.on('roleUpdate', (updatedRole) => {
    const connections = io.sockets.sockets.values();

    for (const socket of connections) {
        if (socket.user.tier === updatedRole.tier) {
            socket.emit('roleRefresh', {
                newPermissions: updatedRole.permissions
            });
        }
    }

    logRoleChange(updatedRole);
}); 