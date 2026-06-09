/**
 * Internal dependencies
 */
import {
	getPaymentMethodsOverride,
	adjustButtonHeights,
} from '../payment-method-overrides';

describe( 'getPaymentMethodsOverride', () => {
	test( 'enables only applePay with "always" mode', () => {
		const result = getPaymentMethodsOverride( 'applePay' );
		expect( result.paymentMethods.applePay ).toBe( 'always' );
		expect( result.paymentMethods.googlePay ).toBe( 'never' );
		expect( result.paymentMethods.amazonPay ).toBe( 'never' );
	} );

	test( 'enables only googlePay with "always" mode', () => {
		const result = getPaymentMethodsOverride( 'googlePay' );
		expect( result.paymentMethods.googlePay ).toBe( 'always' );
		expect( result.paymentMethods.applePay ).toBe( 'never' );
		expect( result.paymentMethods.amazonPay ).toBe( 'never' );
	} );

	test( 'enables amazonPay with "auto" mode (not "always")', () => {
		const result = getPaymentMethodsOverride( 'amazonPay' );
		expect( result.paymentMethods.amazonPay ).toBe( 'auto' );
		expect( result.paymentMethods.applePay ).toBe( 'never' );
		expect( result.paymentMethods.googlePay ).toBe( 'never' );
	} );

	test( 'disables all other methods (link, paypal, klarna)', () => {
		const result = getPaymentMethodsOverride( 'applePay' );
		expect( result.paymentMethods.link ).toBe( 'never' );
		expect( result.paymentMethods.paypal ).toBe( 'never' );
		expect( result.paymentMethods.klarna ).toBe( 'never' );
	} );
} );

describe( 'adjustButtonHeights', () => {
	const baseOptions = ( overrides = {} ) => ( {
		buttonHeight: 48,
		buttonTheme: {
			applePay: 'black',
			googlePay: 'black',
		},
		buttonType: {
			applePay: 'plain',
			googlePay: 'plain',
		},
		...overrides,
	} );

	test( 'increases Apple Pay height by 0.4px when theme is black', () => {
		const result = adjustButtonHeights( baseOptions(), 'applePay' );
		expect( result.buttonHeight ).toBe( 48.4 );
	} );

	test( 'does not adjust Apple Pay height when theme is not black', () => {
		const result = adjustButtonHeights(
			baseOptions( {
				buttonTheme: { applePay: 'white', googlePay: 'black' },
			} ),
			'applePay'
		);
		expect( result.buttonHeight ).toBe( 48 );
	} );

	test( 'decreases Google Pay height by 2px when theme is white', () => {
		const result = adjustButtonHeights(
			baseOptions( {
				buttonTheme: { applePay: 'black', googlePay: 'white' },
			} ),
			'googlePay'
		);
		expect( result.buttonHeight ).toBe( 46 );
	} );

	test( 'does not adjust Google Pay height when theme is not white', () => {
		const result = adjustButtonHeights( baseOptions(), 'googlePay' );
		expect( result.buttonHeight ).toBe( 48 );
	} );

	test( 'clamps height to minimum 40px', () => {
		const result = adjustButtonHeights(
			baseOptions( {
				buttonHeight: 40,
				buttonTheme: { applePay: 'black', googlePay: 'white' },
			} ),
			'googlePay'
		);
		expect( result.buttonHeight ).toBe( 40 );
	} );

	test( 'clamps height to maximum 55px', () => {
		const result = adjustButtonHeights(
			baseOptions( { buttonHeight: 56 } ),
			'amazonPay'
		);
		expect( result.buttonHeight ).toBe( 55 );
	} );

	test( 'does not mutate the original options object', () => {
		const original = baseOptions();
		adjustButtonHeights( original, 'applePay' );
		expect( original.buttonHeight ).toBe( 48 );
	} );
} );
