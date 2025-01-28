module.exports = {
    PORT: process.env.SEWN_WS_PORT || 8080,
    HOST: process.env.SEWN_WS_HOST || 'localhost',
    JWT_SECRET: process.env.SEWN_WS_JWT_SECRET
};
