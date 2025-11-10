module.exports = {
	env: {
		node: true,
	},
	globals: {
		page: 'readonly',
		browser: 'readonly',
		context: 'readonly',
	},
	rules: {
		// Disable Jest-specific rules that conflict with Playwright
		'jest/no-done-callback': 'off',
		'jest/expect-expect': 'off',
		// Allow QIT-specific imports that ESLint can't resolve
		'import/no-unresolved': [ 'error', { ignore: [ '/qitHelpers' ] } ],
	},
	overrides: [
		{
			files: [ '*.spec.js', '*.test.js' ],
			rules: {
				// Playwright test specific overrides
				'jest/no-done-callback': 'off',
			},
		},
	],
};
