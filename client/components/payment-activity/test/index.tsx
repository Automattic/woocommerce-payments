/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import PaymentActivity from '..';

declare const global: {
	wcpaySettings: {
		accountStatus: {
			deposits: {
				restrictions: string;
				completed_waiting_period: boolean;
				minimum_scheduled_deposit_amounts: {
					[ currencyCode: string ]: number;
				};
			};
		};
		accountDefaultCurrency: string;
		zeroDecimalCurrencies: string[];
		currencyData: Record< string, any >;
		connect: {
			country: string;
		};
	};
};

describe( 'PaymentActivity component', () => {
	beforeEach( () => {
		global.wcpaySettings = {
			accountStatus: {
				deposits: {
					restrictions: 'deposits_unrestricted',
					completed_waiting_period: true,
					minimum_scheduled_deposit_amounts: {
						eur: 500,
						usd: 500,
					},
				},
			},
			accountDefaultCurrency: 'USD',
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
				EU: {
					code: 'EUR',
					symbol: '€',
					symbolPosition: 'left',
					thousandSeparator: '.',
					decimalSeparator: ',',
					precision: 2,
				},
			},
		};
	} );

	it( 'should render', () => {
		const { container } = render( <PaymentActivity /> );

		expect( container ).toMatchSnapshot();
	} );
} );
