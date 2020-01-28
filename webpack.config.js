/* eslint-disable */
const path = require( 'path' );
var NODE_ENV = process.env.NODE_ENV || 'development';
const MiniCssExtractPlugin = require( 'mini-css-extract-plugin' );
const WordPressExternalDependenciesPlugin = require( '@wordpress/dependency-extraction-webpack-plugin' );

const webpackConfig = {
	mode: NODE_ENV,
	devtool: 'source-map',
	entry: {
		index: './client/index.js',
		settings: './client/settings.js',
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
						query: {
							includePaths: [ 'client/stylesheets/abstracts' ],
							data:
								'@import "node_modules/@wordpress/base-styles/_colors.scss"; ' +
								'@import "node_modules/@wordpress/base-styles/_variables.scss"; ' +
								'@import "node_modules/@wordpress/base-styles/_mixins.scss"; ' +
								'@import "node_modules/@wordpress/base-styles/_breakpoints.scss"; ' +
								'@import "node_modules/@wordpress/base-styles/_animations.scss"; ' +
								'@import "node_modules/@wordpress/base-styles/_z-index.scss"; ' +
								'@import "_colors"; ' +
								'@import "_breakpoints"; ' +
								'@import "_mixins"; ',
						},
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
				switch ( request ) {
					case '@woocommerce/components':
						return [ 'wc', 'components' ];
					case '@woocommerce/currency':
						return [ 'wc', 'currency' ];
				}
			},
			requestToHandle( request ) {
				switch ( request ) {
					case '@woocommerce/components':
						return 'wc-components';
					case '@woocommerce/currency':
						return 'wc-currency';
				}
			},
		} ),
	],
};

if ( webpackConfig.mode !== 'production' ) {
	webpackConfig.devtool = process.env.SOURCEMAP || 'source-map';
}

module.exports = webpackConfig;
