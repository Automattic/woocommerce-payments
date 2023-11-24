/** @format */

/**
 * External dependencies
 */
import { act, render, screen } from '@testing-library/react';
import user from '@testing-library/user-event';
import { getQuery, updateQueryString } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import { DepositsFilters } from '../';

// TODO: this is a bit of a hack as we're mocking an old version of WC, we should relook at this.
jest.mock( '@woocommerce/settings', () => ( {
	...jest.requireActual( '@woocommerce/settings' ),
	getSetting: jest.fn( ( key ) => ( key === 'wcVersion' ? 7.7 : '' ) ),
} ) );

describe( 'Deposits filters', () => {
	beforeEach( () => {
		// the query string is preserved across tests, so we need to reset it
		updateQueryString( {}, '/', {} );

		const { rerender } = render( <DepositsFilters /> );

		// select advanced filter view
		user.click( screen.getByRole( 'button', { name: /All deposits/i } ) );
		user.click(
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

		beforeEach( () => {
			addAdvancedFilter( 'Date' );
			ruleSelector = screen.getByRole( 'combobox', {
				name: /deposit date filter/i,
			} );
		} );

		test( 'should filter by before', () => {
			user.selectOptions( ruleSelector, 'before' );

			user.type(
				screen.getByRole( 'textbox', { name: /Choose a date/i } ),
				'04/29/2020'
			);
			user.click( screen.getByRole( 'link', { name: /Filter/ } ) );

			expect( getQuery().date_before ).toEqual( '2020-04-29' );
		} );

		test( 'should filter by after', () => {
			user.selectOptions( ruleSelector, 'after' );

			user.type(
				screen.getByRole( 'textbox', { name: /Choose a date/i } ),
				'04/29/2020'
			);
			user.click( screen.getByRole( 'link', { name: /Filter/ } ) );

			expect( getQuery().date_after ).toEqual( '2020-04-29' );
		} );

		test( 'should filter by between', () => {
			user.selectOptions( ruleSelector, 'between' );

			const dateInputs = screen.getAllByRole( 'textbox', {
				name: /Choose a date/i,
			} );
			user.type( dateInputs[ 0 ], '04/19/2020' );
			user.type( dateInputs[ 1 ], '04/29/2020' );
			user.click( screen.getByRole( 'link', { name: /Filter/ } ) );

			expect( getQuery().date_between ).toEqual( [
				'2020-04-19',
				'2020-04-29',
			] );
		} );
	} );

	describe( 'when filtering by status', () => {
		let ruleSelector;

		beforeEach( () => {
			addAdvancedFilter( 'Status' );
			ruleSelector = screen.getByRole( 'combobox', {
				name: /deposit status filter/i,
			} );
		} );

		test( 'should render all status', () => {
			const statusSelect = screen.getByRole( 'combobox', {
				name: /deposit status$/i,
			} );
			expect( statusSelect.options ).toMatchSnapshot();
		} );

		test( 'should filter by is', () => {
			user.selectOptions( ruleSelector, 'is' );

			// need to include $ in name, otherwise "Select a deposit status filter" is also matched.
			user.selectOptions(
				screen.getByRole( 'combobox', { name: /deposit status$/i } ),
				'paid'
			);
			user.click( screen.getByRole( 'link', { name: /Filter/ } ) );

			expect( getQuery().status_is ).toEqual( 'paid' );
		} );

		test( 'should filter by is_not', () => {
			user.selectOptions( ruleSelector, 'is_not' );

			// need to include $ in name, otherwise "Select a deposit status filter" is also matched.
			user.selectOptions(
				screen.getByRole( 'combobox', { name: /deposit status$/i } ),
				'pending'
			);
			user.click( screen.getByRole( 'link', { name: /Filter/ } ) );

			expect( getQuery().status_is_not ).toEqual( 'pending' );
		} );

		test( 'should filter by in_transit', () => {
			user.selectOptions( ruleSelector, 'is' );

			// need to include $ in name, otherwise "Select a deposit status filter" is also matched.
			user.selectOptions(
				screen.getByRole( 'combobox', { name: /deposit status$/i } ),
				'in_transit'
			);
			user.click( screen.getByRole( 'link', { name: /Filter/ } ) );

			expect( getQuery().status_is ).toEqual( 'in_transit' );
		} );

		test( 'should filter by canceled', () => {
			user.selectOptions( ruleSelector, 'is' );

			// need to include $ in name, otherwise "Select a deposit status filter" is also matched.
			user.selectOptions(
				screen.getByRole( 'combobox', { name: /deposit status$/i } ),
				'canceled'
			);
			user.click( screen.getByRole( 'link', { name: /Filter/ } ) );

			expect( getQuery().status_is ).toEqual( 'canceled' );
		} );

		test( 'should filter by failed', () => {
			user.selectOptions( ruleSelector, 'is' );

			// need to include $ in name, otherwise "Select a deposit status filter" is also matched.
			user.selectOptions(
				screen.getByRole( 'combobox', { name: /deposit status$/i } ),
				'failed'
			);
			user.click( screen.getByRole( 'link', { name: /Filter/ } ) );

			expect( getQuery().status_is ).toEqual( 'failed' );
		} );
	} );

	function addAdvancedFilter( filter ) {
		user.click( screen.getByRole( 'button', { name: /Add a Filter/i } ) );
		user.click( screen.getByRole( 'button', { name: filter } ) );
	}
} );
