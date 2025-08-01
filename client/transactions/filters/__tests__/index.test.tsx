/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { act, render, screen } from '@testing-library/react';
import user from '@testing-library/user-event';
import { getQuery, updateQueryString } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import { TransactionsFilters } from '../';
import { Transaction } from 'wcpay/data';
import PAYMENT_METHOD_IDS, {
	PAYMENT_METHOD_BRANDS,
} from 'wcpay/constants/payment-method';

// TODO: this is a bit of a hack as we're mocking an old version of WC, we should relook at this.
jest.mock( '@woocommerce/settings', () => ( {
	...jest.requireActual( '@woocommerce/settings' ),
	getSetting: jest.fn( ( key ) => ( key === 'wcVersion' ? 7.8 : '' ) ),
} ) );

jest.mock( 'tracks', () => ( {
	recordEvent: jest.fn(),
	events: {
		PAGE_VIEW: 'page_view',
	},
} ) );

async function addAdvancedFilter( filter: string ) {
	await user.click( screen.getByRole( 'button', { name: /Add a Filter/i } ) );
	await user.click( screen.getByRole( 'button', { name: filter } ) );
}

const storeCurrencies = [ 'eur', 'usd' ];
const customerCurrencies = [ 'eur', 'usd', 'gbp' ];
const transactionSources: Transaction[ 'source' ][] = [
	PAYMENT_METHOD_BRANDS.VISA,
	PAYMENT_METHOD_BRANDS.MASTERCARD,
	PAYMENT_METHOD_IDS.SOFORT,
];

declare const global: {
	wcSettings: { countries: Record< string, string > };
	wooPaymentsPaymentMethodsConfig: Record< string, { title: string } >;
};

global.wcSettings = {
	countries: {
		US: 'United States of America',
		CA: 'Canada',
		UK: 'United Kingdom',
	},
};

global.wooPaymentsPaymentMethodsConfig = {
	sofort: {
		title: 'Sofort',
	},
};

describe( 'Transactions filters', () => {
	beforeAll( () => {
		jest.useFakeTimers();
	} );

	afterAll( () => {
		jest.useRealTimers();
	} );

	beforeEach( async () => {
		// the query string is preserved across tests, so we need to reset it
		updateQueryString( {}, '/', {} );

		global.wcSettings = {
			countries: {
				US: 'United States of America',
				CA: 'Canada',
				UK: 'United Kingdom',
			},
		};

		const { rerender } = render(
			<TransactionsFilters
				storeCurrencies={ storeCurrencies }
				customerCurrencies={ customerCurrencies }
				transactionSources={ transactionSources }
			/>
		);

		// select advanced filter view
		await user.click(
			screen.getByRole( 'button', { name: /All transactions/i } )
		);
		await user.click(
			screen.getByRole( 'button', { name: /Advanced filters/i } )
		);
		rerender(
			<TransactionsFilters
				storeCurrencies={ storeCurrencies }
				customerCurrencies={ customerCurrencies }
				transactionSources={ transactionSources }
			/>
		);
	} );

	// Waiting for the microtask queue to be flushed to prevent "TypeError: Cannot read properties of null (reading 'documentElement')"
	// See https://github.com/floating-ui/floating-ui/issues/1908 and https://floating-ui.com/docs/react#testing
	afterEach( async () => {
		// eslint-disable-next-line @typescript-eslint/no-empty-function
		await act( async () => {} );
	} );

	describe( 'when filtering by date', () => {
		let ruleSelector: HTMLElement;

		beforeEach( async () => {
			await addAdvancedFilter( 'Date' );
			ruleSelector = screen.getByRole( 'combobox', {
				name: /transaction date filter/i,
			} );
		} );

		test( 'should filter by before', async () => {
			await user.selectOptions( ruleSelector, 'before' );

			await user.type(
				screen.getByRole( 'textbox', { name: /Choose a date/i } ),
				'04/29/2020'
			);
			await user.click( screen.getByRole( 'link', { name: /Filter/ } ) );

			expect( getQuery().date_before ).toEqual( '2020-04-29' );
		} );

		test( 'should filter by after', async () => {
			await user.selectOptions( ruleSelector, 'after' );

			await user.type(
				screen.getByRole( 'textbox', { name: /Choose a date/i } ),
				'04/29/2020'
			);
			await user.click( screen.getByRole( 'link', { name: /Filter/ } ) );

			expect( getQuery().date_after ).toEqual( '2020-04-29' );
		} );

		test( 'should filter by between', async () => {
			await user.selectOptions( ruleSelector, 'between' );

			const dateInputs = screen.getAllByRole( 'textbox', {
				name: /Choose a date/i,
			} );
			await user.type( dateInputs[ 0 ], '04/19/2020' );
			await user.type( dateInputs[ 1 ], '04/29/2020' );
			await user.click( screen.getByRole( 'link', { name: /Filter/ } ) );

			expect( getQuery().date_between ).toEqual( [
				'2020-04-19',
				'2020-04-29',
			] );
		} );
	} );

	describe( 'when filtering by type', () => {
		let ruleSelector: HTMLElement;

		beforeEach( async () => {
			await addAdvancedFilter( 'Type' );
			ruleSelector = screen.getByRole( 'combobox', {
				name: /transaction type filter/i,
			} );
		} );

		test( 'should render all types', () => {
			const typeSelect = screen.getByRole( 'combobox', {
				name: /transaction type$/i,
			} ) as HTMLSelectElement;
			expect( typeSelect.options ).toMatchSnapshot();
		} );

		test( 'should filter by is', async () => {
			await user.selectOptions( ruleSelector, 'is' );

			// need to include $ in name, otherwise "Select a transaction type filter" is also matched.
			await user.selectOptions(
				screen.getByRole( 'combobox', { name: /transaction type$/i } ),
				'charge'
			);
			await user.click( screen.getByRole( 'link', { name: /Filter/ } ) );

			expect( getQuery().type_is ).toEqual( 'charge' );
		} );

		test( 'should filter by is_not', async () => {
			await user.selectOptions( ruleSelector, 'is_not' );

			// need to include $ in name, otherwise "Select a transaction type filter" is also matched.
			await user.selectOptions(
				screen.getByRole( 'combobox', { name: /transaction type$/i } ),
				'dispute'
			);
			await user.click( screen.getByRole( 'link', { name: /Filter/ } ) );

			expect( getQuery().type_is_not ).toEqual( 'dispute' );
		} );

		test( 'should filter by refund', async () => {
			await user.selectOptions( ruleSelector, 'is' );

			// need to include $ in name, otherwise "Select a transaction type filter" is also matched.
			await user.selectOptions(
				screen.getByRole( 'combobox', { name: /transaction type$/i } ),
				'refund'
			);
			await user.click( screen.getByRole( 'link', { name: /Filter/ } ) );

			expect( getQuery().type_is ).toEqual( 'refund' );
		} );
	} );

	describe( 'when filtering by customer currency', () => {
		let ruleSelector: HTMLElement;

		beforeEach( async () => {
			await addAdvancedFilter( 'Customer currency' );
			ruleSelector = screen.getByRole( 'combobox', {
				name: /transaction customer currency filter/i,
			} );
		} );

		test( 'should render all types', () => {
			const typeSelect = screen.getByRole( 'combobox', {
				name: /customer currency$/i,
			} ) as HTMLSelectElement;
			expect( typeSelect.options ).toMatchSnapshot();
		} );

		test( 'should filter by is', async () => {
			await user.selectOptions( ruleSelector, 'is' );

			await user.selectOptions(
				screen.getByRole( 'combobox', {
					name: /Select a customer currency/i,
				} ),
				'eur'
			);
			await user.click( screen.getByRole( 'link', { name: /Filter/ } ) );

			expect( getQuery().customer_currency_is ).toEqual( 'eur' );
		} );

		test( 'should filter by is_not', async () => {
			await user.selectOptions( ruleSelector, 'is_not' );

			await user.selectOptions(
				screen.getByRole( 'combobox', {
					name: /Select a customer currency/i,
				} ),
				'eur'
			);
			await user.click( screen.getByRole( 'link', { name: /Filter/ } ) );

			expect( getQuery().customer_currency_is_not ).toEqual( 'eur' );
		} );
	} );

	describe( 'when filtering by payment method', () => {
		let ruleSelector: HTMLElement;

		beforeEach( async () => {
			await addAdvancedFilter( 'Payment method' );
			ruleSelector = screen.getByRole( 'combobox', {
				name: /payment method filter/i,
			} );
		} );

		test( 'should render all types', () => {
			const typeSelect = screen.getByRole( 'combobox', {
				name: /payment method$/i,
			} ) as HTMLSelectElement;
			expect( typeSelect.options ).toMatchSnapshot();
		} );

		test( 'should filter by is', async () => {
			await user.selectOptions( ruleSelector, 'is' );

			await user.selectOptions(
				screen.getByRole( 'combobox', {
					name: /Select a payment method$/i,
				} ),
				'visa'
			);
			await user.click( screen.getByRole( 'link', { name: /Filter/ } ) );

			expect( getQuery().source_is ).toEqual( 'visa' );
		} );

		test( 'should filter by is_not', async () => {
			await user.selectOptions( ruleSelector, 'is_not' );

			await user.selectOptions(
				screen.getByRole( 'combobox', {
					name: /Select a payment method$/i,
				} ),
				'visa'
			);
			await user.click( screen.getByRole( 'link', { name: /Filter/ } ) );

			expect( getQuery().source_is_not ).toEqual( 'visa' );
		} );
	} );

	describe( 'when filtering by source device', () => {
		let ruleSelector: HTMLElement;

		beforeEach( async () => {
			await addAdvancedFilter( 'Device Type' );
			ruleSelector = screen.getByRole( 'combobox', {
				name: /transaction device type filter/i,
			} );
		} );

		test( 'should render all types', () => {
			const typeSelect = screen.getByRole( 'combobox', {
				name: /transaction device type$/i,
			} ) as HTMLSelectElement;
			expect( typeSelect.options ).toMatchSnapshot();
		} );

		test( 'should filter by is', async () => {
			await user.selectOptions( ruleSelector, 'is' );

			// need to include $ in name, otherwise "Select a transaction type filter" is also matched.
			await user.selectOptions(
				screen.getByRole( 'combobox', {
					name: /transaction device type$/i,
				} ),
				'ios'
			);
			await user.click( screen.getByRole( 'link', { name: /Filter/ } ) );

			expect( getQuery().source_device_is ).toEqual( 'ios' );
		} );

		test( 'should filter by is_not', async () => {
			await user.selectOptions( ruleSelector, 'is_not' );

			// need to include $ in name, otherwise "Select a transaction type filter" is also matched.
			await user.selectOptions(
				screen.getByRole( 'combobox', {
					name: /transaction device type$/i,
				} ),
				'android'
			);
			await user.click( screen.getByRole( 'link', { name: /Filter/ } ) );

			expect( getQuery().source_device_is_not ).toEqual( 'android' );
		} );
	} );

	describe( 'when filtering by channel', () => {
		let ruleSelector: HTMLElement;

		beforeEach( async () => {
			await addAdvancedFilter( 'Sales channel' );
			ruleSelector = screen.getByRole( 'combobox', {
				name: /transaction sales channel filter/i,
			} );
		} );

		test( 'should render all types', () => {
			const typeSelect = screen.getByRole( 'combobox', {
				name: /transaction sales channel$/i,
			} ) as HTMLSelectElement;
			expect( typeSelect.options ).toMatchSnapshot();
		} );

		test( 'should filter by is', async () => {
			await user.selectOptions( ruleSelector, 'is' );

			await user.selectOptions(
				screen.getByRole( 'combobox', {
					name: /transaction sales channel$/i,
				} ),
				'online'
			);
			await user.click( screen.getByRole( 'link', { name: /Filter/ } ) );

			expect( getQuery().channel_is ).toEqual( 'online' );
		} );

		test( 'should filter by is_not', async () => {
			await user.selectOptions( ruleSelector, 'is_not' );

			// need to include $ in name, otherwise "Select a transaction type filter" is also matched.
			await user.selectOptions(
				screen.getByRole( 'combobox', {
					name: /transaction sales channel$/i,
				} ),
				'in_person'
			);
			await user.click( screen.getByRole( 'link', { name: /Filter/ } ) );

			expect( getQuery().channel_is_not ).toEqual( 'in_person' );
		} );
	} );

	describe( 'when filtering by customer country', () => {
		let ruleSelector: HTMLElement;

		beforeEach( async () => {
			await addAdvancedFilter( 'Customer Country' );
			ruleSelector = screen.getByRole( 'combobox', {
				name: /transaction customer country filter/i,
			} );
		} );

		test( 'should render all types', () => {
			const typeSelect = screen.getByRole( 'combobox', {
				name: /transaction customer country$/i,
			} ) as HTMLSelectElement;
			expect( typeSelect.options ).toMatchSnapshot();
		} );

		test( 'should filter by is', async () => {
			await user.selectOptions( ruleSelector, 'is' );

			// need to include $ in name, otherwise "Select a transaction type filter" is also matched.
			await user.selectOptions(
				screen.getByRole( 'combobox', {
					name: /transaction customer country$/i,
				} ),
				'US'
			);
			await user.click( screen.getByRole( 'link', { name: /Filter/ } ) );

			expect( getQuery().customer_country_is ).toEqual( 'US' );
		} );

		test( 'should filter by is_not', async () => {
			await user.selectOptions( ruleSelector, 'is_not' );

			// need to include $ in name, otherwise "Select a transaction type filter" is also matched.
			await user.selectOptions(
				screen.getByRole( 'combobox', {
					name: /transaction customer country$/i,
				} ),
				'CA'
			);
			await user.click( screen.getByRole( 'link', { name: /Filter/ } ) );

			expect( getQuery().customer_country_is_not ).toEqual( 'CA' );
		} );
	} );

	describe( 'when filtering by risk level', () => {
		let ruleSelector: HTMLElement;

		beforeEach( async () => {
			await addAdvancedFilter( 'Risk Level' );
			ruleSelector = screen.getByRole( 'combobox', {
				name: /transaction Risk level filter/i,
			} );
		} );

		test( 'should render all types', () => {
			const typeSelect = screen.getByRole( 'combobox', {
				name: /transaction Risk level$/i,
			} ) as HTMLSelectElement;
			expect( typeSelect.options ).toMatchSnapshot();
		} );

		test( 'should filter by is', async () => {
			await user.selectOptions( ruleSelector, 'is' );

			// need to include $ in name, otherwise "Select a transaction type filter" is also matched.
			await user.selectOptions(
				screen.getByRole( 'combobox', {
					name: /transaction Risk level$/i,
				} ),
				'0'
			);
			await user.click( screen.getByRole( 'link', { name: /Filter/ } ) );

			expect( getQuery().risk_level_is ).toEqual( '0' );
		} );

		test( 'should filter by is_not', async () => {
			await user.selectOptions( ruleSelector, 'is_not' );

			// need to include $ in name, otherwise "Select a transaction type filter" is also matched.
			await user.selectOptions(
				screen.getByRole( 'combobox', {
					name: /transaction Risk level$/i,
				} ),
				'1'
			);
			await user.click( screen.getByRole( 'link', { name: /Filter/ } ) );

			expect( getQuery().risk_level_is_not ).toEqual( '1' );
		} );
	} );
} );
