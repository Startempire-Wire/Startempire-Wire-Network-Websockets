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