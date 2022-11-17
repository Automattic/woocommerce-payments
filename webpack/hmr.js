const ReactRefreshWebpackPlugin = require( '@pmmmwh/react-refresh-webpack-plugin' );

const babelLoader = {
	loader: 'babel-loader',
	options: {
		plugins: [ 'react-refresh/babel' ],
	},
};

module.exports = {
	optimization: {
		runtimeChunk: 'single',
	},
	module: {
		rules: [
			{
				test: /\.tsx?$/,
				use: [ babelLoader, 'ts-loader' ],
				exclude: /node_modules/,
			},
			{
				test: /\.jsx?$/,
				use: [ babelLoader ],
				exclude: /node_modules/,
			},
		],
	},
	plugins: [ new ReactRefreshWebpackPlugin() ],
	devServer: {
		hot: true,
		host: 'localhost',
		allowedHosts: 'all',
		server: 'https',
		devMiddleware: {
			writeToDisk: true,
		},
		client: {
			overlay: {
				errors: true,
				warnings: false,
			},
		},
	},
};
