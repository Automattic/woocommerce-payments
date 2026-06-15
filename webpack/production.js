module.exports = {
	devtool: 'hidden-source-map',
	optimization: {
		splitChunks: {
			cacheGroups: {
				// Short, readable names for the big vendor splits instead of
				// opaque numeric ids (chunks/4183.js). Everything else keeps
				// the default numeric naming, which stays short.
				datepicker: {
					test: /[\\/]node_modules[\\/](react-day-picker|@date-fns|date-fns)[\\/]/,
					name: 'vendor-datepicker',
					chunks: 'all',
				},
				dataviews: {
					test: /[\\/]node_modules[\\/]@wordpress[\\/]dataviews[\\/]/,
					name: 'vendor-dataviews',
					chunks: 'all',
				},
				stripe: {
					test: /[\\/]node_modules[\\/]@stripe[\\/]/,
					name: 'vendor-stripe',
					chunks: 'all',
				},
			},
		},
	},
};
