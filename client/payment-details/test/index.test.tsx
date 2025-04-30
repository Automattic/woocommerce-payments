/** @format */

/**
 * External dependencies
 */
import { render } from '@testing-library/react';
import { useSelect } from '@wordpress/data';
import React from 'react';

/**
 * Internal dependencies
 */
import PaymentDetailsPage from '..';
import DisputeNotice from '../dispute-details/dispute-notice';
import PAYMENT_METHOD_IDS, {
	PAYMENT_METHOD_BRANDS,
} from 'wcpay/constants/payment-method';
import { Dispute } from 'wcpay/types/disputes';
import { Charge, PaymentMethodDetails } from 'wcpay/types/charges';

declare const global: {
	wcSettings: { countries: Record< string, string > };
	wcpaySettings: {
		accountStatus: {
			country: string;
		};
		zeroDecimalCurrencies: string[];
		featureFlags: Record< string, boolean >;
		connect: {
			country: string;
		};
		dateFormat: string;
		timeFormat: string;
	};
};

// Workaround for mocking @wordpress/data.
// See https://github.com/WordPress/gutenberg/issues/15031
jest.mock( '@wordpress/data', () => ( {
	createRegistryControl: jest.fn(),
	dispatch: jest.fn( () => ( {
		setIsMatching: jest.fn(),
		onLoad: jest.fn(),
	} ) ),
	registerStore: jest.fn(),
	select: jest.fn(),
	combineReducers: jest.fn(),
	useDispatch: jest.fn( () => ( { createNotice: jest.fn() } ) ),
	withDispatch: jest.fn( () => jest.fn() ),
	withSelect: jest.fn( () => jest.fn() ),
	useSelect: jest.fn(),
} ) );

const mockHistoryReplace = jest.fn();
jest.mock( '@woocommerce/navigation', () => ( {
	getQuery: () => {
		return {
			status_is: '',
			type_is: '',
		};
	},
	getHistory: () => ( {
		replace: mockHistoryReplace,
	} ),
	addHistoryListener: jest.fn(),
} ) );

jest.mock( '@woocommerce/data', () => ( {
	useUserPreferences: jest.fn( () => ( {
		updateUserPreferences: jest.fn(),
		wc_payments_wporg_review_2025_prompt_dismissed: false,
	} ) ),
} ) );

const chargeMock = {
	id: 'ch_mock',
	amount: 1500,
	application_fee_amount: 74,
	balance_transaction: {
		amount: 1500,
		currency: 'usd',
		fee: 74,
	},
	billing_details: {
		address: {
			city: 'City',
			country: 'US',
			line1: 'Line 1 Street',
			line2: 'Line 2',
			postal_code: '99999',
			state: 'CA',
		},
		email: 'email@example.com',
		name: 'First Name',
		phone: '1-000-000-0000',
		formatted_address: 'Line 1 Street<br/>Line 2<br/>City, CA 99999',
	},
	captured: true,
	created: 1655921807,
	currency: 'usd',
	customer: 'cus_mock',
	dispute: null,
	disputed: false,
	paid: true,
	payment_intent: 'pi_mock',
	payment_method: 'pm_mock',
	payment_method_details: {
		card: {
			brand: 'visa',
			checks: {
				address_line1_check: 'pass',
				address_postal_code_check: 'pass',
				cvc_check: 'pass',
			},
			country: 'US',
			exp_month: 12,
			exp_year: 2099,
			fingerprint: 'fingerprint',
			funding: 'credit',
			last4: '4242',
			network: 'visa',
		},
		type: 'card',
	},
	refunded: false,
	refunds: {},
	status: 'succeeded',
};

( useSelect as jest.Mock ).mockImplementation( ( cb ) =>
	cb(
		jest.fn().mockReturnValue( {
			getCharge: jest.fn().mockReturnValue( chargeMock ),
			isResolving: jest.fn().mockReturnValue( false ),
			hasFinishedResolution: jest.fn().mockReturnValue( true ),
			getChargeError: jest.fn().mockReturnValue( null ),
			getPaymentIntent: jest.fn().mockReturnValue( {
				id: 'pi_mock',
				charge: chargeMock,
			} ),
			getPaymentIntentError: jest.fn().mockReturnValue( null ),
			getTimeline: jest.fn().mockReturnValue( {} ),
			getTimelineError: jest.fn().mockReturnValue( null ),
			getAuthorization: jest.fn().mockReturnValue( {
				created: '2022-09-27 17:07:09',
			} ),
			getIsRequesting: jest.fn().mockReturnValue( false ),
		} )
	)
);

global.wcSettings = {
	countries: {
		US: 'United States of America',
	},
};

global.wcpaySettings = {
	accountStatus: {
		country: 'US',
	},
	featureFlags: { paymentTimeline: true },
	zeroDecimalCurrencies: [ 'usd' ],
	connect: { country: 'US' },
	dateFormat: 'M j, Y',
	timeFormat: 'g:ia',
};

describe( 'Payment details page', () => {
	const { location } = window;

	const chargeQuery = { id: 'ch_mock' };
	const paymentIntentQuery = { id: 'pi_mock' };
	const redirectUrl =
		'admin.php?page=wc-admin&path=%2Fpayments%2Ftransactions%2Fdetails&id=pi_mock';

	beforeEach( () => {
		Object.defineProperty( window, 'location', {
			value: { href: 'http://example.com' },
		} );
		mockHistoryReplace.mockReset();
	} );

	afterAll( () => {
		Object.defineProperty( window, 'location', {
			configurable: true,
			value: location,
		} );
	} );

	it( 'should match the snapshot - Payment Intent query param', () => {
		const { container } = render(
			<PaymentDetailsPage query={ paymentIntentQuery } />
		);

		expect( container ).toMatchSnapshot();

		// eslint-disable-next-line @typescript-eslint/ban-ts-comment
		// @ts-ignore
		expect( console ).toHaveWarnedWith(
			'List with items prop is deprecated is deprecated and will be removed in version 9.0.0. Note: See ExperimentalList / ExperimentalListItem for the new API that will replace this component in future versions.'
		);
	} );

	it( 'should match the snapshot - Charge query param', () => {
		const { container } = render(
			<PaymentDetailsPage query={ chargeQuery } />
		);

		expect( container ).toMatchSnapshot();
	} );

	it( 'should redirect from ch_mock to pi_mock', () => {
		render( <PaymentDetailsPage query={ chargeQuery } /> );

		expect( mockHistoryReplace ).toHaveBeenCalledWith( redirectUrl );
	} );

	it( 'should not redirect with a payment intent ID as query param', () => {
		render( <PaymentDetailsPage query={ paymentIntentQuery } /> );

		expect( mockHistoryReplace ).not.toHaveBeenCalled();
	} );
} );

describe( 'DisputeNotice bank name logic', () => {
	// Mock dispute object with different payment methods
	const createDisputeWithPaymentMethod = (
		paymentMethodType: string,
		paymentMethodDetails: Partial< PaymentMethodDetails >
	): Partial< Dispute > => {
		return {
			id: 'dp_mock',
			charge: {
				id: 'ch_mock',
				payment_method_details: paymentMethodDetails as PaymentMethodDetails,
			} as Charge,
			reason: 'fraudulent',
			status: 'needs_response',
			evidence_details: {
				due_by: 1655921807,
				has_evidence: false,
				past_due: false,
				submission_count: 0,
			},
			amount: 1000,
			currency: 'usd',
			created: 1655921807,
			balance_transactions: [],
			payment_intent: 'pi_mock',
			metadata: {},
			order: null,
			evidence: {},
			issuer_evidence: null,
		};
	};

	it( 'should return null when charge is a string ID', () => {
		const dispute = {
			id: 'dp_mock',
			charge: 'ch_mock',
			reason: 'fraudulent',
			status: 'needs_response',
			evidence_details: {
				due_by: 1655921807,
				has_evidence: false,
				past_due: false,
				submission_count: 0,
			},
			amount: 1000,
			currency: 'usd',
			created: 1655921807,
			balance_transactions: [],
			payment_intent: 'pi_mock',
			metadata: {},
			order: null,
			evidence: {},
			issuer_evidence: null,
		} as Dispute;

		const { container } = render(
			<DisputeNotice
				dispute={ dispute }
				isUrgent={ false }
				paymentMethod={ 'card' }
				bankName={ null }
			/>
		);

		// The notice should not contain a bank name
		expect( container.textContent ).not.toContain( 'Chase Bank' );
	} );

	it( 'should return bank name for giropay payment method', () => {
		const dispute = createDisputeWithPaymentMethod( 'giropay', {
			giropay: {
				bank_name: 'Deutsche Bank',
			},
			type: PAYMENT_METHOD_IDS.GIROPAY,
		} ) as Dispute;

		const { container } = render(
			<DisputeNotice
				dispute={ dispute }
				isUrgent={ false }
				paymentMethod={ 'giropay' }
				bankName={ 'Deutsche Bank' }
			/>
		);

		expect( container.textContent ).toContain( 'Deutsche Bank' );
	} );

	it( 'should return bank name for bancontact payment method', () => {
		const dispute = createDisputeWithPaymentMethod( 'bancontact', {
			bancontact: {
				bank_name: 'ING Bank',
			},
			type: PAYMENT_METHOD_IDS.BANCONTACT,
		} ) as Dispute;

		const { container } = render(
			<DisputeNotice
				dispute={ dispute }
				isUrgent={ false }
				paymentMethod={ 'bancontact' }
				bankName={ 'ING Bank' }
			/>
		);

		expect( container.textContent ).toContain( 'ING Bank' );
	} );

	it( 'should return bank name for sofort payment method', () => {
		const dispute = createDisputeWithPaymentMethod( 'sofort', {
			sofort: {
				bank_name: 'Commerzbank',
			},
			type: PAYMENT_METHOD_IDS.SOFORT,
		} ) as Dispute;

		const { container } = render(
			<DisputeNotice
				dispute={ dispute }
				isUrgent={ false }
				paymentMethod={ 'sofort' }
				bankName={ 'Commerzbank' }
			/>
		);

		expect( container.textContent ).toContain( 'Commerzbank' );
	} );

	it( 'should return brand name for card payment method', () => {
		const dispute = createDisputeWithPaymentMethod( 'card', {
			card: {
				brand: 'visa',
				issuer: 'Chase Bank',
			},
			type: PAYMENT_METHOD_IDS.CARD,
		} ) as Dispute;

		const { container } = render(
			<DisputeNotice
				dispute={ dispute }
				isUrgent={ false }
				paymentMethod={ 'card' }
				bankName={ 'Chase Bank' }
			/>
		);

		expect( container.textContent ).toContain( 'Chase Bank' );
	} );

	it( 'should return null for card payment method with invalid brand', () => {
		const dispute = createDisputeWithPaymentMethod( 'card', {
			card: {
				brand: 'invalid_brand',
			},
			type: PAYMENT_METHOD_IDS.CARD,
		} ) as Dispute;

		const { container } = render(
			<DisputeNotice
				dispute={ dispute }
				isUrgent={ false }
				paymentMethod={ 'card' }
				bankName={ null }
			/>
		);

		// The notice should not contain a bank name
		expect( container.textContent ).not.toContain( 'invalid_brand' );
	} );

	it( 'should return null for unknown payment method', () => {
		const dispute = createDisputeWithPaymentMethod( 'card', {
			card: {
				brand: 'invalid_brand',
			},
			type: PAYMENT_METHOD_IDS.CARD,
		} ) as Dispute;

		const { container } = render(
			<DisputeNotice
				dispute={ dispute }
				isUrgent={ false }
				paymentMethod={ 'unknown' }
				bankName={ null }
			/>
		);

		// The notice should not contain a bank name
		expect( container.textContent ).not.toContain( 'invalid_brand' );
	} );

	it( 'should return null when payment method is null', () => {
		const dispute = createDisputeWithPaymentMethod( 'card', {
			card: {
				brand: 'visa',
			},
			type: PAYMENT_METHOD_IDS.CARD,
		} ) as Dispute;

		const { container } = render(
			<DisputeNotice
				dispute={ dispute }
				isUrgent={ false }
				paymentMethod={ null }
				bankName={ null }
			/>
		);

		// The notice should not contain a bank name
		expect( container.textContent ).not.toContain(
			PAYMENT_METHOD_BRANDS.VISA
		);
	} );
} );
