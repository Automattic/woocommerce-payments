/**
 * Jest setup file to clean up WordPress component props that shouldn't be passed to the DOM
 * This prevents React warnings about unrecognized DOM properties during tests
 */

// List of WordPress component props that should be removed before reaching the DOM
const WORDPRESS_COMPONENT_PROPS = [
	'__next40pxDefaultSize',
	'__nextHasNoMarginBottom',
	// Add any other WordPress component props that cause DOM warnings here
];

/**
 * Suppresses console warnings about WordPress component props
 */
function suppressWordPressPropWarnings() {
	// eslint-disable-next-line no-console
	const originalConsoleError = console.error;

	// eslint-disable-next-line no-console
	console.error = function ( ...args ) {
		// Check if this is a WordPress component prop warning
		const message = args[ 0 ];
		if (
			typeof message === 'string' &&
			message.includes( 'React does not recognize the' ) &&
			message.includes( 'prop on a DOM element' )
		) {
			// Check if any of the WordPress component props are mentioned
			const hasWordPressProp = WORDPRESS_COMPONENT_PROPS.some( ( prop ) =>
				args.some(
					( arg ) => typeof arg === 'string' && arg.includes( prop )
				)
			);

			if ( hasWordPressProp ) {
				// Suppress this warning
				return;
			}
		}

		// Pass through all other console.error calls
		originalConsoleError.apply( console, args );
	};
}

// Suppress WordPress component prop warnings
suppressWordPressPropWarnings();

// Export the cleanup function for manual use if needed
global.suppressWordPressPropWarnings = suppressWordPressPropWarnings;
