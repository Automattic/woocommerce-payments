const { jestPuppeteerConfig } = require( '@automattic/puppeteer-utils' );

// Add arg to allow accessing the payments iframes in interactive mode ({ headles: false }).
// https://github.com/puppeteer/puppeteer/issues/4960#issuecomment-535729011
jestPuppeteerConfig.launch.args.push( '--disable-features=site-per-process' );

// Use this config to run puppeteer in interactive mode ({ headless: false }).
const config = {
	...jestPuppeteerConfig,
	launch: {
		...jestPuppeteerConfig.launch,
		// Devtools must be false to avoid crashing Chromium with `--disable-features=site-per-process`
		devtools: false,
		headless: false,
		defaultViewport: {
			width: 1280,
			height: 720,
		},
	},
};

module.exports = config;
