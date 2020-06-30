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
	amount: 1500,
	// eslint-disable-next-line camelcase
	amount_refunded: 0,
	// eslint-disable-next-line camelcase
	application_fee_amount: 70,
	disputed: false,
	dispute: null,
	fee: 74,
	net: 1426,
	currency: 'usd',
	type: 'charge',
	order: {
		number: 45981,
		url: 'https://somerandomorderurl.com/?edit_order=45981',
	},
	// eslint-disable-next-line camelcase
	billing_details: {
		name: 'Customer name',
	},
	// eslint-disable-next-line camelcase
	payment_method_details: {
		card: {
			brand: 'visa',
			last4: '4242',
		},
		type: 'card',
	},
	outcome: {
		// eslint-disable-next-line camelcase
		risk_level: 'normal',
	},
} );

describe( 'PaymentDetailsSummary', () => {
	test( 'correctly renders a charge', () => {
		const paymentDetailsSummary = renderCharge( getBaseCharge() );
		expect( paymentDetailsSummary ).toMatchSnapshot();
	} );

	test( 'renders partially refunded information for a charge', () => {
		const charge = getBaseCharge();
		charge.refunded = false;
		// eslint-disable-next-line camelcase
		charge.amount_refunded = 1200;

		const paymentDetailsSummary = renderCharge( charge );
		expect( paymentDetailsSummary ).toMatchSnapshot();
	} );

	test( 'renders fully refunded information for a charge', () => {
		const charge = getBaseCharge();
		charge.refunded = true;
		// eslint-disable-next-line camelcase
		charge.amount_refunded = 1500;

		const paymentDetailsSummary = renderCharge( charge );
		expect( paymentDetailsSummary ).toMatchSnapshot();
	} );

	test( 'renders the information of a disputed charge', () => {
		const charge = getBaseCharge();
		charge.disputed = true;
		charge.dispute = {
			amount: 1500,
		};

		const paymentDetailsSummary = renderCharge( charge );
		expect( paymentDetailsSummary ).toMatchSnapshot();
	} );

	test( 'renders loading state', () => {
		const paymentDetailsSummary = renderCharge( {}, true );
		expect( paymentDetailsSummary ).toMatchSnapshot();
	} );

	function renderCharge( charge, isLoading = false ) {
		const { container } = render( <PaymentDetailsSummary charge={ charge } isLoading={ isLoading } /> );
		return container;
	}
} );

