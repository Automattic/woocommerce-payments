/**
 * Jest setup file to handle WordPress Button component props in tests
 * This prevents props like __next40pxDefaultSize from being passed to the DOM
 */

const React = require( 'react' );

// Use a more targeted approach by overriding React.createElement for Button components
const originalCreateElement = React.createElement;

React.createElement = function ( type, props, ...children ) {
	// Check if this is a Button component from @wordpress/components
	if (
		type &&
		( type.displayName === 'Button' ||
			( typeof type === 'function' && type.name === 'Button' ) ||
			( props && props.__next40pxDefaultSize ) )
	) {
		// Filter out the __next40pxDefaultSize prop
		const { __next40pxDefaultSize, ...restProps } = props || {};
		return originalCreateElement( type, restProps, ...children );
	}

	return originalCreateElement( type, props, ...children );
};
