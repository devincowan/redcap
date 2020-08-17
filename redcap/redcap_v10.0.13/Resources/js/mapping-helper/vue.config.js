const fs = require('fs');
 
module.exports = {
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
        // must be server using yarn serve
        // or using the vue ui, but without specifying https: true
        /* https: {
            // ca: fs.readFileSync('/Users/delacqf/code/CERTS/Biondo.pem'),
            key: fs.readFileSync('/Users/delacqf/code/ssl/localhost.key'),
            cert: fs.readFileSync('/Users/delacqf/code/ssl/localhost.crt'),
            // pfx: '',
        } */
    }
}