const { jsWithBabel: tsjPreset } = require( 'ts-jest/presets' );

module.exports = {
	rootDir: '../../',
	moduleDirectories: [
		'node_modules',
		'<rootDir>/client',
		'<rootDir>/multi-currency/client',
	],
	moduleNameMapper: {
		'^react$': '<rootDir>/node_modules/react',
		'^react-dom$': '<rootDir>/node_modules/react-dom',
		'^moment$': '<rootDir>/node_modules/moment',
		'^moment-timezone$': '<rootDir>/node_modules/moment-timezone',
		'^wcpay(.*)$': '<rootDir>/client$1',
		'^mccy(.*)$': '<rootDir>/multi-currency/client$1',
		'^iti/utils$': '<rootDir>/node_modules/intl-tel-input/build/js/utils',
		'^assets(.*?)(\\?.*)?$': '<rootDir>/assets$1',
		'^@woocommerce/blocks-registry$':
			'<rootDir>/tests/js/woocommerce-blocks-registry',
		'^uuid$': require.resolve( 'uuid' ),
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
		'expect-puppeteer',
	],
	preset: '@wordpress/jest-preset-default',
	testMatch: [
		'**/__tests__/**/*.(js|ts|tsx)',
		'**/?(*.)(spec|test).(js|ts|tsx)',
		'**/test/*.(js|ts|tsx)',
	],
	testPathIgnorePatterns: [
		'/node_modules/',
		'/vendor/',
		'<rootDir>/.*/build/',
		'<rootDir>/.*/build-module/',
		'<rootDir>/docker/',
		'<rootDir>/tests/e2e',
	],
	transform: {
		...tsjPreset.transform,
		'^.+\\.(jpg|svg|png|gif)(\\?.*)?$': '<rootDir>/tests/js/fileMock.js',
	},
	transformIgnorePatterns: [
		'node_modules/(?!(@woocommerce/.+)|gridicons|@automattic/components/|@automattic/material-design-icons/)',
	],
	verbose: true,
};
