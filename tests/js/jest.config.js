module.exports = {
	rootDir: '../../',
	moduleDirectories: [ 'node_modules', '<rootDir>/client' ],
	moduleNameMapper: {
		'^react$': '<rootDir>/node_modules/react',
		'^react-dom$': '<rootDir>/node_modules/react-dom',
	},
	globalSetup: '<rootDir>/tests/js/jest-global-setup.js',
	setupFiles: [
		require.resolve(
			'@wordpress/jest-preset-default/scripts/setup-globals.js'
		),
		'<rootDir>/tests/js/jest-test-file-setup.js',
	],
	setupFilesAfterEnv: [
		'<rootDir>/node_modules/@wordpress/jest-preset-default/scripts/setup-test-framework.js',
		'<rootDir>/tests/js/jest-extensions-setup.js',
	],
	preset: '@wordpress/jest-preset-default',
	testPathIgnorePatterns: [
		'/node_modules/',
		'/vendor/',
		'<rootDir>/.*/build/',
		'<rootDir>/.*/build-module/',
		'<rootDir>/docker/',
		'<rootDir>/tests/e2e',
	],
	transformIgnorePatterns: [ 'node_modules/(?!(@woocommerce/.+)/)' ],
	verbose: true,
};
