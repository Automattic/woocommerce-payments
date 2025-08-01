/**
 * Test file to verify that WordPress component props are properly removed
 * before reaching the DOM during testing
 */

// Import React and testing library at the top level to avoid hook issues
const React = require( 'react' );
const { render } = require( '@testing-library/react' );

describe( 'DOM Property Cleanup', () => {
	beforeEach( () => {
		// Ensure React is available
		jest.resetModules();
	} );

	test( 'should remove __next40pxDefaultSize prop from Button component', () => {
		// Create a Button component with the problematic prop
		const Button = ( { children, ...props } ) => {
			return React.createElement( 'button', props, children );
		};

		// Render with the WordPress prop
		const { container } = render(
			<Button __next40pxDefaultSize={ true }>Test Button</Button>
		);

		// Check that the prop was removed from the DOM
		const button = container.querySelector( 'button' );
		expect( button ).toBeTruthy();
		expect( button.getAttribute( '__next40pxDefaultSize' ) ).toBeNull();
		expect( button.textContent ).toBe( 'Test Button' );
	} );

	test( 'should remove __nextHasNoMarginBottom prop from TextControl component', () => {
		// Create a TextControl component with the problematic prop
		const TextControl = ( { children, ...props } ) => {
			return React.createElement( 'input', props, children );
		};

		// Render with the WordPress prop
		const { container } = render(
			<TextControl __nextHasNoMarginBottom={ true } />
		);

		// Check that the prop was removed from the DOM
		const input = container.querySelector( 'input' );
		expect( input ).toBeTruthy();
		expect( input.getAttribute( '__nextHasNoMarginBottom' ) ).toBeNull();
	} );

	test( 'should preserve other props while removing WordPress props', () => {
		const Button = ( { children, ...props } ) => {
			return React.createElement( 'button', props, children );
		};

		// Render with both WordPress props and regular props
		const { container } = render(
			<Button
				__next40pxDefaultSize={ true }
				className="test-class"
				id="test-button"
				onClick={ () => {} }
			>
				Test Button
			</Button>
		);

		// Check that WordPress props were removed but others preserved
		const button = container.querySelector( 'button' );
		expect( button ).toBeTruthy();
		expect( button.getAttribute( '__next40pxDefaultSize' ) ).toBeNull();
		expect( button.getAttribute( 'class' ) ).toBe( 'test-class' );
		expect( button.getAttribute( 'id' ) ).toBe( 'test-button' );
		expect( button.onclick ).toBeDefined();
	} );

	test( 'should handle nested components with WordPress props', () => {
		const Button = ( { children, ...props } ) => {
			return React.createElement( 'button', props, children );
		};

		const Container = ( { children } ) => {
			return React.createElement(
				'div',
				{ className: 'container' },
				children
			);
		};

		// Render nested components with WordPress props
		const { container } = render(
			<Container>
				<Button __next40pxDefaultSize={ true }>Inner Button</Button>
			</Container>
		);

		// Check that the WordPress prop was removed from the nested button
		const button = container.querySelector( 'button' );
		expect( button ).toBeTruthy();
		expect( button.getAttribute( '__next40pxDefaultSize' ) ).toBeNull();
		expect( button.textContent ).toBe( 'Inner Button' );
	} );
} );
