const config = require( 'config' );

global.process.env = {
	...global.process.env,
	WP_BASE_URL: config.get( 'url' ),
};
