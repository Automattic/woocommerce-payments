const path = require( 'path' );
const { config } = require( 'dotenv' );
const { jestConfig } = require( '@automattic/puppeteer-utils' );

config( { path: path.resolve( __dirname, '.env' ) } );
config( { path: path.resolve( __dirname, 'local.env' ) } );

module.exports = {
	...jestConfig,
	rootDir: path.resolve( __dirname, '../../../' ),
	roots: [
		path.resolve( __dirname, '../specs/merchant' ),
		path.resolve( __dirname, '../specs/shopper' ),
	],
	setupFilesAfterEnv: [
		path.resolve( __dirname, '../setup/jest-setup.js' ),
		...jestConfig.setupFilesAfterEnv,
	],
};
