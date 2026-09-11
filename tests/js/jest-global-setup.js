/** @format **/

module.exports = async () => {
	process.env.TZ = 'America/New_York';
	process.env.LANG = 'en_US';

	// `wp-scripts test-unit-js` used to force both of these before handing over
	// to Jest. Jest only defaults `NODE_ENV` when it is unset and never sets
	// `BABEL_ENV`, so without this a shell exporting either as `production`
	// would transform the suite with `babel.config.js`'s production block.
	// Setting them here rather than in the npm script keeps it working on
	// shells with no support for `VAR=value command` prefixes.
	process.env.BABEL_ENV = 'test';
	process.env.NODE_ENV = 'test';
};
