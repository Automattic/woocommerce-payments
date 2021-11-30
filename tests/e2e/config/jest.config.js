const path = require( 'path' );
const { config } = require( 'dotenv' );
const { jestConfig } = require( '@automattic/puppeteer-utils' );

config( { path: path.resolve( __dirname, '.env' ) } );
config( { path: path.resolve( __dirname, 'local.env' ) } );

// Define paths to look for E2E tests.
const e2ePaths = {
	wcpay: path.resolve( __dirname, '../specs/wcpay' ),
	subscriptions: path.resolve( __dirname, '../specs/subscriptions' ),
	blocks: path.resolve( __dirname, '../specs/blocks' ),
};

// Allow E2E tests to run specific tests - wcpay, subscriptions, blocks, all (default).
const allowedPaths = [];
if ( process.env.E2E_GROUP ) {
	if ( process.env.E2E_BRANCH ) {
		allowedPaths.push(
			e2ePaths[ process.env.E2E_GROUP ] + '/' + process.env.E2E_BRANCH
		);
	} else {
		allowedPaths.push( e2ePaths[ process.env.E2E_GROUP ] );
	}
} else {
	Object.values( e2ePaths ).forEach( ( testPath ) => {
		allowedPaths.push( testPath );
	} );
}

module.exports = {
	...jestConfig,
	rootDir: path.resolve( __dirname, '../../../' ),
	roots: allowedPaths,
	setupFilesAfterEnv: [
		path.resolve( __dirname, '../setup/jest-setup.js' ),
		...jestConfig.setupFilesAfterEnv,
	],
	testSequencer: path.resolve(
		__dirname,
		'../config/jest-custom-sequencer.js'
	),
};
