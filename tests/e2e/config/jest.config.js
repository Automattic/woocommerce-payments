const path = require( 'path' );
const { config } = require( 'dotenv' );
const { useE2EJestConfig } = require( '@woocommerce/e2e-environment' );
const fs = require( 'fs' );

config( { path: path.resolve( __dirname, '.env' ) } );
config( { path: path.resolve( __dirname, 'local.env' ) } );

// Define paths to look for E2E tests.
const e2ePaths = {
	wcpay: path.resolve( __dirname, '../specs/wcpay' ),
	subscriptions: path.resolve( __dirname, '../specs/subscriptions' ),
	blocks: path.resolve( __dirname, '../specs/blocks' ),
	upe: path.resolve( __dirname, '../specs/upe' ),
	upeSplit: path.resolve( __dirname, '../specs/upe-split' ),
};

// Allow E2E tests to run specific tests - wcpay, subscriptions, blocks, all (default).
const allowedPaths = [];

if ( process.env.E2E_GROUP ) {
	// Throw error if E2E_GROUP is not found in defined paths.
	if ( ! ( process.env.E2E_GROUP in e2ePaths ) ) {
		throw new Error(
			`Invalid test group specified: ${ process.env.E2E_GROUP }`
		);
	}

	if ( process.env.E2E_BRANCH ) {
		const combinedPath = path.join(
			e2ePaths[ process.env.E2E_GROUP ],
			process.env.E2E_BRANCH
		);

		// Throw error if path doesn't exist.
		if ( ! fs.existsSync( combinedPath ) ) {
			throw new Error(
				`Invalid test branch specified: ${ process.env.E2E_BRANCH }`
			);
		}

		allowedPaths.push( combinedPath );
	} else {
		allowedPaths.push( e2ePaths[ process.env.E2E_GROUP ] );
	}
} else {
	// The 'atomic' folder is a temporary measure until we are able to run all E2E tests for Atomic sites.
	// The only way to run E2E tests locally for the Atomic site is to use E2E_GROUP=atomic, to avoid duplicate tests in other cases.
	Object.values( e2ePaths ).forEach( ( testPath ) => {
		allowedPaths.push( testPath );
	} );
}

// eslint-disable-next-line react-hooks/rules-of-hooks
const testConfig = useE2EJestConfig( {
	rootDir: path.resolve( __dirname, '../../../' ),
	setupFiles: [ '<rootDir>/tests/e2e/config/env.setup.js' ],
	roots: allowedPaths,
	testSequencer: path.resolve(
		__dirname,
		'../config/jest-custom-sequencer.js'
	),
} );

module.exports = testConfig;
