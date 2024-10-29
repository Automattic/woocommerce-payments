/**
 * Internal dependencies
 */
import {
	getTerms,
	generateCheckoutEventNames,
	getUpeSettings,
	getStripeElementOptions,
	blocksShowLinkButtonHandler,
	getSelectedUPEGatewayPaymentMethod,
	hasPaymentMethodCountryRestrictions,
	isUsingSavedPaymentMethod,
	dispatchChangeEventFor,
	togglePaymentMethodForCountry,
} from '../upe';
import { getPaymentMethodsConstants } from '../../constants';
import { getUPEConfig } from 'wcpay/utils/checkout';

jest.mock( 'wcpay/utils/checkout' );

jest.mock( '../../constants', () => {
	return {
		getPaymentMethodsConstants: jest.fn(),
	};
} );

describe( 'UPE checkout utils', () => {
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
			getUPEConfig.mockImplementation( ( argument ) => {
				if ( argument === 'paymentMethodsConfig' ) {
					return { card: {}, bancontact: {} };
				}

				if ( argument === 'gatewayId' ) {
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

	describe( 'getTerms', () => {
		const paymentMethods = {
			card: {
				isReusable: true,
			},
			bancontact: {
				isReusable: true,
			},
			eps: {
				isReusable: true,
			},
			giropay: {
				isReusable: false,
			},
		};

		const terms = {
			always: {
				card: 'always',
				bancontact: 'always',
				eps: 'always',
			},
			never: {
				card: 'never',
				bancontact: 'never',
				eps: 'never',
			},
		};

		it( 'should only generate a terms parameter for reusable payment methods', () => {
			expect( getTerms( paymentMethods, 'always' ) ).toEqual(
				terms.always
			);
		} );

		it( 'should use a specified value for the terms parameter', () => {
			expect( getTerms( paymentMethods, 'never' ) ).toEqual(
				terms.never
			);
		} );
	} );

	describe( 'hasPaymentMethodCountryRestrictions', () => {
		let container;

		beforeAll( () => {
			container = document.createElement( 'div' );
			container.innerHTML = `
				<ul class="wc_payment_methods payment_methods methods">
					<li class="wc_payment_method payment_method_woocommerce_payments_card" data-payment-method-type="card">
						<input id="payment_method_woocommerce_payments" type="radio" class="input-radio">
					</li>
					<li class="wc_payment_method payment_method_woocommerce_payments_bancontact" data-payment-method-type="bancontact">
						<input id="payment_method_woocommerce_payments_bancontact" type="radio" class="input-radio">
					</li>
				</ul>
			`;
			document.body.appendChild( container );
		} );

		afterAll( () => {
			document.body.removeChild( container );
			container = null;
		} );

		beforeEach( () => {
			jest.clearAllMocks();
			getUPEConfig.mockImplementation( ( argument ) => {
				if ( argument === 'paymentMethodsConfig' ) {
					return {
						card: { countries: [] },
						bancontact: { countries: [ 'BE' ] },
					};
				}
			} );
		} );

		it( 'should be true when the payment method is restricted to the location', () => {
			const bancontactUpeElement = document.querySelector(
				'.payment_method_woocommerce_payments_bancontact'
			);

			expect(
				hasPaymentMethodCountryRestrictions( bancontactUpeElement )
			).toBe( true );
		} );

		it( 'should be false when the payment method is not restricted to the location', () => {
			const cardUpeElement = document.querySelector(
				'.payment_method_woocommerce_payments_card'
			);

			expect(
				hasPaymentMethodCountryRestrictions( cardUpeElement )
			).toBe( false );
		} );
	} );

	describe( 'togglePaymentMethodForCountry', () => {
		let container;

		beforeAll( () => {
			container = document.createElement( 'div' );
			container.innerHTML = `
				<select id="billing_country">
					<option value="US">United States</option>
					<option value="BE">Belgium</option>
				</select>
				<ul class="wc_payment_methods payment_methods methods">
					<li class="wc_payment_method payment_method_woocommerce_payments_card" data-payment-method-type="card">
						<input id="payment_method_woocommerce_payments" type="radio" class="input-radio">
					</li>
					<li class="wc_payment_method payment_method_woocommerce_payments_bancontact" data-payment-method-type="bancontact">
						<input id="payment_method_woocommerce_payments_bancontact" type="radio" class="input-radio">
					</li>
				</ul>
			`;
			document.body.appendChild( container );
		} );

		afterAll( () => {
			document.body.removeChild( container );
			container = null;
		} );

		beforeEach( () => {
			jest.clearAllMocks();
			getUPEConfig.mockImplementation( ( argument ) => {
				if ( argument === 'paymentMethodsConfig' ) {
					return {
						card: { countries: [ 'US' ] },
						bancontact: { countries: [ 'BE' ] },
					};
				}

				if ( argument === 'gatewayId' ) {
					return 'woocommerce_payments';
				}
			} );
			window.wcpayCustomerData = { billing_country: 'BE' };
		} );

		afterEach( () => {
			window.wcpayCustomerData = null;
		} );

		it( 'should show payment method if country is supported', () => {
			const upeElement = document.querySelector(
				'.payment_method_woocommerce_payments_card'
			);
			document.getElementById( 'billing_country' ).value = 'US';

			togglePaymentMethodForCountry( upeElement );

			expect( upeElement.style.display ).toBe( '' );
		} );

		it( 'should hide payment method if country is not supported', () => {
			const upeElement = document.querySelector(
				'.payment_method_woocommerce_payments_card'
			);
			document.getElementById( 'billing_country' ).value = 'BE';

			togglePaymentMethodForCountry( upeElement );

			expect( upeElement.style.display ).toBe( 'none' );
		} );

		it( 'should fall back to card as the default payment method if the selected payment method is toggled off', () => {
			const input = document.querySelector(
				'#payment_method_woocommerce_payments_bancontact'
			);
			input.checked = true;

			const upeElement = document.querySelector(
				'.payment_method_woocommerce_payments_bancontact'
			);
			document.getElementById( 'billing_country' ).value = 'US';

			const cardPaymentMethod = document.querySelector(
				'#payment_method_woocommerce_payments'
			);
			jest.spyOn( cardPaymentMethod, 'click' );

			togglePaymentMethodForCountry( upeElement );

			expect( upeElement.style.display ).toBe( 'none' );
			expect( cardPaymentMethod.click ).toHaveBeenCalled();
		} );
	} );

	describe( 'getUPESettings', () => {
		afterEach( () => {
			const checkboxElement = document.getElementById(
				'wc-woocommerce_payments-new-payment-method'
			);
			if ( checkboxElement ) {
				checkboxElement.remove();
			}
		} );

		it( 'should not provide terms when cart does not contain subscriptions and the saving checkbox is unchecked', () => {
			getUPEConfig.mockImplementation( ( argument ) => {
				if ( argument === 'paymentMethodsConfig' ) {
					return {
						card: {
							label: 'Card',
							isReusable: true,
						},
					};
				}

				if ( argument === 'cartContainsSubscription' ) {
					return false;
				}
			} );

			createCheckboxElementWhich( false );

			const upeSettings = getUpeSettings();

			expect( upeSettings.terms.card ).toEqual( 'never' );
		} );

		it( 'should provide terms when cart does not contain subscriptions but the saving checkbox is checked', () => {
			getUPEConfig.mockImplementation( ( argument ) => {
				if ( argument === 'paymentMethodsConfig' ) {
					return {
						card: {
							label: 'Card',
							isReusable: true,
						},
					};
				}

				if ( argument === 'cartContainsSubscription' ) {
					return false;
				}
			} );

			createCheckboxElementWhich( true );

			const upeSettings = getUpeSettings();

			// console.log(result);
			expect( upeSettings.terms.card ).toEqual( 'always' );
		} );

		it( 'should provide terms when cart contains subscriptions but the saving checkbox is unchecked', () => {
			getUPEConfig.mockImplementation( ( argument ) => {
				if ( argument === 'paymentMethodsConfig' ) {
					return {
						card: {
							label: 'Card',
							isReusable: true,
						},
					};
				}

				if ( argument === 'cartContainsSubscription' ) {
					return true;
				}
			} );

			createCheckboxElementWhich( false );
			const upeSettings = getUpeSettings();

			expect( upeSettings.terms.card ).toEqual( 'always' );
		} );

		function createCheckboxElementWhich( isChecked ) {
			// Create the checkbox element
			const checkboxElement = document.createElement( 'input' );
			checkboxElement.type = 'checkbox';
			checkboxElement.checked = isChecked;
			checkboxElement.id = 'wc-woocommerce_payments-new-payment-method';

			document.body.appendChild( checkboxElement );
		}
	} );

	describe( 'generateCheckoutEventNames', () => {
		it( 'should return empty string when there are no payment methods', () => {
			getPaymentMethodsConstants.mockImplementation( () => [] );

			const result = generateCheckoutEventNames();

			expect( result ).toEqual( '' );
		} );

		it( 'should generate correct event names when there are payment methods', () => {
			getPaymentMethodsConstants.mockImplementation( () => [
				'woocommerce_payments_bancontact',
				'woocommerce_payments_eps',
			] );

			const result = generateCheckoutEventNames();

			expect( result ).toEqual(
				'checkout_place_order_woocommerce_payments_bancontact checkout_place_order_woocommerce_payments_eps'
			);
		} );
	} );
} );

describe( 'getStripeElementOptions', () => {
	test( 'should return options with "always" terms for cart containing subscription', () => {
		const shouldSavePayment = false;
		getUPEConfig.mockImplementation( ( argument ) => {
			if ( argument === 'cartContainsSubscription' ) {
				return true;
			}
		} );
		const paymentMethodsConfig = {
			card: {
				isReusable: true,
			},
			bancontact: {
				isReusable: true,
			},
			eps: {
				isReusable: true,
			},
			giropay: {
				isReusable: false,
			},
		};

		const options = getStripeElementOptions(
			shouldSavePayment,
			paymentMethodsConfig
		);

		expect( options ).toEqual( {
			fields: {
				billingDetails: {
					address: {
						city: 'never',
						country: 'never',
						line1: 'never',
						line2: 'never',
						postalCode: 'never',
						state: 'never',
					},
					email: 'never',
					name: 'never',
					phone: 'never',
				},
			},
			terms: { bancontact: 'always', card: 'always', eps: 'always' },
			wallets: { applePay: 'never', googlePay: 'never' },
		} );
	} );

	test( 'should return options with "always" terms when checkbox to save payment method is checked', () => {
		const shouldSavePayment = true;
		getUPEConfig.mockImplementation( ( argument ) => {
			if ( argument === 'cartContainsSubscription' ) {
				return false;
			}
		} );
		const paymentMethodsConfig = {
			card: {
				isReusable: true,
			},
			bancontact: {
				isReusable: true,
			},
			eps: {
				isReusable: true,
			},
			giropay: {
				isReusable: false,
			},
		};

		const options = getStripeElementOptions(
			shouldSavePayment,
			paymentMethodsConfig
		);

		expect( options ).toEqual( {
			fields: {
				billingDetails: {
					address: {
						city: 'never',
						country: 'never',
						line1: 'never',
						line2: 'never',
						postalCode: 'never',
						state: 'never',
					},
					email: 'never',
					name: 'never',
					phone: 'never',
				},
			},
			terms: { bancontact: 'always', card: 'always', eps: 'always' },
			wallets: { applePay: 'never', googlePay: 'never' },
		} );
	} );

	test( 'should return options with "never" for terms when shouldSavePayment is false and no subscription in cart', () => {
		const shouldSavePayment = false;
		const paymentMethodsConfig = {
			card: {
				isReusable: true,
			},
		};

		getUPEConfig.mockImplementation( ( argument ) => {
			if ( argument === 'cartContainsSubscription' ) {
				return false;
			}
		} );

		const options = getStripeElementOptions(
			shouldSavePayment,
			paymentMethodsConfig
		);

		expect( options ).toEqual( {
			fields: {
				billingDetails: {
					address: {
						city: 'never',
						country: 'never',
						line1: 'never',
						line2: 'never',
						postalCode: 'never',
						state: 'never',
					},
					email: 'never',
					name: 'never',
					phone: 'never',
				},
			},
			terms: { card: 'never' },
			wallets: { applePay: 'never', googlePay: 'never' },
		} );
	} );
} );

describe( 'blocksShowLinkButtonHandler', () => {
	let container;
	const autofill = {
		launch: ( props ) => {
			return props.email;
		},
	};

	beforeEach( () => {
		container = document.createElement( 'div' );
		container.innerHTML = `
			<input id="email" type="email" value="">
			<label for="email">Email address</label>
		`;
		document.body.appendChild( container );
	} );

	afterEach( () => {
		document.body.removeChild( container );
		container = null;
	} );

	test( 'should hide link button if email input is empty', () => {
		blocksShowLinkButtonHandler( autofill );

		const stripeLinkButton = document.querySelector(
			'.wcpay-stripelink-modal-trigger'
		);
		expect( stripeLinkButton ).toBeDefined();
		expect( stripeLinkButton.style.display ).toEqual( 'none' );
	} );

	test( 'should show link button if email input is present', () => {
		document.getElementById( 'email' ).value = 'admin@example.com';

		blocksShowLinkButtonHandler( autofill );

		const stripeLinkButton = document.querySelector(
			'.wcpay-stripelink-modal-trigger'
		);
		expect( stripeLinkButton ).toBeDefined();
		expect( stripeLinkButton.style.display ).toEqual( 'inline-block' );
	} );
} );

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

		expect( isUsingSavedPaymentMethod( paymentMethodType ) ).toBe( false );
	} );

	test( 'saved CC is selected', () => {
		const input = document.querySelector(
			'#wc-woocommerce_payments-payment-token-new'
		);
		input.checked = false;
		const paymentMethodType = 'card';

		expect( isUsingSavedPaymentMethod( paymentMethodType ) ).toBe( true );
	} );

	test( 'new SEPA is selected', () => {
		const input = document.querySelector(
			'#wc-woocommerce_payments_sepa_debit-payment-token-new'
		);
		input.checked = true;
		const paymentMethodType = 'sepa_debit';

		expect( isUsingSavedPaymentMethod( paymentMethodType ) ).toBe( false );
	} );

	test( 'saved SEPA is selected', () => {
		const input = document.querySelector(
			'#wc-woocommerce_payments_sepa_debit-payment-token-new'
		);
		input.checked = false;
		const paymentMethodType = 'sepa_debit';

		expect( isUsingSavedPaymentMethod( paymentMethodType ) ).toBe( true );
	} );

	test( 'non-tokenized payment gateway is selected', () => {
		const paymentMethodType = 'sofort';

		expect( isUsingSavedPaymentMethod( paymentMethodType ) ).toBe( false );
	} );
} );

describe( 'dispatching change event for element', () => {
	it( 'should dispatch a change event with bubbling', () => {
		const mockElement = document.createElement( 'input' );
		jest.spyOn( mockElement, 'dispatchEvent' );

		dispatchChangeEventFor( mockElement );

		expect( mockElement.dispatchEvent ).toHaveBeenCalledWith(
			expect.objectContaining( {
				type: 'change',
				bubbles: true,
			} )
		);
	} );

	it( 'should throw an error when called with an invalid element', () => {
		expect( () => {
			dispatchChangeEventFor( null );
		} ).toThrow();

		expect( () => {
			dispatchChangeEventFor( undefined );
		} ).toThrow();

		expect( () => {
			dispatchChangeEventFor( {} );
		} ).toThrow();

		expect( () => {
			dispatchChangeEventFor( 'not-an-element' );
		} ).toThrow();
	} );
} );
