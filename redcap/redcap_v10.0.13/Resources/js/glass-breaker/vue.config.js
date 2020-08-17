module.exports = {
    devServer: {
        overlay: {
            warnings: false,
            errors: true
        },
        proxy: {
            '/api': {
                target: 'https://redcap.test/API_PROXY/index.php',
                ws: false,
                changeOrigin: true,
                pathRewrite: {'^/api': ''}
            },
        },
    }
}