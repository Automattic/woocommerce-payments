/* eslint-disable */
const { mergeWithRules } = require( 'webpack-merge' );

const shared = require( './webpack/shared' );
const production = require( './webpack/production' );
const development = require( './webpack/development' );
const hmr = require( './webpack/hmr' );
const modules = require( './webpack/modules' );

const mode = process.env.NODE_ENV || 'development';

const merge = mergeWithRules( {
	module: {
		rules: {
			test: 'match',
			use: 'replace',
		},
	},
} );

// The script modules build into their own folder, which the main build's clean step keeps.
const keepModules = {
	output: { clean: { keep: new RegExp( `^${ modules.OUTPUT_DIR }/` ) } },
};

module.exports = ( { WEBPACK_SERVE } ) => {
	if ( mode === 'development' ) {
		const main = merge(
			{ mode },
			shared,
			development,
			!! WEBPACK_SERVE && hmr,
			keepModules
		);
		// The dev server serves one build, so it only runs the main one.
		return WEBPACK_SERVE ? main : [ main, modules( mode ) ];
	}

	return [
		merge( { mode }, shared, production, keepModules ),
		modules( mode ),
	];
};
