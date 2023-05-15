/** @format */
/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';
import React from 'react';
import moment from 'moment';
/**
 * Internal dependencies
 */
import PaymentDetailsSummary from '../';
import { Charge } from 'wcpay/types/charges';
import { useAuthorization } from 'wcpay/data';
import { paymentIntentMock } from '../../../data/payment-intents/test/hooks';

declare const global: {
	wcSettings: {
		locale: {
			siteLocale: string;
		};
	};
	wcpaySettings: {
		isSubscriptionsActive: boolean;
		zeroDecimalCurrencies: string[];
		currencyData: Record< string, any >;
		connect: {
			country: string;
		};
		featureFlags: {
			isAuthAndCaptureEnabled: boolean;
		};
	};
};

jest.mock( 'wcpay/data', () => ( {
	useAuthorization: jest.fn( () => ( {
		authorization: null,
	} ) ),
} ) );

const mockUseAuthorization = useAuthorization as jest.MockedFunction<
	typeof useAuthorization
>;

const getBaseCharge = (): Charge =>
	( {
		id: 'ch_38jdHA39KKA',
		/* Stripe data comes in seconds, instead of the default Date milliseconds */
		created: Date.parse( 'Sep 19, 2019, 5:24 pm' ) / 1000,
		amount: 2000,
		amount_refunded: 0,
		application_fee_amount: 70,
		disputed: false,
		dispute: null,
		currency: 'usd',
		type: 'charge',
		status: 'succeeded',
		paid: true,
		captured: true,
		balance_transaction: {
			amount: 2000,
			currency: 'usd',
			fee: 70,
		},
		refunds: {
			data: [],
		},
		order: {
			number: 45981,
			url: 'https://somerandomorderurl.com/?edit_order=45981',
		},
		billing_details: {
			name: 'Customer name',
		},
		payment_method_details: {
			card: {
				brand: 'visa',
				last4: '4242',
			},
			type: 'card',
		},
		outcome: {
			risk_level: 'normal',
		},
	} as any );

const getBaseMetadata = () => ( {
	platform: 'ios',
	reader_id: 'APPLEBUILTINSIMULATOR-1',
	reader_model: 'COTS_DEVICE',
} );

function renderCharge(
	charge: Charge,
	metadata = {},
	isLoading = false,
	props = {}
) {
	const { container } = render(
		<PaymentDetailsSummary
			charge={ charge }
			metadata={ metadata }
			isLoading={ isLoading }
			{ ...props }
		/>
	);
	return container;
}

describe( 'PaymentDetailsSummary', () => {
	beforeEach( () => {
		jest.clearAllMocks();

		global.wcpaySettings = {
			isSubscriptionsActive: false,
			zeroDecimalCurrencies: [],
			connect: {
				country: 'US',
			},
			featureFlags: {
				isAuthAndCaptureEnabled: true,
			},
			currencyData: {
				US: {
					code: 'USD',
					symbol: '$',
					symbolPosition: 'left',
					thousandSeparator: ',',
					decimalSeparator: '.',
					precision: 2,
				},
			},
		};
	} );

	test( 'correctly renders a charge', () => {
		expect( renderCharge( getBaseCharge() ) ).toMatchSnapshot();
	} );

	test( 'renders partially refunded information for a charge', () => {
		const charge = getBaseCharge();
		charge.refunded = false;
		charge.amount_refunded = 1200;
		charge.refunds?.data.push( {
			balance_transaction: {
				amount: -charge.amount_refunded,
				currency: 'usd',
			} as any,
		} );

		expect( renderCharge( charge ) ).toMatchSnapshot();
	} );

	test( 'renders fully refunded information for a charge', () => {
		const charge = getBaseCharge();
		charge.refunded = true;
		charge.amount_refunded = 2000;
		charge.refunds?.data.push( {
			balance_transaction: {
				amount: -charge.amount_refunded,
				currency: 'usd',
			} as any,
		} );

		expect( renderCharge( charge ) ).toMatchSnapshot();
	} );

	test( 'renders the information of a disputed charge', () => {
		const charge = getBaseCharge();
		charge.disputed = true;
		charge.dispute = {
			amount: 1500,
			status: 'under_review',
			balance_transactions: [
				{
					amount: -1500,
					fee: 1500,
				} as any,
			],
		} as any;

		expect( renderCharge( charge ) ).toMatchSnapshot();
	} );

	test( 'renders the Tap to Pay channel from metadata', () => {
		const charge = getBaseCharge();
		const metadata = getBaseMetadata();

		expect( renderCharge( charge, metadata ) ).toMatchSnapshot();
	} );

	test( 'renders a charge with subscriptions', () => {
		global.wcpaySettings.isSubscriptionsActive = true;

		const charge = getBaseCharge();
		if ( charge.order ) {
			charge.order.subscriptions = [
				{
					number: 246,
					url: 'https://example.com/subscription/246',
				},
			];
		}

		expect( renderCharge( charge ) ).toMatchSnapshot();
	} );

	test( 'renders loading state', () => {
		expect( renderCharge( {} as any, true ) ).toMatchSnapshot();
	} );

	describe( 'capture notification and fraud buttons', () => {
		beforeAll( () => {
			// Mock current date and time to fixed value in moment
			const fixedCurrentDate = new Date( '2023-01-01T01:00:00.000Z' );
			jest.spyOn( Date, 'now' ).mockImplementation( () =>
				fixedCurrentDate.getTime()
			);
		} );

		afterAll( () => {
			jest.spyOn( Date, 'now' ).mockRestore();
		} );

		test( 'renders capture section correctly', () => {
			mockUseAuthorization.mockReturnValueOnce( {
				authorization: {
					captured: false,
					charge_id: 'ch_mock',
					amount: 1000,
					currency: 'usd',
					created: moment.utc().format(),
					order_id: 123,
					risk_level: 1,
					customer_country: 'US',
					customer_email: 'test@example.com',
					customer_name: 'Test Customer',
					payment_intent_id: 'pi_mock',
				},
				isLoading: false,
				isRequesting: false,
				doCaptureAuthorization: jest.fn(),
				doCancelAuthorization: jest.fn(),
			} );
			const charge = getBaseCharge();
			charge.captured = false;

			const container = renderCharge( charge );

			expect(
				screen.getByRole( 'button', { name: /Capture/i } )
			).toBeInTheDocument();

			expect( container ).toMatchSnapshot();
		} );

		test( 'renders the fraud outcome buttons', () => {
			mockUseAuthorization.mockReturnValueOnce( {
				authorization: {
					captured: false,
					charge_id: 'ch_mock',
					amount: 1000,
					currency: 'usd',
					created: new Date( Date.now() ).toISOString(),
					order_id: 123,
					risk_level: 1,
					customer_country: 'US',
					customer_email: 'test@example.com',
					customer_name: 'Test Customer',
					payment_intent_id: 'pi_mock',
				},
				isLoading: false,
				isRequesting: false,
				doCaptureAuthorization: jest.fn(),
				doCancelAuthorization: jest.fn(),
			} );
			const charge = getBaseCharge();
			charge.captured = false;

			const container = renderCharge( charge, {}, false, {
				paymentIntent: paymentIntentMock,
			} );

			expect(
				screen.getByRole( 'button', { name: /Approve Transaction/i } )
			).toBeInTheDocument();

			expect(
				screen.getByRole( 'button', { name: /Block Transaction/i } )
			).toBeInTheDocument();

			expect(
				screen.queryByRole( 'button', { name: /Capture/i } )
			).not.toBeInTheDocument();

			expect(
				screen.getByText(
					/Approving this transaction will capture the charge./
				)
			).toBeInTheDocument();

			expect( container ).toMatchSnapshot();
		} );
	} );
} );
