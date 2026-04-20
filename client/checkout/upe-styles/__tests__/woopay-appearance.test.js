/**
 * Internal dependencies
 */
import * as upeStyles from '..';

describe( 'WooPay appearance theming', () => {
	const mockElement = document.createElement( 'input' );
	const cssPropertiesCamel = {
		fontFamily:
			'"Source Sans Pro", HelveticaNeue-Light, "Helvetica Neue Light"',
		color: 'rgb(109, 109, 109)',
		backgroundColor: 'rgba(0, 0, 0, 0)',
		unsuportedProperty: 'some value',
		outlineColor: 'rgb(150, 88, 138)',
		outlineWidth: '1px',
		fontSize: '12px',
		padding: '10px',
	};
	const cssPropertiesDashed = {
		'font-family':
			'"Source Sans Pro", HelveticaNeue-Light, "Helvetica Neue Light"',
		color: 'rgb(109, 109, 109)',
		'background-color': 'rgba(0, 0, 0, 0)',
		'unsuported-property': 'some value',
		'outline-color': 'rgb(150, 88, 138)',
		'outline-width': '1px',
		'font-size': '12px',
		padding: '10px',
	};
	const mockCSStyleDeclaration = {
		...cssPropertiesCamel,
		length: 8,
		0: 'color',
		1: 'backgroundColor',
		2: 'fontFamily',
		3: 'unsuportedProperty',
		4: 'outlineColor',
		5: 'outlineWidth',
		6: 'fontSize',
		7: 'padding',
		getPropertyValue: ( propertyName ) =>
			cssPropertiesDashed[ propertyName ],
	};

	test( 'getAppearance for woopay_shortcode_checkout includes WooPay rules with correct link priority', () => {
		const contentLink = document.createElement( 'a' );
		const navLink = document.createElement( 'a' );
		const accentColor = 'rgb(0, 102, 204)';
		const navColor = 'rgb(255, 255, 255)';

		const accentStyleDeclaration = {
			...cssPropertiesCamel,
			color: accentColor,
			getPropertyValue: ( prop ) => {
				if ( prop === 'color' ) return accentColor;
				return cssPropertiesDashed[ prop ];
			},
		};
		const navStyleDeclaration = {
			...cssPropertiesCamel,
			color: navColor,
			getPropertyValue: ( prop ) => {
				if ( prop === 'color' ) return navColor;
				return cssPropertiesDashed[ prop ];
			},
		};

		const scope = {
			querySelector: jest.fn( ( selector ) => {
				// Content-area link — should win for .Link color.
				if ( selector === 'form.checkout a' ) return contentLink;
				// Bare fallback — should NOT win.
				if ( selector === 'a' ) return navLink;
				// Everything else uses default mock element.
				return mockElement;
			} ),
			createElement: jest.fn( ( htmlTag ) =>
				document.createElement( htmlTag )
			),
			defaultView: {
				getComputedStyle: jest.fn( ( el ) => {
					if ( el === contentLink ) return accentStyleDeclaration;
					if ( el === navLink ) return navStyleDeclaration;
					return mockCSStyleDeclaration;
				} ),
			},
		};

		const appearance = upeStyles.getAppearance(
			'woopay_shortcode_checkout',
			true,
			scope
		);

		// WooPay-specific rules should all be present.
		// Use array syntax to prevent Jest interpreting dots as nested paths.
		expect( appearance.rules ).toHaveProperty( [ '.Link' ] );
		expect( appearance.rules ).toHaveProperty( [ '.Header' ] );
		expect( appearance.rules ).toHaveProperty( [ '.Footer' ] );
		expect( appearance.rules ).toHaveProperty( [ '.Footer-link' ] );
		expect( appearance.rules ).toHaveProperty( [ '.Button' ] );
		expect( appearance.rules ).toHaveProperty( [ '.Heading' ] );
		expect( appearance.rules ).toHaveProperty( [ '.Container' ] );

		// Link color should come from content-area selector, not bare 'a'.
		expect( appearance.rules[ '.Link' ].color ).toBe( accentColor );

		// Verify the content-area link selector was actually queried.
		expect( scope.querySelector ).toHaveBeenCalledWith( 'form.checkout a' );
	} );

	test( 'getAppearance routes woopay_shortcode_checkout to wooPayClassicCheckout selectors', () => {
		const scope = {
			querySelector: jest.fn( () => mockElement ),
			createElement: jest.fn( ( htmlTag ) =>
				document.createElement( htmlTag )
			),
			defaultView: {
				getComputedStyle: jest.fn( () => mockCSStyleDeclaration ),
			},
		};

		upeStyles.getAppearance( 'woopay_shortcode_checkout', false, scope );

		expect( scope.querySelector ).toHaveBeenCalledWith(
			upeStyles.appearanceSelectors.wooPayClassicCheckout
				.upeThemeInputSelector
		);
		expect( scope.querySelector ).toHaveBeenCalledWith(
			upeStyles.appearanceSelectors.wooPayClassicCheckout
				.upeThemeLabelSelector
		);
	} );
} );
