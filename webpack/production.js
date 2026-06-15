module.exports = {
	devtool: 'hidden-source-map',
	optimization: {
		splitChunks: {
			cacheGroups: {
				// Name vendor splits after their npm package instead of the
				// opaque, very long auto-generated ids (e.g.
				// `vendors-node_modules_wordpress_dataviews_...`). Deriving the
				// name from the package directory keeps it short and readable
				// without maintaining a per-dependency list. Packages below
				// splitChunks' default minSize stay inlined, so this doesn't
				// fragment the output into many tiny files.
				vendor: {
					test: /[\\/]node_modules[\\/]/,
					chunks: 'all',
					name( module ) {
						const pkg = module.context.match(
							/[\\/]node_modules[\\/](@[^\\/]+[\\/][^\\/]+|[^\\/]+)/
						);

						return pkg
							? 'vendor-' +
									pkg[ 1 ]
										.replace( '@', '' )
										.replace( /[\\/]/g, '-' )
							: 'vendor';
					},
				},
			},
		},
	},
};
