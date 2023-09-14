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
import type { Charge } from 'wcpay/types/charges';
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

interface ChargeWithDisputeRequired extends Charge {
	dispute: Dispute;
}

const getBaseCharge = (): ChargeWithDisputeRequired =>
	( {
		id: 'ch_38jdHA39KKA',
		/* Stripe data comes in seconds, instead of the default Date milliseconds */
		created: Date.parse( 'Sep 19, 2019, 5:24 pm' ) / 1000,
		amount: 2000,
		amount_refunded: 0,
		application_fee_amount: 70,
		disputed: true,
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

// mock the history push function
const mockHistoryPush = jest.fn();
jest.mock( '@woocommerce/navigation', () => ( {
	getHistory: () => ( {
		push: mockHistoryPush,
	} ),
} ) );

describe( 'DisputeDetails', () => {
	beforeEach( () => {
		// mock Date.now that moment library uses to get current date for testing purposes
		Date.now = jest.fn( () =>
			new Date( '2023-09-08T12:33:37.000Z' ).getTime()
		);

		jest.clearAllMocks();

		mockUseDisputeAccept.mockReset();
		mockUseDisputeAccept.mockReturnValue( {
			doAccept: mockDoAccept,
			isLoading: false,
		} );
	} );

	afterEach( () => {
		// roll it back
		Date.now = () => new Date().getTime();
	} );

	test( 'correctly renders dispute details', () => {
		const charge = getBaseCharge();
		render( <DisputeDetails dispute={ charge.dispute } /> );

		// Expect this warning to be logged to the console
		expect( console ).toHaveWarnedWith(
			'List with items prop is deprecated is deprecated and will be removed in version 9.0.0. Note: See ExperimentalList / ExperimentalListItem for the new API that will replace this component in future versions.'
		);

		// Dispute Notice
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

		// Don't render the staged evidence message
		expect(
			screen.queryByText(
				/You initiated a dispute a challenge to this dispute/,
				{ ignore: '.a11y-speak-region' }
			)
		).toBeNull();

		// Dispute Summary Row
		expect(
			screen.getByText( /Dispute Amount/i ).nextSibling
		).toHaveTextContent( /\$68.00/ );
		expect(
			screen.getByText( /Disputed On/i ).nextSibling
		).toHaveTextContent( /Aug 30, 2023/ );
		expect( screen.getByText( /Reason/i ).nextSibling ).toHaveTextContent(
			/Transaction unauthorized/
		);
		expect(
			screen.getByText( /Respond By/i ).nextSibling
		).toHaveTextContent( /Sep 9, 2023/ );
	} );

	test( 'correctly renders dispute details for a dispute with staged evidence', () => {
		const charge = getBaseCharge();
		charge.dispute.evidence_details = {
			has_evidence: true,
			due_by: 1694303999,
			past_due: false,
			submission_count: 0,
		};

		render( <DisputeDetails dispute={ charge.dispute } /> );

		screen.getByText(
			/The cardholder claims this is an unauthorized transaction/,
			{ ignore: '.a11y-speak-region' }
		);

		// Render the staged evidence message
		screen.getByText(
			/You initiated a dispute a challenge to this dispute/,
			{ ignore: '.a11y-speak-region' }
		);

		screen.getByRole( 'button', {
			name: /Continue with challenge/,
		} );
	} );

	test( 'correctly renders the accept dispute modal and accepts', () => {
		const charge = getBaseCharge();
		charge.dispute.status = 'needs_response';

		render( <DisputeDetails dispute={ charge.dispute } /> );

		const openModalButton = screen.getByRole( 'button', {
			name: /Accept dispute/,
		} );

		// Open the modal
		openModalButton.click();

		screen.getByRole( 'heading', {
			name: /Accept the dispute?/,
		} );
		screen.getByText( /\$15.00 dispute fee/, {
			ignore: '.a11y-speak-region',
		} );

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

	test( 'correctly navigates to the challenge screen when challenge button clicked', () => {
		const charge = getBaseCharge();
		render( <DisputeDetails dispute={ charge.dispute } /> );

		const challengeButton = screen.getByRole( 'button', {
			name: /Challenge dispute/,
		} );
		challengeButton.click();

		expect( mockHistoryPush ).toHaveBeenNthCalledWith(
			1,
			expect.stringContaining( `challenge&id=${ charge.dispute.id }` )
		);
	} );
} );
