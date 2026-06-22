/**
 * External dependencies
 */
import '@testing-library/jest-dom';

const WORDPRESS_COMPONENT_WARNINGS = [
	'Bottom margin styles for wp.components.BaseControl is deprecated since version 6.7',
	'Bottom margin styles for wp.components.SelectControl is deprecated since version 6.7',
];

const wrapConsoleWarn = () => {
	// eslint-disable-next-line no-console
	const originalWarn = console.warn;
	if ( ! originalWarn?.mock || originalWarn.__wcpayWarningFilter ) {
		return;
	}

	// eslint-disable-next-line no-console
	console.warn = Object.assign(
		( ...args ) => {
			const message = args[ 0 ];
			if (
				typeof message === 'string' &&
				WORDPRESS_COMPONENT_WARNINGS.some( ( warning ) =>
					message.includes( warning )
				)
			) {
				return;
			}

			return originalWarn( ...args );
		},
		{
			__wcpayWarningFilter: true,
			getMockName: () => originalWarn.getMockName(),
			mockClear: () => originalWarn.mockClear(),
			mockName: ( name ) => originalWarn.mockName( name ),
			mockReset: () => originalWarn.mockReset(),
			mockRestore: () => originalWarn.mockRestore(),
		}
	);

	// eslint-disable-next-line no-console
	Object.defineProperties( console.warn, {
		assertionsNumber: {
			get: () => originalWarn.assertionsNumber,
			set: ( value ) => {
				originalWarn.assertionsNumber = value;
			},
		},
		mock: {
			get: () => originalWarn.mock,
		},
	} );
};

beforeEach( wrapConsoleWarn );
