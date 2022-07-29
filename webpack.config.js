/* eslint-disable */
const { merge } = require( 'webpack-merge' );

const shared = require( './webpack/shared' );
const production = require( './webpack/production' );
const development = require( './webpack/development' );

const mode = process.env.NODE_ENV || 'development';

module.exports = () => {
	if ( mode === 'development' ) {
		return merge( { mode }, shared, development );
	}

	return merge( { mode }, shared, production );
};
