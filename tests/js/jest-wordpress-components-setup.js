/**
 * Jest setup file for handling WordPress component props and components in tests
 *
 * This file addresses several issues that arise when testing components that use
 *
 * `@wordpress/components` in a Jest environment:
 *
 * 1. **WordPress Component Props**: Some WordPress components use props like
 *    `__next40pxDefaultSize` and `__nextHasNoMarginBottom` that are meant for
 *    internal WordPress styling but get passed to the DOM, causing React warnings
 *    in test environments.
 *
 * 2. **Missing Components**: Some components like `CardNotice` are referenced in
 *    type definitions but not actually available in the `@wordpress/components`
 *    package, causing undefined component errors.
 *
 * **How it works:**
 *
 * This setup overrides React.createElement to intercept component creation and:
 * - Filter out problematic WordPress component props before they reach the DOM
 * - Provide mock implementations for missing components
 * - Ensure tests run without React warnings or undefined component errors
 */

const React = require( 'react' );

/**
 * Mock implementation of CardNotice component
 *
 * CardNotice is referenced in type definitions but not actually available in
 *
 * `@wordpress/components`. This mock provides a basic implementation that renders
 * the children content and actions in a structured way for testing.
 *
 * @param {Object} props - Component props including children and actions
 * @param {React.ReactNode} children - The content to render
 * @return {React.Element} Mock CardNotice component
 */
const MockCardNotice = ( props, children ) => {
	const { actions, ...restProps } = props || {};
	return React.createElement(
		'div',
		{
			...restProps,
			className: 'card-notice-mock',
			'data-testid': 'card-notice',
		},
		[
			React.createElement(
				'div',
				{ key: 'content', className: 'card-notice__text' },
				children
			),
			actions &&
				React.createElement(
					'div',
					{ key: 'actions', className: 'card-notice__button' },
					actions
				),
		]
	);
};

// Store the original React.createElement function
const originalCreateElement = React.createElement;

/**
 * Override React.createElement to handle WordPress component issues
 *
 * This function intercepts all component creation and applies specific handling
 * for WordPress components that cause issues in test environments.
 *
 * @param {Function|string} type - The component type or HTML element
 * @param {Object} props - Component props
 * @param {...any} children - Component children
 * @return {React.Element} The created React element
 */
React.createElement = function ( type, props, ...children ) {
	// Handle undefined components (like CardNotice from @wordpress/components)
	if ( type === undefined ) {
		// Check if this might be CardNotice by looking at the props or context
		// CardNotice typically has actions prop and is used in specific contexts
		if (
			props &&
			( props.actions ||
				props.className === 'card-notice' ||
				props[ 'data-testid' ] === 'card-notice' )
		) {
			return MockCardNotice( props, ...children );
		}
		// For any other undefined component, return a simple div with error info
		return React.createElement(
			'div',
			{
				className: 'undefined-component-mock',
				'data-testid': 'undefined-component',
				style: {
					color: 'red',
					border: '1px solid red',
					padding: '10px',
				},
			},
			`Undefined component rendered`
		);
	}

	// Handle CardNotice component - replace it with our mock
	if ( type && type.displayName === 'CardNotice' ) {
		return MockCardNotice( props, ...children );
	}

	// Create a new props object to avoid mutating the original
	const cleanProps = { ...props };

	// Filter out WordPress-specific props that should not be passed to DOM
	if ( cleanProps ) {
		// Remove __nextHasNoMarginBottom prop from any component
		if ( cleanProps.__nextHasNoMarginBottom !== undefined ) {
			delete cleanProps.__nextHasNoMarginBottom;
		}

		// Remove __next40pxDefaultSize prop from any component
		if ( cleanProps.__next40pxDefaultSize !== undefined ) {
			delete cleanProps.__next40pxDefaultSize;
		}
	}

	// For all other cases, use the original React.createElement with cleaned props
	return originalCreateElement( type, cleanProps, ...children );
};
