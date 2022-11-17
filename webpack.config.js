/* eslint-disable */
const { mergeWithRules } = require( 'webpack-merge' );

const shared = require( './webpack/shared' );
const production = require( './webpack/production' );
const development = require( './webpack/development' );
const hmr = require( './webpack/hmr' );

const mode = process.env.NODE_ENV || 'development';

const merge = mergeWithRules( {
	module: {
		rules: {
			test: 'match',
			use: 'replace',
		},
	},
} );

module.exports = ( { WEBPACK_SERVE } ) => {
	if ( mode === 'development' ) {
		return merge( { mode }, shared, development, !! WEBPACK_SERVE && hmr );
	}

	return merge( { mode }, shared, production );
};
