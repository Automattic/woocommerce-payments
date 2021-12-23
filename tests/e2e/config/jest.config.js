const path = require( 'path' );
const { config } = require( 'dotenv' );
const { useE2EJestConfig } = require( '@woocommerce/e2e-environment' );

const failureHandler = path.resolve(
	'./',
	'node_modules/@woocommerce/e2e-environment/build/setup/jest.failure.js'
);
const setupEnvHandler = path.resolve(
	'./',
	'node_modules/@automattic/puppeteer-utils/lib/setup-env.js'
);

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
	setupFilesAfterEnv: [
		path.resolve( __dirname, '../setup/jest-setup.js' ),
		setupEnvHandler,
		failureHandler,
		'expect-puppeteer',
	],
	testSequencer: path.resolve(
		__dirname,
		'../config/jest-custom-sequencer.js'
	),
} );

module.exports = testConfig;
