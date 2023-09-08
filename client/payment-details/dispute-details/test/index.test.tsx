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

describe( 'DisputeDetails', () => {
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
	} );
} );
