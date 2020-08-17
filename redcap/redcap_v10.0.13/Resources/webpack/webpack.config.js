const path = require('path');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const CopyWebpackPlugin = require('copy-webpack-plugin');

module.exports = {
  entry: {
    main: ["babel-polyfill", './src/app.js']
  },
  output: {
    filename: 'bundle.js',
    path: path.resolve(__dirname, './js')
  },
  plugins: [
    new MiniCssExtractPlugin({
      // Options similar to the same options in webpackOptions.output
      // both options are optional
      filename: '../css/bundle.css',
      chunkFilename: '../css/bundle.css',
    }),
    // Copy various files from packages into Resources/webpack/ subdirectories
    new CopyWebpackPlugin([
      // FontAwesome CSS and Webfonts
      {
        from: 'node_modules/@fortawesome/fontawesome-free/css/all.min.css',
        to: '../css/fontawesome/css/all.min.css',
        toType: 'file'
      },
      {
        from: 'node_modules/@fortawesome/fontawesome-free/webfonts/',
        to: '../css/fontawesome/webfonts/'
      },
      // TinyMCE
      {
        from: 'node_modules/tinymce/',
        to: '../css/tinymce/',
        ignore: ['tinymce.js', 'jquery.tinymce.js', 'jquery.tinymce.min.js', 'bower.json', 'changelog.txt', 'composer.json', 'package.json', 'readme.md']
      }
    ])
  ],
  module: {
    rules: [
      {
        test: /\.js$/,
        exclude: /node_modules/,
        use: {
          loader: "babel-loader"
        }
      },
	  {
		test: require.resolve('jquery'),
		use: [{
		  loader: 'expose-loader',
		  options: 'jQuery'
		},{
		  loader: 'expose-loader',
		  options: '$'
		}]
	  },
      {
        test: /\.(css|sass|scss)$/,
        use: [
          MiniCssExtractPlugin.loader,
          {
            loader: 'css-loader',
            options: {
              importLoaders: 2,
              sourceMap: true
            }
          },
          {
            loader: 'postcss-loader',
            options: {
              plugins: () => [
                require('autoprefixer')
              ],
              sourceMap: true
            }
          },
          {
            loader: 'sass-loader',
            options: {
              sourceMap: true
            }
          }
        ]
      },
      {
        test: /\.(jpe?g|png|gif)$/i,
        loader:"file-loader",
        options:{
          name:'[name].[ext]',
          outputPath: '../images/',
          publicPath: 'Resources/webpack/images/'
        }
      },
      {
        test: require.resolve('sweetalert2'),
        use: [{
          loader: 'expose-loader',
          options: 'Swal'
        }]
      }
    ]
  }
};