const ChunkEntriesPlugin = require( './chunk-entries-plugin' );

module.exports = {
	devtool: 'hidden-source-map',
	plugins: [
		new ChunkEntriesPlugin( { filename: 'i18n-chunk-entries.json' } ),
	],
};
