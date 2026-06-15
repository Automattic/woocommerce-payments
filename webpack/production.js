module.exports = {
	devtool: 'hidden-source-map',
	optimization: {
		// Give synthesized shared/vendor chunks readable, path-based names
		// instead of opaque numeric ids (chunks/4183.js).
		chunkIds: 'named',
	},
};
