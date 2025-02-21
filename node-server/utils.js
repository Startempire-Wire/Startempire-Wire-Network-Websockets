const fs = require('fs').promises;
const path = require('path');

/**
 * Ensures that the directory for a file exists, creating it if necessary
 * @param {string} filePath - The path to the file
 * @returns {Promise<void>}
 */
async function ensureDirectoryExists(filePath) {
    const directory = path.dirname(filePath);
    try {
        await fs.access(directory);
    } catch (error) {
        if (error.code === 'ENOENT') {
            await fs.mkdir(directory, { recursive: true });
        } else {
            throw error;
        }
    }
}

module.exports = {
    ensureDirectoryExists
}; 