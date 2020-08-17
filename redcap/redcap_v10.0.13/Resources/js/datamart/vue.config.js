const fs = require('fs');
 
module.exports = {
    configureWebpack: {
        devtool: 'source-map'
    },
    devServer: {
        overlay: {
            warnings: false,
            errors: true
        },
        proxy: {
            '/backend': {
                // target: 'https://redcap.test/plugins/router',
                target: 'https://redcap.test/API_PROXY/index.php',
                ws: false,
                changeOrigin: true,
                pathRewrite: {'^/backend': ''}
            },
        },
        /* https: {
            // ca: fs.readFileSync('/Users/delacqf/code/CERTS/Biondo.pem'),
            key: fs.readFileSync('/Users/delacqf/code/ssl/localhost.key'),
            cert: fs.readFileSync('/Users/delacqf/code/ssl/localhost.crt'),
            // pfx: '',
        } */
    }
}