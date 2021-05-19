/* eslint-disable */
const path = require( 'path' );
var NODE_ENV = process.env.NODE_ENV || 'development';
const MiniCssExtractPlugin = require( 'mini-css-extract-plugin' );
const WordPressExternalDependenciesPlugin = require( '@wordpress/dependency-extraction-webpack-plugin' );

const webpackConfig = {
	mode: NODE_ENV,
	devtool: process.env.SOURCEMAP === 'none' ? undefined : 'source-map',
	entry: {
		index: './client/index.js',
		settings: './client/settings/index.js',
		'blocks-checkout': './client/checkout/blocks/index.js',
		checkout: './client/checkout/classic/index.js',
		'payment-request': './client/payment-request/index.js',
		'subscription-edit-page': './client/subscription-edit-page.js',
		tos: './client/tos/index.js',
		'additional-methods-setup':
			'./client/additional-methods-setup/index.js',
	},
	output: {
		filename: '[name].js',
		path: path.resolve( 'dist' ),
	},
	module: {
		rules: [
			{
				test: /\.(t|j)sx?$/,
				use: [ 'ts-loader', 'babel-loader' ],
				exclude: /node_modules/,
			},
			{
				test: /\.(scss|css)$/,
				use: [
					MiniCssExtractPlugin.loader,
					'css-loader',
					{
						loader: 'sass-loader',
						options: {
							sassOptions: {
								includePaths: [
									'client/stylesheets/abstracts',
								],
							},
							additionalData:
								'@import "node_modules/@wordpress/base-styles/_colors.scss"; ' +
								'@import "node_modules/@wordpress/base-styles/_colors.native.scss"; ' +
								'@import "node_modules/@wordpress/base-styles/_variables.scss"; ' +
								'@import "node_modules/@wordpress/base-styles/_mixins.scss"; ' +
								'@import "node_modules/@wordpress/base-styles/_breakpoints.scss"; ' +
								'@import "node_modules/@wordpress/base-styles/_animations.scss"; ' +
								'@import "node_modules/@wordpress/base-styles/_z-index.scss"; ' +
								'@import "_colors"; ' +
								'@import "_breakpoints"; ' +
								'@import "_mixins"; ' +
								'@import "_variables"; ',
						},
					},
				],
			},
			{
				enforce: 'pre',
				test: /\.js$/,
				exclude: /node_modules/,
				loader: 'source-map-loader',
			},
			{
				test: /\.(svg|png)$/,
				exclude: /node_modules/,
				loader: 'url-loader',
			},
		],
	},
	resolve: {
		extensions: [ '.ts', '.tsx', '.json', '.js', '.jsx' ],
		modules: [ path.join( __dirname, 'client' ), 'node_modules' ],
	},
	plugins: [
		new MiniCssExtractPlugin( { filename: '[name].css' } ),
		new WordPressExternalDependenciesPlugin( {
			injectPolyfill: true,
			requestToExternal( request ) {
				switch ( request ) {
					case '@wordpress/components':
						return null;
					case '@woocommerce/components':
						return [ 'wc', 'components' ];
					case '@woocommerce/currency':
						return [ 'wc', 'currency' ];
					case '@woocommerce/navigation':
						return [ 'wc', 'navigation' ];
					case '@woocommerce/blocks-registry':
						return [ 'wc', 'wcBlocksRegistry' ];
					case 'wp-mediaelement':
						return [ 'wp', 'mediaelement' ];
				}
			},
			requestToHandle( request ) {
				switch ( request ) {
					case '@wordpress/components':
						return null;
					case '@woocommerce/components':
						return 'wc-components';
					case '@woocommerce/currency':
						return 'wc-currency';
					case '@woocommerce/navigation':
						return 'wc-navigation';
					case '@woocommerce/blocks-registry':
						return 'wc-blocks-registry';
					case 'wp-mediaelement':
						return 'wp-mediaelement';
				}
			},
		} ),
	],
};

module.exports = webpackConfig;
