/**
 * Internal dependencies
 */
import { getFieldStyles } from '../index';

describe( 'Getting styles for automated theming', () => {
	test( 'getFieldStyles returns correct styles for inputs', () => {
		const mockInput = document.createElement( 'input' );
		const mockCSSStyleDeclaration = {
			length: 4,
			0: 'color',
			1: 'backgroundColor',
			2: 'fontFamily',
			3: 'unsuportedProperty',
			getPropertyValue: ( propertyName ) => {
				const cssProperties = {
					fontFamily:
						'"Source Sans Pro", HelveticaNeue-Light, "Helvetica Neue Light"',
					color: 'rgb(109, 109, 109)',
					backgroundColor: 'rgba(0, 0, 0, 0)',
					unsuportedProperty: 'some value',
				};
				return cssProperties[ propertyName ];
			},
		};
		jest.spyOn( document, 'querySelector' ).mockImplementation( () => {
			return mockInput;
		} );
		jest.spyOn( window, 'getComputedStyle' ).mockImplementation( () => {
			return mockCSSStyleDeclaration;
		} );

		const fieldStyles = getFieldStyles(
			'.woocommerce-checkout .form-row input',
			'.Input'
		);
		expect( fieldStyles ).toEqual( {
			backgroundColor: 'rgba(0, 0, 0, 0)',
			color: 'rgb(109, 109, 109)',
			fontFamily:
				'"Source Sans Pro", HelveticaNeue-Light, "Helvetica Neue Light"',
		} );
	} );

	test( 'getFieldStyles returns empty object if it can not find the element', () => {
		jest.spyOn( document, 'querySelector' ).mockImplementation( () => {
			return undefined;
		} );
		const fieldStyles = getFieldStyles( '.i-do-not-exist', '.Input' );
		expect( fieldStyles ).toEqual( {} );
	} );
} );
