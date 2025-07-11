/**
 * While we're updating the WP components, there are some errors coming from ReactJS.
 * The bundled WP components (which are used in the tests) don't recognize the `__nextHasNoMarginBottom` prop,
 * because they're older versions.
 *
 * Intercept console.warn and console.error to filter out specific React warnings
 * This needs to be done before @wordpress/jest-console sets up its spies
 */

const originalConsoleWarn = console.warn;
const originalConsoleError = console.error;

const makeFilteredConsoleMethod = ( originalMethod ) => {
	return ( ...args ) => {
		// checking if this is a React prop warning about __nextHasNoMarginBottom
		if (
			args.length >= 3 &&
			args[ 0 ] &&
			args[ 0 ].includes(
				'React does not recognize the `%s` prop on a DOM element'
			) &&
			args[ 1 ] === '__nextHasNoMarginBottom'
		) {
			// if it is, ignore
			return;
		}

		// otherwise, call the original method
		return originalMethod.apply( console, args );
	};
};

console.warn = makeFilteredConsoleMethod( originalConsoleWarn );
console.error = makeFilteredConsoleMethod( originalConsoleError );
