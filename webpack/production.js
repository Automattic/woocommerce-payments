const ChunkEntriesPlugin = require( './chunk-entries-plugin' );

module.exports = {
	devtool: 'hidden-source-map',
	// The JS strings for the POT come from a Babel plugin, which skips modules
	// webpack restores from cache. A cached rebuild would ship a POT without them.
	cache: false,
	plugins: [
		new ChunkEntriesPlugin( { filename: 'i18n-chunk-entries.json' } ),
	],
};
