const { jestConfig: baseE2Econfig } = require( '@woocommerce/e2e-environment' );
const { config } = require( 'dotenv' );
const { jestConfig } = require( '@automattic/puppeteer-utils' );

config( { path: './tests/e2e/config/.env' } );
config( { path: './tests/e2e/config/local.env' } );

module.exports = {
	...jestConfig,
	...baseE2Econfig,
	rootDir: '../../../',
	roots: [
		'<rootDir>/tests/e2e/specs',
	],
};
