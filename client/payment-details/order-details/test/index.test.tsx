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
import PaymentOrderDetails from '..';
import { chargeMock } from '../../../data/payment-intents/test/hooks';
import { STORE_NAME } from '../../../data/constants';
import {
	useAuthorization,
	useChargeFromOrder,
	useTimeline,
} from '../../../data';
import { ApiError } from '../../../types/errors';

declare const global: {
	wcSettings: { countries: Record< string, string > };
	wcpaySettings: {
		zeroDecimalCurrencies: string[];
		featureFlags: Record< string, boolean >;
		connect: {
			country: string;
		};
	};
};

const mockTimeline = [
	{
		type: 'fraud_outcome_block',
		datetime: 1679911782,
		ruleset_results: { purchase_price_threshold: 'block' },
	},
];

const chargeFromOrderMock = {
	id: '776',
	amount: 1500,
	amount_captured: 0,
	amount_refunded: 0,
	application_fee_amount: 0,
	balance_transaction: { currency: 'USD', amount: 1500, fee: 0 },
	billing_details: {
		address: {
			city: 'San Francisco',
			country: 'US',
			line1: '60 29th street',
			line2: '',
			postal_code: '91140',
			state: 'CA',
		},
		email: 'admin_test_example@email.com',
		name: 'First Last',
		phone: '20000000000',
		formatted_address: '60 29th street<br/>San Francisco, CA 91140',
	},
	created: 1679922581,
	currency: 'USD',
	disputed: false,
	outcome: null,
	order: {
		number: 776,
		url: 'http://wcpay.test/wp-admin/post.php?post=776&action=edit',
		customer_url:
			'admin.php?page=wc-admin&path=/customers&filter=single_customer&customers=55',
		subscriptions: [],
		fraud_meta_box_type: 'succeeded',
	},
	paid: false,
	paydown: null,
	payment_method: '',
	payment_intent: null,
	payment_method_details: {
		card: { country: 'US', checks: [], network: '' },
		type: 'card' as any,
	},
	refunded: false,
	refunds: null,
	status: 'pending',
};

jest.mock( 'data/index', () => ( {
	useChargeFromOrder: jest.fn(),
	useAuthorization: jest.fn(),
	useTimeline: jest.fn(),
} ) );

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

const mockUseChargeFromOrder = useChargeFromOrder as jest.MockedFunction<
	typeof useChargeFromOrder
>;

const mockUseAuthorization = useAuthorization as jest.MockedFunction<
	typeof useAuthorization
>;

const mockUseTimeline = useTimeline as jest.MockedFunction<
	typeof useTimeline
>;

describe( 'Order details page', () => {
	const { location } = window;

	const orderId = '42';

	const redirectUrl =
		'admin.php?page=wc-admin&path=%2Fpayments%2Ftransactions%2Fdetails&id=pi_mock';

	let selectors: Record< string, () => any >;

	beforeEach( () => {
		selectors = {};

		global.wcSettings = {
			countries: {
				US: 'United States of America',
			},
		};

		global.wcpaySettings = {
			featureFlags: { paymentTimeline: true },
			zeroDecimalCurrencies: [],
			connect: { country: 'US' },
		};

		const selectMock = jest.fn( ( storeName ) =>
			STORE_NAME === storeName ? selectors : {}
		);

		( useSelect as jest.Mock ).mockImplementation(
			( cb: ( callback: any ) => jest.Mock ) => cb( selectMock )
		);

		Object.defineProperty( window, 'location', {
			value: { href: 'http://example.com' },
		} );

		mockUseAuthorization.mockReturnValue( {
			authorization: {
				created: '2022-09-27 17:07:09',
			} as any,
			doCaptureAuthorization: jest.fn(),
			doCancelAuthorization: jest.fn(),
			isLoading: false,
			isRequesting: false,
		} );
	} );

	afterAll( () => {
		window.location = location;
	} );

	it( 'should match the snapshot - Charge without payment intent', () => {
		mockUseChargeFromOrder.mockReturnValue( {
			data: chargeFromOrderMock,
			error: {} as ApiError,
			isLoading: false,
		} );

		mockUseTimeline.mockReturnValue( {
			timeline: mockTimeline,
			timelineError: undefined,
			isLoading: false,
		} );

		const { container } = render( <PaymentOrderDetails id={ orderId } /> );

		expect( window.location.href ).toEqual( 'http://example.com' );

		expect( container ).toMatchSnapshot();

		// eslint-disable-next-line @typescript-eslint/ban-ts-comment
		// @ts-ignore
		expect( console ).toHaveWarnedWith(
			'List with items prop is deprecated is deprecated and will be removed in version 9.0.0. Note: See ExperimentalList / ExperimentalListItem for the new API that will replace this component in future versions.'
		);
	} );

	it( 'should match the snapshot - Charge with payment intent', () => {
		mockUseChargeFromOrder.mockReturnValue( {
			data: chargeMock,
			error: {} as ApiError,
			isLoading: false,
		} );

		mockUseTimeline.mockReturnValue( {
			timeline: mockTimeline,
			timelineError: undefined,
			isLoading: false,
		} );

		render( <PaymentOrderDetails id={ orderId } /> );

		expect( window.location.href ).toEqual( redirectUrl );
	} );
} );
