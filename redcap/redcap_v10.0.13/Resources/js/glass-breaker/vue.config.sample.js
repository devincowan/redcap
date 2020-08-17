module.exports = {
    // publicPath: '',
    /* pages: {
        index: {
            // entry for the page
            entry: 'src/main.js',
            // the source template
            template: 'public/index.html',
            // template: 'public/index.html',
            // output as dist/index.html
            filename: 'index.html',
            // when using title option,
            // template title tag needs to be <title><%= htmlWebpackPlugin.options.title %></title>
            title: 'Index Page'+` (${process.env.NODE_ENV})`,
            // chunks to include on this page, by default includes
            // extracted common chunks and vendor chunks.
            chunks: ['chunk-vendors', 'chunk-common', 'index'],
        },
    }, */
    // disable hashes in filenames
    /* filenameHashing: true,
    // delete HTML related webpack plugins
    chainWebpack: config => {
        config.plugins.delete('html')
        config.plugins.delete('preload')
        config.plugins.delete('prefetch')
    }, */
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