/**
 * Internal dependencies
 */
import * as upeStyles from '../index';

describe( 'Getting styles for automated theming', () => {
	const mockElement = document.createElement( 'input' );
	const mockCSStyleDeclaration = {
		length: 8,
		0: 'color',
		1: 'backgroundColor',
		2: 'fontFamily',
		3: 'unsuportedProperty',
		4: 'outlineColor',
		5: 'outlineWidth',
		6: 'fontSize',
		7: 'padding',
		getPropertyValue: ( propertyName ) => {
			const cssProperties = {
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
			return cssProperties[ propertyName ];
		},
	};

	test( 'getFieldStyles returns correct styles for inputs', () => {
		jest.spyOn( document, 'querySelector' ).mockImplementation( () => {
			return mockElement;
		} );
		jest.spyOn( window, 'getComputedStyle' ).mockImplementation( () => {
			return mockCSStyleDeclaration;
		} );

		const fieldStyles = upeStyles.getFieldStyles(
			'.woocommerce-checkout .form-row input',
			'.Input'
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
		jest.spyOn( document, 'querySelector' ).mockImplementation( () => {
			return undefined;
		} );

		const fieldStyles = upeStyles.getFieldStyles(
			'.i-do-not-exist',
			'.Input'
		);
		expect( fieldStyles ).toEqual( {} );
	} );

	test( 'getFontRulesFromPage returns font rules from allowed font providers', () => {
		const mockStyleSheets = {
			length: 3,
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
		jest.spyOn( document, 'styleSheets', 'get' ).mockReturnValue(
			mockStyleSheets
		);

		const fontRules = upeStyles.getFontRulesFromPage();
		expect( fontRules ).toEqual( [] );
	} );

	test( 'getAppearance returns the object with filtered CSS rules for UPE theming', () => {
		jest.spyOn( document, 'querySelector' ).mockImplementation( () => {
			return mockElement;
		} );
		jest.spyOn( window, 'getComputedStyle' ).mockImplementation( () => {
			return mockCSStyleDeclaration;
		} );

		const appearance = upeStyles.getAppearance(
			'shortcode_checkout',
			true
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
				'.Heading': {
					color: 'rgb(109, 109, 109)',
					fontFamily:
						'"Source Sans Pro", HelveticaNeue-Light, "Helvetica Neue Light"',
					fontSize: '12px',
					padding: '10px',
				},
				'.Button': {
					backgroundColor: 'rgba(0, 0, 0, 0)',
					color: 'rgb(109, 109, 109)',
					fontFamily:
						'"Source Sans Pro", HelveticaNeue-Light, "Helvetica Neue Light"',
					fontSize: '12px',
					outline: '1px solid rgb(150, 88, 138)',
					padding: '10px',
				},
				'.Link': {
					color: 'rgb(109, 109, 109)',
					fontFamily:
						'"Source Sans Pro", HelveticaNeue-Light, "Helvetica Neue Light"',
					fontSize: '12px',
					padding: '10px',
				},
				'.Container': {
					backgroundColor: 'rgba(0, 0, 0, 0)',
				},
			},
		} );
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
		afterEach( () => {
			document.querySelector.mockClear();
		} );

		describe( `when elementsLocation is ${ elementsLocation }`, () => {
			test( 'getAppearance uses the correct appearanceSelectors based on the elementsLocation', () => {
				jest.spyOn( document, 'querySelector' ).mockImplementation(
					() => mockElement
				);
				jest.spyOn( window, 'getComputedStyle' ).mockImplementation(
					() => mockCSStyleDeclaration
				);

				upeStyles.getAppearance( elementsLocation );

				expectedSelectors.forEach( ( selector ) => {
					expect( document.querySelector ).toHaveBeenCalledWith(
						selector
					);
				} );
			} );
		} );
	} );
} );
