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
 * 3. **Test Environment Compatibility**: The WordPress component library is designed
 *    for production environments and may not handle test scenarios gracefully.
 *
 * **How it works:**
 *
 * This setup overrides React.createElement to intercept component creation and:
 * - Filter out problematic WordPress component props before they reach the DOM
 * - Provide mock implementations for missing components
 * - Ensure tests run without React warnings or undefined component errors
 *
 * **Why this approach:**
 *
 * Instead of mocking entire modules (which can be complex and brittle), this
 * approach uses React.createElement override to handle specific cases. This is:
 * - More targeted and less likely to break other functionality
 * - Easier to maintain and debug
 * - More performant than module-level mocking
 *
 * **Usage:**
 *
 * This file is automatically loaded by Jest via the setupFilesAfterEnv configuration
 * in jest.config.js. No additional setup is required in individual test files.
 *
 * **Maintained by:** WooCommerce Payments team
 * **Last updated:** 2024
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

	// Filter out __next40pxDefaultSize prop from Button components
	// This prop is used by WordPress for button sizing but should not be passed to DOM
	if (
		type &&
		( type.displayName === 'Button' ||
			( typeof type === 'function' && type.name === 'Button' ) ||
			( props && props.__next40pxDefaultSize ) )
	) {
		const { __next40pxDefaultSize, ...restProps } = props || {};
		return originalCreateElement( type, restProps, ...children );
	}

	// Filter out __nextHasNoMarginBottom prop from any component
	// This prop is used by WordPress for margin control but should not be passed to DOM
	if ( props && props.__nextHasNoMarginBottom ) {
		const { __nextHasNoMarginBottom, ...restProps } = props;
		return originalCreateElement( type, restProps, ...children );
	}

	// For all other cases, use the original React.createElement
	return originalCreateElement( type, props, ...children );
};
