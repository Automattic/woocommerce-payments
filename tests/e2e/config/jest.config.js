const path = require( 'path' );
const { config } = require( 'dotenv' );
const { useE2EJestConfig } = require( '@woocommerce/e2e-environment' );

config( { path: path.resolve( __dirname, '.env' ) } );
config( { path: path.resolve( __dirname, 'local.env' ) } );

// eslint-disable-next-line react-hooks/rules-of-hooks
const testConfig = useE2EJestConfig( {
	setupFiles: [],
	rootDir: path.resolve( __dirname, '../../../' ),
	roots: [
		path.resolve( __dirname, '../specs/merchant' ),
		path.resolve( __dirname, '../specs/shopper' ),
	],
	testSequencer: path.resolve(
		__dirname,
		'../config/jest-custom-sequencer.js'
	),
} );

module.exports = testConfig;
