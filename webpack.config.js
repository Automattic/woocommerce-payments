/* eslint-disable */
const path = require( 'path' );
var NODE_ENV = process.env.NODE_ENV || 'development';
const MiniCssExtractPlugin = require( 'mini-css-extract-plugin' );
const WordPressExternalDependenciesPlugin = require( '@wordpress/dependency-extraction-webpack-plugin' );

const webpackConfig = {
	mode: NODE_ENV,
	entry: {
		index: './client/index.js',
	},
	output: {
		filename: '[name].js',
		path: path.resolve( 'dist' ),
		libraryTarget: 'this',
	},
	module: {
		rules: [
			{
				test: /\.jsx?$/,
				loader: 'babel-loader',
				exclude: /node_modules/
			},
			{
				test: /\.(scss|css)$/,
				use: [
					MiniCssExtractPlugin.loader,
					'css-loader',
					{
						loader: 'sass-loader',
					},
				],
			},
		],
	},
	resolve: {
		extensions: [ '.json', '.js', '.jsx' ],
		modules: [ path.join( __dirname, 'client' ), 'node_modules' ],
	},
	plugins: [
		new MiniCssExtractPlugin( 'css/[name].css' ),
		new WordPressExternalDependenciesPlugin( {
			injectPolyfill: true,
			requestToExternal( request ) {
				const match = request.match( /@woocommerce\/(.+)/ );
				if ( match ) {
					return [ 'wc', match[ 1 ] ];
				}
			},
			requestToHandle( request ) {
				const match = request.match( /@woocommerce\/(.+)/ );
				if ( match ) {
					return `wc-${ match[ 1 ] }`;
				}
			},
		} ),
	],
};

if ( webpackConfig.mode !== 'production' ) {
	webpackConfig.devtool = process.env.SOURCEMAP || 'source-map';
}

module.exports = webpackConfig;