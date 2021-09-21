/** @format */
/**
 * External dependencies
 */
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import PaymentDetailsSummary from '../';

const getBaseCharge = () => ( {
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
} );

describe( 'PaymentDetailsSummary', () => {
	beforeEach( () => {
		global.wcpaySettings = {
			isSubscriptionsActive: false,
			zeroDecimalCurrencies: [],
		};
	} );

	test( 'correctly renders a charge', () => {
		const paymentDetailsSummary = renderCharge( getBaseCharge() );
		expect( paymentDetailsSummary ).toMatchSnapshot();
	} );

	test( 'renders partially refunded information for a charge', () => {
		const charge = getBaseCharge();
		charge.refunded = false;
		charge.amount_refunded = 1200;
		charge.refunds.data.push( {
			balance_transaction: {
				amount: -charge.amount_refunded,
			},
		} );

		const paymentDetailsSummary = renderCharge( charge );
		expect( paymentDetailsSummary ).toMatchSnapshot();
	} );

	test( 'renders fully refunded information for a charge', () => {
		const charge = getBaseCharge();
		charge.refunded = true;
		charge.amount_refunded = 2000;
		charge.refunds.data.push( {
			balance_transaction: {
				amount: -charge.amount_refunded,
			},
		} );

		const paymentDetailsSummary = renderCharge( charge );
		expect( paymentDetailsSummary ).toMatchSnapshot();
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
				},
			],
		};

		const paymentDetailsSummary = renderCharge( charge );
		expect( paymentDetailsSummary ).toMatchSnapshot();
	} );

	test( 'renders a charge with subscriptions', () => {
		global.wcpaySettings.isSubscriptionsActive = true;

		const charge = getBaseCharge();
		charge.order.subscriptions = [
			{
				number: 246,
				url: 'https://example.com/subscription/246',
			},
		];

		const paymentDetailsSummary = renderCharge( charge );
		expect( paymentDetailsSummary ).toMatchSnapshot();
	} );

	test( 'renders loading state', () => {
		const paymentDetailsSummary = renderCharge( {}, true );
		expect( paymentDetailsSummary ).toMatchSnapshot();
	} );

	function renderCharge( charge, isLoading = false ) {
		const { container } = render(
			<PaymentDetailsSummary charge={ charge } isLoading={ isLoading } />
		);
		return container;
	}
} );
