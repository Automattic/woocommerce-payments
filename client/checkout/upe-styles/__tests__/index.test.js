/**
 * Internal dependencies
 */
import * as upeStyles from '..';
import { appearanceSelectors } from '..';

describe( 'appearanceSelectors.updateSelectors', () => {
	let scope;

	beforeEach( () => {
		scope = document;
		document.body.innerHTML = '';
	} );

	it( 'uses primary selectors when they exist in the DOM', () => {
		document.body.innerHTML =
			'<div class="woocommerce-billing-fields__field-wrapper">' +
			'<input id="billing_first_name" type="text" />' +
			'</div>';

		const selectors = {
			appendTarget: '.woocommerce-billing-fields__field-wrapper',
			upeThemeInputSelector: '#billing_first_name',
			alternateSelectors: {
				appendTarget: 'form.checkout',
				upeThemeInputSelector: 'form.checkout input[type="text"]',
			},
		};

		const result = appearanceSelectors.updateSelectors( selectors, scope );
		expect( result.appendTarget ).toBe(
			'.woocommerce-billing-fields__field-wrapper'
		);
		expect( result.upeThemeInputSelector ).toBe( '#billing_first_name' );
		expect( result ).not.toHaveProperty( 'alternateSelectors' );
	} );

	it( 'falls back to alternate selectors when primary are missing', () => {
		document.body.innerHTML =
			'<form class="checkout">' +
			'<input type="text" name="name" />' +
			'</form>';

		const selectors = {
			appendTarget: '.woocommerce-billing-fields__field-wrapper',
			upeThemeInputSelector: '#billing_first_name',
			alternateSelectors: {
				appendTarget: 'form.checkout',
				upeThemeInputSelector: 'form.checkout input[type="text"]',
			},
		};

		const result = appearanceSelectors.updateSelectors( selectors, scope );
		expect( result.appendTarget ).toBe( 'form.checkout' );
		expect( result.upeThemeInputSelector ).toBe(
			'form.checkout input[type="text"]'
		);
	} );

	it( 'falls back array selectors when none of the primary match', () => {
		document.body.innerHTML =
			'<form class="checkout">' +
			'<div class="woocommerce">content</div>' +
			'</form>';

		const selectors = {
			upeThemeTextSelectors: [
				'#payment .payment_methods li .payment_box fieldset',
				'.woocommerce-checkout .form-row',
			],
			alternateSelectors: {
				upeThemeTextSelectors: [ 'form.checkout', '.woocommerce' ],
			},
		};

		const result = appearanceSelectors.updateSelectors( selectors, scope );
		expect( result.upeThemeTextSelectors ).toEqual( [
			'form.checkout',
			'.woocommerce',
		] );
	} );

	it( 'keeps array selectors when at least one primary matches', () => {
		document.body.innerHTML =
			'<div class="woocommerce-checkout">' +
			'<div class="form-row">content</div>' +
			'</div>';

		const selectors = {
			upeThemeTextSelectors: [
				'#payment .payment_methods li .payment_box fieldset',
				'.woocommerce-checkout .form-row',
			],
			alternateSelectors: {
				upeThemeTextSelectors: [ 'form.checkout', '.woocommerce' ],
			},
		};

		const result = appearanceSelectors.updateSelectors( selectors, scope );
		expect( result.upeThemeTextSelectors ).toEqual( [
			'#payment .payment_methods li .payment_box fieldset',
			'.woocommerce-checkout .form-row',
		] );
	} );
} );

describe( 'Getting styles for automated theming', () => {
	const mockElement = document.createElement( 'input' );
	// camelCase for direct property access (styles.fontFamily)
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
	// dashed for getPropertyValue (styles.getPropertyValue('font-family'))
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

	test( 'getFieldStyles returns correct styles for inputs', () => {
		const scope = {
			querySelector: jest.fn( () => mockElement ),
			defaultView: {
				getComputedStyle: jest.fn( () => mockCSStyleDeclaration ),
			},
		};

		const fieldStyles = upeStyles.getFieldStyles(
			'.woocommerce-checkout .form-row input',
			'.Input',
			null,
			scope
		);
		expect( fieldStyles ).toEqual( {
			backgroundColor: 'rgba(0, 0, 0, 0)',
			color: 'rgb(109, 109, 109)',
			fontFamily:
				'"Source Sans Pro", HelveticaNeue-Light, "Helvetica Neue Light"',
			fontSize: '12px',
			outline: '1px solid rgb(150, 88, 138)',
			padding: '10px',
		} );
	} );

	test( 'getFieldStyles returns empty object if it can not find the element', () => {
		const scope = {
			querySelector: jest.fn( () => undefined ),
		};

		const fieldStyles = upeStyles.getFieldStyles(
			'.i-do-not-exist',
			'.Input',
			null,
			scope
		);
		expect( fieldStyles ).toEqual( {} );
	} );

	test( 'getFontRulesFromPage returns font rules from allowed font providers', () => {
		const mockStyleSheets = {
			length: 5,
			0: {
				href:
					'https://not-supported-fonts-domain.com/style.css?ver=1.1.1',
			},
			1: { href: null },
			2: {
				href:
					// eslint-disable-next-line max-len
					'https://fonts.googleapis.com/css?family=Source+Sans+Pro%3A400%2C300%2C300italic%2C400italic%2C600%2C700%2C900&subset=latin%2Clatin-ext&ver=3.6.0',
			},
			3: {
				href: 'https://fonts.bunny.net/css?family=Inter:400,700',
			},
			4: {
				href: 'https://fonts.wp.com/css?family=Open+Sans:400,700',
			},
		};
		jest.spyOn( document, 'styleSheets', 'get' ).mockReturnValue(
			mockStyleSheets
		);

		const fontRules = upeStyles.getFontRulesFromPage();
		expect( fontRules ).toEqual( [
			{
				cssSrc:
					// eslint-disable-next-line max-len
					'https://fonts.googleapis.com/css?family=Source+Sans+Pro%3A400%2C300%2C300italic%2C400italic%2C600%2C700%2C900&subset=latin%2Clatin-ext&ver=3.6.0',
			},
			{ cssSrc: 'https://fonts.bunny.net/css?family=Inter:400,700' },
			{
				cssSrc: 'https://fonts.wp.com/css?family=Open+Sans:400,700',
			},
		] );
	} );

	test( 'getFontRulesFromPage returns empty array if there are no fonts from allowed providers', () => {
		const mockStyleSheets = {
			length: 2,
			0: {
				href:
					'https://not-supported-fonts-domain.com/style.css?ver=1.1.1',
			},
			1: { href: null },
		};
		const scope = {
			styleSheets: {
				get: jest.fn( () => mockStyleSheets ),
			},
		};

		const fontRules = upeStyles.getFontRulesFromPage( scope );
		expect( fontRules ).toEqual( [] );
	} );

	test( 'getAppearance returns the object with filtered CSS rules for UPE theming', () => {
		const scope = {
			querySelector: jest.fn( () => mockElement ),
			createElement: jest.fn( ( htmlTag ) =>
				document.createElement( htmlTag )
			),
			defaultView: {
				getComputedStyle: jest.fn( () => mockCSStyleDeclaration ),
			},
		};

		const appearance = upeStyles.getAppearance(
			'shortcode_checkout',
			false,
			scope
		);
		expect( appearance ).toEqual( {
			variables: {
				colorBackground: '#ffffff',
				colorText: 'rgb(109, 109, 109)',
				fontFamily:
					'"Source Sans Pro", HelveticaNeue-Light, "Helvetica Neue Light"',
				fontSizeBase: '12px',
			},
			theme: 'stripe',
			rules: {
				'.Input': {
					backgroundColor: 'rgba(0, 0, 0, 0)',
					color: 'rgb(109, 109, 109)',
					fontFamily:
						'"Source Sans Pro", HelveticaNeue-Light, "Helvetica Neue Light"',
					outline: '1px solid rgb(150, 88, 138)',
					fontSize: '12px',
					padding: '10px',
				},
				'.Input--invalid': {
					backgroundColor: 'rgba(0, 0, 0, 0)',
					color: 'rgb(109, 109, 109)',
					fontFamily:
						'"Source Sans Pro", HelveticaNeue-Light, "Helvetica Neue Light"',
					outline: '1px solid rgb(150, 88, 138)',
					fontSize: '12px',
					padding: '10px',
				},
				'.Label': {
					color: 'rgb(109, 109, 109)',
					fontFamily:
						'"Source Sans Pro", HelveticaNeue-Light, "Helvetica Neue Light"',
					fontSize: '12px',
					padding: '10px',
				},
				'.Label--resting': {
					fontSize: '12px',
				},
				'.Tab': {
					backgroundColor: 'rgba(0, 0, 0, 0)',
					color: 'rgb(109, 109, 109)',
					fontFamily:
						'"Source Sans Pro", HelveticaNeue-Light, "Helvetica Neue Light"',
				},
				'.Tab:hover': {
					backgroundColor: 'rgba(18, 18, 18, 0)',
					color: 'rgb(255, 255, 255)',
					fontFamily:
						'"Source Sans Pro", HelveticaNeue-Light, "Helvetica Neue Light"',
				},
				'.Tab--selected': {
					backgroundColor: 'rgba(0, 0, 0, 0)',
					color: 'rgb(109, 109, 109)',
					outline: '1px solid rgb(150, 88, 138)',
				},
				'.TabIcon:hover': {
					color: 'rgb(255, 255, 255)',
				},
				'.TabIcon--selected': {
					color: 'rgb(109, 109, 109)',
				},
				'.Text': {
					color: 'rgb(109, 109, 109)',
					fontFamily:
						'"Source Sans Pro", HelveticaNeue-Light, "Helvetica Neue Light"',
					fontSize: '12px',
					padding: '10px',
				},
				'.Text--redirect': {
					color: 'rgb(109, 109, 109)',
					fontFamily:
						'"Source Sans Pro", HelveticaNeue-Light, "Helvetica Neue Light"',
					fontSize: '12px',
					padding: '10px',
				},
				'.Block': {
					padding: '10px',
					backgroundColor: '#ffffff',
				},
			},
			labels: 'above',
		} );
	} );

	test( 'getFieldStyles prioritizes content-area selectors over bare fallback', () => {
		const contentElement = document.createElement( 'a' );
		const bareElement = document.createElement( 'a' );
		const accentColor = 'rgb(0, 102, 204)';
		const navColor = 'rgb(255, 0, 0)';
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

		// Both 'form.checkout a' and bare 'a' match, but the first should win.
		const scope = {
			querySelector: jest.fn( ( selector ) => {
				if ( selector === 'form.checkout a' ) return contentElement;
				if ( selector === 'a' ) return bareElement;
				return null;
			} ),
			defaultView: {
				getComputedStyle: jest.fn( ( el ) =>
					el === contentElement
						? accentStyleDeclaration
						: navStyleDeclaration
				),
			},
		};

		const fieldStyles = upeStyles.getFieldStyles(
			[ 'form.checkout a', '.woocommerce a', 'a' ],
			'.Label',
			null,
			scope
		);

		expect( fieldStyles.color ).toBe( accentColor );
		expect( scope.querySelector ).toHaveBeenCalledWith( 'form.checkout a' );
	} );

	[
		{
			elementsLocation: 'shortcode_checkout',
			expectedSelectors: [
				upeStyles.appearanceSelectors.classicCheckout
					.upeThemeInputSelector,
				upeStyles.appearanceSelectors.classicCheckout
					.upeThemeLabelSelector,
			],
		},
		{
			elementsLocation: 'blocks_checkout',
			expectedSelectors: [
				upeStyles.appearanceSelectors.blocksCheckout
					.upeThemeInputSelector,
				upeStyles.appearanceSelectors.blocksCheckout
					.upeThemeLabelSelector,
			],
		},
		{
			elementsLocation: 'other',
			expectedSelectors: [
				upeStyles.appearanceSelectors.blocksCheckout
					.upeThemeInputSelector,
				upeStyles.appearanceSelectors.blocksCheckout
					.upeThemeLabelSelector,
			],
		},
	].forEach( ( { elementsLocation, expectedSelectors } ) => {
		describe( `when elementsLocation is ${ elementsLocation }`, () => {
			test( 'getAppearance uses the correct appearanceSelectors based on the elementsLocation', () => {
				const scope = {
					querySelector: jest.fn( () => mockElement ),
					createElement: jest.fn( ( htmlTag ) =>
						document.createElement( htmlTag )
					),
					defaultView: {
						getComputedStyle: jest.fn(
							() => mockCSStyleDeclaration
						),
					},
				};

				upeStyles.getAppearance( elementsLocation, false, scope );

				expectedSelectors.forEach( ( selector ) => {
					expect( scope.querySelector ).toHaveBeenCalledWith(
						selector
					);
				} );
			} );
		} );
	} );
} );
