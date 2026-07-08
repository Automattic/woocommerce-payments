/**
 * External dependencies
 */
const wordpress = require( '@wordpress/eslint-plugin' );
const jest = require( 'eslint-plugin-jest' );
const testingLibrary = require( 'eslint-plugin-testing-library' );
const globals = require( 'globals' );

// Test file globs, matching what @woocommerce/eslint-plugin used to apply.
const jsTestFiles = [
	'**/@(test|__tests__)/**/*.js',
	'**/?(*.)test.js',
	'**/tests/**/*.js',
];

module.exports = [
	{
		// ESLint 9 no longer skips dot-directories by default.
		ignores: [
			'**/.*/**',
			'bin/**',
			'!bin/generate-docs/**',
			'dist/**',
			'docker/**',
			'includes/**',
			'vendor/**',
			'release/**',
			'node_modules/**',
			'tests/e2e/docker*/**',
			'tests/e2e/deps/**',
			'tests/qit/test-package/**',
			'docs/rest-api/source/**/*.js',
			'playwright-report/**',
		],
	},
	...wordpress.configs.recommended,
	{
		files: [ '**/*.js', '**/*.jsx', '**/*.ts', '**/*.tsx' ],
		languageOptions: {
			globals: {
				...globals.browser,
				...globals.node,
				...globals.jest,
				wp: true,
				wpApiSettings: true,
				wcSettings: true,
				wcpaySettings: true,
				page: true,
				browser: true,
				context: true,
			},
		},
		settings: {
			react: {
				version: 'detect',
			},
			jsdoc: {
				mode: 'typescript',
			},
			'import/resolver': {
				typescript: {},
			},
			// Modules that are externals in our webpack config.
			'import/core-modules': [
				'@woocommerce/settings',
				'lodash',
				'react',
			],
		},
		rules: {
			camelcase: [
				'error',
				{
					properties: 'never',
					ignoreGlobals: true,
				},
			],
			eqeqeq: [ 'error', 'always', { null: 'ignore' } ],
			radix: 'error',
			yoda: [ 'error', 'never' ],
			'import/no-extraneous-dependencies': 'off',
			indent: 'off',
			'max-len': [
				'error',
				{
					code: 140,
				},
			],
			'no-console': 'warn',
			'react/no-danger': 'off',
			'react/react-in-jsx-scope': 'off',
			'react/jsx-curly-spacing': [
				2,
				{
					when: 'always',
					children: {
						when: 'always',
					},
				},
			],
			'jsx-a11y/label-has-for': [
				'error',
				{
					required: 'id',
				},
			],
			'jsdoc/check-tag-names': [
				'error',
				{
					definedTags: [ 'format' ],
				},
			],
			/* partially disable rules to get @wordpress/eslint-plugin integration done */
			'jsdoc/no-undefined-types': 'off',
			'jsdoc/require-param': 'off',
			'jsdoc/check-param-names': 'off',
			'jsdoc/require-property': 'off',
			'@wordpress/no-unused-vars-before-return': 'off',
			'@wordpress/i18n-translator-comments': 'off',
			'@wordpress/valid-sprintf': 'off',
			'@wordpress/i18n-text-domain': [
				'error',
				{ allowedTextDomain: 'woocommerce-payments' },
			],
			// Fixing these would change existing translated strings; needs a
			// dedicated pass with translation updates.
			'@wordpress/i18n-no-flanking-whitespace': 'off',
			'@wordpress/i18n-hyphenated-range': 'off',
			'react-hooks/exhaustive-deps': [
				'error',
				{ additionalHooks: '(^useSelect$|^useSuspenseSelect$)' },
			],
			'react-hooks/rules-of-hooks': 'error',
			'no-alert': 'off',
			'object-shorthand': 'off',
			'no-multi-str': 'off',
			'no-restricted-syntax': [
				'error',
				{
					selector:
						'ImportDeclaration[source.value=/gridicons(?!\\u002F)/]',
					message:
						"Do not import whole Gridicons, import them individually with 'gridicons/dist/icon-name'.",
				},
			],
		},
	},
	// Jest rules for JS test files, matching @wordpress/eslint-plugin/test-unit
	// scoped the way @woocommerce/eslint-plugin used to.
	...wordpress.configs[ 'test-unit' ].map( ( config ) => ( {
		...config,
		files: jsTestFiles,
	} ) ),
	{
		files: jsTestFiles,
		plugins: {
			'testing-library': testingLibrary,
		},
		rules: {
			...testingLibrary.configs[ 'flat/react' ].rules,
			'testing-library/prefer-query-by-disappearance': 'off',
			'testing-library/render-result-naming-convention': 'off',
			'testing-library/prefer-screen-queries': 'off',
			'testing-library/prefer-presence-queries': 'off',
			'testing-library/no-container': 'off',
			'testing-library/no-node-access': 'off',
			'testing-library/prefer-find-by': 'off',
			// allow the use of render in beforeEach
			'testing-library/no-render-in-lifecycle': [
				'error',
				{ allowTestingFrameworkSetupHook: 'beforeEach' },
			],
		},
	},
	// Jest rules disabled repo-wide before the flat config migration.
	{
		files: [ '**/*.js', '**/*.jsx', '**/*.ts', '**/*.tsx' ],
		plugins: {
			jest,
		},
		rules: {
			'jest/no-conditional-expect': 'off',
			'jest/valid-title': 'off',
			'jest/expect-expect': 'off',
			'jest/no-disabled-tests': 'off',
			'jest/no-standalone-expect': 'off',
			'jest/no-identical-title': 'off',
			'jest/no-deprecated-functions': 'off',
		},
	},
	{
		files: [ '**/*.ts', '**/*.tsx' ],
		rules: {
			// The .eslintrc TS override extended eslint-config-prettier and
			// jsx-a11y/recommended at override level, which disabled these two
			// for TS files; keep that behaviour.
			'max-len': 'off',
			'jsx-a11y/label-has-for': 'off',
			camelcase: 'off',
			'@typescript-eslint/naming-convention': [
				'error',
				{
					selector: [ 'method', 'variableLike' ],
					format: [ 'camelCase', 'PascalCase' ],
				},
				{
					selector: 'typeProperty',
					format: [ 'camelCase', 'snake_case' ],
				},
			],
			'@typescript-eslint/no-explicit-any': 'off',
			'no-use-before-define': 'off',
			'@typescript-eslint/no-use-before-define': [ 'error' ],
			'@typescript-eslint/explicit-module-boundary-types': 'off',
			'no-shadow': 'off',
			'@typescript-eslint/no-shadow': 'error',
			'@typescript-eslint/no-unused-vars': [
				'error',
				{
					varsIgnorePattern: '^(React|createElement)$',
					ignoreRestSiblings: true,
					argsIgnorePattern: '^_',
					caughtErrors: 'none',
				},
			],
			'jsdoc/require-param-type': 0,
			'jsdoc/require-returns-type': 0,
		},
	},
	// Keep the previous unused-vars options for JS files.
	{
		files: [ '**/*.js', '**/*.jsx' ],
		rules: {
			'no-unused-vars': [
				'error',
				{
					varsIgnorePattern: '^(React|createElement)$',
					ignoreRestSiblings: true,
					argsIgnorePattern: '^_',
					caughtErrors: 'none',
				},
			],
		},
	},
];
