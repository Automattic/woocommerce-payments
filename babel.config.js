// Note it is important to have file named babel.config.js because tests fail if named .babelrc
// :exploding_head: same case here: https://github.com/facebook/jest/issues/9292#issuecomment-625750534
module.exports = {
	env: {},
	ignore: [],
	presets: [ '@wordpress/babel-preset-default' ],
};
