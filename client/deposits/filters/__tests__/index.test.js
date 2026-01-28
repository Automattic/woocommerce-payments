/** @format */

/**
 * External dependencies
 */
import { act, render, screen } from '@testing-library/react';
import { userEvent as user } from 'jest-utils/user-event-timers';
import { getQuery, updateQueryString } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import { DepositsFilters } from '../';

// TODO: this is a bit of a hack as we're mocking an old version of WC, we should relook at this.
jest.mock( '@woocommerce/settings', () => ( {
	...jest.requireActual( '@woocommerce/settings' ),
	getSetting: jest.fn( ( key ) => ( key === 'wcVersion' ? 7.8 : '' ) ),
} ) );

describe( 'Deposits filters', () => {
	beforeAll( () => {
		jest.spyOn( console, 'error' ).mockImplementation( () => null );
		jest.spyOn( console, 'warn' ).mockImplementation( () => null );
		jest.useFakeTimers();
	} );

	afterAll( () => {
		jest.useRealTimers();
	} );

	beforeEach( async () => {
		// the query string is preserved across tests, so we need to reset it
		updateQueryString( {}, '/', {} );

		const { rerender } = render( <DepositsFilters /> );

		// select advanced filter view
		await user.click(
			screen.getByRole( 'button', { name: /All payouts/i } )
		);
		await user.click(
			screen.getByRole( 'button', { name: /Advanced filters/i } )
		);
		rerender( <DepositsFilters /> );
	} );

	// Waiting for the microtask queue to be flushed to prevent "TypeError: Cannot read properties of null (reading 'documentElement')"
	// See https://github.com/floating-ui/floating-ui/issues/1908 and https://floating-ui.com/docs/react#testing
	afterEach( async () => {
		// eslint-disable-next-line @typescript-eslint/no-empty-function
		await act( async () => {} );
	} );

	describe( 'when filtering by date', () => {
		let ruleSelector;

		beforeEach( async () => {
			await addAdvancedFilter( 'Date' );
			ruleSelector = screen.getByRole( 'combobox', {
				name: /payout date filter/i,
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

			expect( console ).toHaveWarnedWith(
				'wp.date.__experimentalGetSettings is deprecated since version 6.1. Please use wp.date.getSettings instead.'
			);
			expect( console ).toHaveErrored();
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

	describe( 'when filtering by status', () => {
		let ruleSelector;

		beforeEach( async () => {
			await addAdvancedFilter( 'Status' );
			ruleSelector = screen.getByRole( 'combobox', {
				name: /payout status filter/i,
			} );
		} );

		test( 'should render all status', () => {
			const statusSelect = screen.getByRole( 'combobox', {
				name: /payout status$/i,
			} );
			expect( statusSelect.options ).toMatchSnapshot();
		} );

		test( 'should filter by is', async () => {
			await user.selectOptions( ruleSelector, 'is' );

			// need to include $ in name, otherwise "Select a deposit status filter" is also matched.
			await user.selectOptions(
				screen.getByRole( 'combobox', { name: /payout status$/i } ),
				'paid'
			);
			await user.click( screen.getByRole( 'link', { name: /Filter/ } ) );

			expect( getQuery().status_is ).toEqual( 'paid' );
		} );

		test( 'should filter by is_not', async () => {
			await user.selectOptions( ruleSelector, 'is_not' );

			// need to include $ in name, otherwise "Select a deposit status filter" is also matched.
			await user.selectOptions(
				screen.getByRole( 'combobox', { name: /payout status$/i } ),
				'pending'
			);
			await user.click( screen.getByRole( 'link', { name: /Filter/ } ) );

			expect( getQuery().status_is_not ).toEqual( 'pending' );
		} );

		test( 'should filter by in_transit', async () => {
			await user.selectOptions( ruleSelector, 'is' );

			// need to include $ in name, otherwise "Select a deposit status filter" is also matched.
			await user.selectOptions(
				screen.getByRole( 'combobox', { name: /payout status$/i } ),
				'in_transit'
			);
			await user.click( screen.getByRole( 'link', { name: /Filter/ } ) );

			expect( getQuery().status_is ).toEqual( 'in_transit' );
		} );

		test( 'should filter by canceled', async () => {
			await user.selectOptions( ruleSelector, 'is' );

			// need to include $ in name, otherwise "Select a deposit status filter" is also matched.
			await user.selectOptions(
				screen.getByRole( 'combobox', { name: /payout status$/i } ),
				'canceled'
			);
			await user.click( screen.getByRole( 'link', { name: /Filter/ } ) );

			expect( getQuery().status_is ).toEqual( 'canceled' );
		} );

		test( 'should filter by failed', async () => {
			await user.selectOptions( ruleSelector, 'is' );

			// need to include $ in name, otherwise "Select a deposit status filter" is also matched.
			await user.selectOptions(
				screen.getByRole( 'combobox', { name: /payout status$/i } ),
				'failed'
			);
			await user.click( screen.getByRole( 'link', { name: /Filter/ } ) );

			expect( getQuery().status_is ).toEqual( 'failed' );
		} );
	} );

	async function addAdvancedFilter( filter ) {
		await user.click(
			screen.getByRole( 'button', { name: /Add a Filter/i } )
		);
		await user.click( screen.getByRole( 'button', { name: filter } ) );
	}
} );
