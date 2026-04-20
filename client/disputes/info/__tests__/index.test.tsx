/** @format */

/**
 * External dependencies
 */
import { render } from '@testing-library/react';
import React from 'react';

/**
 * Internal dependencies
 */
import Info from '../';
import { Dispute, Evidence } from 'wcpay/types/disputes';
import { Charge } from 'wcpay/types/charges';

declare const global: {
	wcpaySettings: {
		zeroDecimalCurrencies: string[];
		connect: {
			country: string;
		};
		currencyData: {
			[ key: string ]: {
				code: string;
				symbol: string;
				symbolPosition: string;
				thousandSeparator: string;
				decimalSeparator: string;
				precision: number;
			};
		};
		dateFormat: string;
		timeFormat: string;
	};
};

describe( 'Dispute info', () => {
	beforeEach( () => {
		global.wcpaySettings = {
			zeroDecimalCurrencies: [],
			connect: {
				country: 'US',
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
			dateFormat: 'M j, Y',
			timeFormat: 'g:iA',
		};
	} );

	test( 'renders correctly', () => {
		const dispute: Dispute = {
			status: 'needs_response',
			id: '',
			evidence_details: {
				has_evidence: true,
				due_by: 1573199200,
				past_due: false,
				submission_count: 0,
			},
			metadata: {},
			order: {
				id: 1,
				number: '1',
				url: 'http://test.local/order/1',
				customer_url: null,
				customer_email: null,
				customer_name: null,
				ip_address: '127.0.0.1',
			},
			evidence: {} as Evidence,
			fileSize: {},
			reason: 'fraudulent',
			issuer_evidence: null,
			charge: {
				id: 'ch_mock',
				billing_details: {
					name: 'Mock customer',
				},
			} as Charge,
			amount: 1000,
			currency: 'usd',
			created: 1572590800,
			balance_transactions: [],
			payment_intent: 'pi_mock',
		};

		const { container: info } = render(
			<Info dispute={ dispute } isLoading={ false } />
		);
		expect( info ).toMatchSnapshot();
	} );

	test( 'renders loading state', () => {
		const { container: info } = render(
			<Info isLoading={ true } dispute={ {} as Dispute } />
		);
		expect( info ).toMatchSnapshot();
	} );
} );
