/**
 * Internal dependencies
 */
import * as CheckoutUtils from 'utils/checkout';
import { getSetupIntentFromSession } from '../upe-split';

import {
	getSelectedUPEGatewayPaymentMethod,
	isUsingSavedPaymentMethod,
} from 'wcpay/checkout/utils/upe';

describe( 'UPE split checkout', () => {
	describe( 'isUsingSavedPaymentMethod', () => {
		let container;

		beforeAll( () => {
			container = document.createElement( 'div' );
			container.innerHTML = `
				<label>
					<input type="radio" id="wc-woocommerce_payments-payment-token-new" value="new">
					Use a new payment method
				</label>
				<label>
					<input type="radio" id="wc-woocommerce_payments_sepa_debit-payment-token-new" value="new">
					Use a new payment method
				</label>
			`;
			document.body.appendChild( container );
		} );

		afterAll( () => {
			document.body.removeChild( container );
			container = null;
		} );

		test( 'new CC is selected', () => {
			const input = document.querySelector(
				'#wc-woocommerce_payments-payment-token-new'
			);
			input.checked = true;
			const paymentMethodType = 'card';

			expect( isUsingSavedPaymentMethod( paymentMethodType ) ).toBe(
				false
			);
		} );

		test( 'saved CC is selected', () => {
			const input = document.querySelector(
				'#wc-woocommerce_payments-payment-token-new'
			);
			input.checked = false;
			const paymentMethodType = 'card';

			expect( isUsingSavedPaymentMethod( paymentMethodType ) ).toBe(
				true
			);
		} );

		test( 'new SEPA is selected', () => {
			const input = document.querySelector(
				'#wc-woocommerce_payments_sepa_debit-payment-token-new'
			);
			input.checked = true;
			const paymentMethodType = 'sepa_debit';

			expect( isUsingSavedPaymentMethod( paymentMethodType ) ).toBe(
				false
			);
		} );

		test( 'saved SEPA is selected', () => {
			const input = document.querySelector(
				'#wc-woocommerce_payments_sepa_debit-payment-token-new'
			);
			input.checked = false;
			const paymentMethodType = 'sepa_debit';

			expect( isUsingSavedPaymentMethod( paymentMethodType ) ).toBe(
				true
			);
		} );

		test( 'non-tokenized payment gateway is selected', () => {
			const paymentMethodType = 'sofort';

			expect( isUsingSavedPaymentMethod( paymentMethodType ) ).toBe(
				false
			);
		} );
	} );

	describe( 'getSelectedUPEGatewayPaymentMethod', () => {
		let container;
		let input;

		beforeAll( () => {
			container = document.createElement( 'div' );
			container.innerHTML = `
			<ul class="wc_payment_methods payment_methods methods">
				<li class="wc_payment_method payment_method_woocommerce_payments">
					<input id="payment_method_woocommerce_payments" type="radio" class="input-radio">
				</li>
				<li class="wc_payment_method payment_method_woocommerce_payments_bancontact">
					<input id="payment_method_woocommerce_payments_bancontact" type="radio" class="input-radio">
				</li>
			</ul>
			`;
			document.body.appendChild( container );
		} );

		beforeEach( () => {
			const spy = jest.spyOn( CheckoutUtils, 'getUPEConfig' );
			spy.mockImplementation( ( param ) => {
				if ( param === 'paymentMethodsConfig' ) {
					return { card: {}, bancontact: {} };
				}
				if ( param === 'gatewayId' ) {
					return 'woocommerce_payments';
				}
			} );
		} );

		afterEach( () => {
			input.checked = false;
			jest.clearAllMocks();
		} );

		afterAll( () => {
			document.body.removeChild( container );
			container = null;
		} );

		test( 'Selected UPE Payment Method is card', () => {
			input = document.querySelector(
				'#payment_method_woocommerce_payments'
			);
			input.checked = true;

			expect( getSelectedUPEGatewayPaymentMethod() ).toBe( 'card' );
		} );

		test( 'Selected UPE Payment Method is bancontact', () => {
			input = document.querySelector(
				'#payment_method_woocommerce_payments_bancontact'
			);
			input.checked = true;

			expect( getSelectedUPEGatewayPaymentMethod() ).toBe( 'bancontact' );
		} );
	} );

	describe( 'getSetupIntentFromSession', () => {
		beforeEach( () => {
			const spy = jest.spyOn( CheckoutUtils, 'getUPEConfig' );
			spy.mockReturnValue( {
				card: {
					upeSetupIntentData: 'card-1234',
				},
				bancontact: {
					upeSetupIntentData: 'bancontact-5678',
				},
				eps: {
					upeSetupIntentData: null,
				},
			} );
		} );

		afterEach( () => {
			jest.clearAllMocks();
		} );

		const cardData = { clientSecret: '1234', intentId: 'card' };
		const bancontactData = { clientSecret: '5678', intentId: 'bancontact' };

		test( 'Get setup intent data from for Card method', () => {
			expect( getSetupIntentFromSession( 'card' ) ).toMatchObject(
				cardData
			);
		} );

		test( 'Get setup intent data from for UPE method', () => {
			expect( getSetupIntentFromSession( 'bancontact' ) ).toMatchObject(
				bancontactData
			);
		} );

		test( 'Get null setup intent data returns empty object', () => {
			expect( getSetupIntentFromSession( 'eps' ) ).toMatchObject( {} );
		} );
	} );
} );
