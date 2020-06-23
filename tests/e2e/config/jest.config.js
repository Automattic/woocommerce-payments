const path = require( 'path' );
const { config } = require( 'dotenv' );
const { jestConfig: baseE2Econfig } = require( '@woocommerce/e2e-environment' );

config( { path: path.resolve( __dirname, '.env' ) } );
config( { path: path.resolve( __dirname, 'local.env' ) } );

// Note: changing rootDir breaks baseE2Econfig
module.exports = {
	...baseE2Econfig,
	// Override setupFilesAfterEnv to prevent trashing sample products by @woocommerce/e2e-environment
	setupFilesAfterEnv: [
		path.resolve( __dirname, '../setup/jest-setup.js' ),
		'expect-puppeteer',
	],
	roots: [
		path.resolve( __dirname, '../specs' ),
	],
};
