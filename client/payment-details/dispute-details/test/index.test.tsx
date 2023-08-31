/** @format */
/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';
import React from 'react';

/**
 * Internal dependencies
 */
import type { Dispute } from 'wcpay/types/disputes';
import { useDisputeAccept } from 'wcpay/data';
import DisputeDetails from '..';

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

const charge = {
	id: 'ch_38jdHA39KKA',
	/* Stripe data comes in seconds, instead of the default Date milliseconds */
	created: Date.parse( 'Sep 19, 2019, 5:24 pm' ) / 1000,
	amount: 2000,
	amount_refunded: 0,
	application_fee_amount: 70,
	disputed: false,
	dispute: {
		id: 'dp_1',
		amount: 6800,
		charge: 'ch_38jdHA39KKA',
		order: null,
		balance_transactions: [
			{
				amount: -2000,
				currency: 'usd',
				fee: 1500,
				reporting_category: 'dispute',
			},
		],
		created: 1693453017,
		currency: 'usd',
		evidence: {
			billing_address: '123 test address',
			customer_email_address: 'test@email.com',
			customer_name: 'Test customer',
			shipping_address: '123 test address',
		},
		evidence_details: {
			due_by: 1694303999,
			has_evidence: false,
			past_due: false,
			submission_count: 0,
		},
		// issuer_evidence: null,
		metadata: [],
		payment_intent: 'pi_1',
		reason: 'fraudulent',
		status: 'needs_response',
	} as Dispute,
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
	payment_method_details: {
		card: {
			brand: 'visa',
			last4: '4242',
		},
		type: 'card',
	},
};

// mock the useDisputeAccept hook
jest.mock( 'wcpay/data', () => ( {
	useDisputeAccept: jest.fn( () => ( {
		doAccept: jest.fn(),
		isLoading: false,
	} ) ),
} ) );
const mockUseDisputeAccept = useDisputeAccept as jest.MockedFunction<
	typeof useDisputeAccept
>;
const mockDoAccept = jest.fn();

describe( 'DisputeDetails', () => {
	beforeEach( () => {
		jest.clearAllMocks();

		mockUseDisputeAccept.mockReset();
		mockUseDisputeAccept.mockReturnValue( {
			doAccept: mockDoAccept,
			isLoading: false,
		} );
	} );

	test( 'correctly renders dispute details', () => {
		render( <DisputeDetails dispute={ charge.dispute } /> );

		screen.getByText(
			/The cardholder claims this is an unauthorized transaction/,
			{ ignore: '.a11y-speak-region' }
		);
		screen.getByRole( 'button', {
			name: /Challenge dispute/,
		} );
		screen.getByRole( 'button', {
			name: /Accept dispute/,
		} );
	} );

	test( 'correctly renders the accept dispute modal and accepts', () => {
		render( <DisputeDetails dispute={ charge.dispute } /> );

		const openModalButton = screen.getByRole( 'button', {
			name: /Accept dispute/,
		} );

		// Open the modal
		openModalButton.click();

		screen.getByText( /Accept the dispute?/ );
		screen.getByRole( 'button', {
			name: /Cancel/,
		} );
		const acceptButton = screen.getByRole( 'button', {
			name: /Accept dispute/,
		} );

		// Accept the dispute
		acceptButton.click();

		expect( mockDoAccept ).toHaveBeenCalledTimes( 1 );
	} );
} );
