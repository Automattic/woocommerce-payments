const config = require( 'config' );

global.process.env = {
	...global.process.env,
	// Remove the trailing slash from jest sequencer WORDPRESS_URL.
	WP_BASE_URL: config.get( 'url' ),
	PUPPETEER_SLOWMO: true,
};
