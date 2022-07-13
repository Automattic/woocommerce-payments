const path = require( 'path' );
const { useE2EJestConfig } = require( '@woocommerce/e2e-environment' );

// eslint-disable-next-line react-hooks/rules-of-hooks
const testConfig = useE2EJestConfig( {
	setupFiles: [],
	rootDir: path.resolve( __dirname, '../../../' ),
	roots: [ path.resolve( __dirname, '../specs/performance' ) ],
	testSequencer: path.resolve(
		__dirname,
		'../config/jest-custom-sequencer.js'
	),
} );

module.exports = testConfig;
