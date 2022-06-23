/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';
import user from '@testing-library/user-event';
import { getQuery, updateQueryString } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import { DisputesFilters } from '../';
import { formatCurrencyName } from '../../../utils/currency';

function addAdvancedFilter( filter: string ) {
	user.click( screen.getByRole( 'button', { name: /Add a Filter/i } ) );
	user.click( screen.getByRole( 'button', { name: filter } ) );
}

function addCurrencyFilter( filter: string ) {
	user.click( screen.getByRole( 'button', { name: /All currencies/i } ) );
	user.click( screen.getByRole( 'button', { name: filter } ) );
}

describe( 'Disputes filters: Advanced', () => {
	beforeEach( () => {
		// the query string is preserved across tests, so we need to reset it
		updateQueryString( {}, '/', {} );

		const { rerender } = render( <DisputesFilters /> );

		user.click( screen.getByRole( 'button', { name: /All disputes/i } ) );
		user.click(
			screen.getByRole( 'button', { name: /Advanced filters/i } )
		);
		rerender( <DisputesFilters storeCurrencies={ [ 'eur', 'usd' ] } /> );
	} );

	describe( 'when filtering by date', () => {
		let ruleSelector: HTMLElement;

		beforeEach( () => {
			addAdvancedFilter( 'Disputed on date' );
			ruleSelector = screen.getByRole( 'combobox', {
				name: /dispute date filter/i,
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
		let ruleSelector: HTMLElement;

		beforeEach( () => {
			addAdvancedFilter( 'Status' );
			ruleSelector = screen.getByRole( 'combobox', {
				name: /dispute status filter/i,
			} );
		} );

		test( 'should render all statuses', () => {
			const statusSelect = screen.getByRole( 'combobox', {
				name: /dispute status$/i,
			} ) as HTMLSelectElement;
			expect( statusSelect.options ).toMatchSnapshot();
		} );

		test( 'should filter by is', () => {
			user.selectOptions( ruleSelector, 'is' );

			// need to include $ in the name, otherwise "Select a dispute status filter" is also matched.
			user.selectOptions(
				screen.getByRole( 'combobox', { name: /dispute status$/i } ),
				'warning_needs_response'
			);
			user.click( screen.getByRole( 'link', { name: /Filter/ } ) );

			expect( getQuery().status_is ).toEqual( 'warning_needs_response' );
		} );

		test( 'should filter by is_not', () => {
			user.selectOptions( ruleSelector, 'is_not' );

			// need to include $ in the name, otherwise "Select a dispute status filter" is also matched.
			user.selectOptions(
				screen.getByRole( 'combobox', { name: /dispute status$/i } ),
				'lost'
			);
			user.click( screen.getByRole( 'link', { name: /Filter/ } ) );

			expect( getQuery().status_is_not ).toEqual( 'lost' );
		} );
	} );

	describe( 'when filtering by currency', () => {
		test( 'by default, no currency filter is applied', () => {
			expect( getQuery().store_currency_is ).toEqual( undefined );
		} );

		test.each( [ 'usd', 'eur' ] )( 'should filter by %s', ( currency ) => {
			addCurrencyFilter( formatCurrencyName( currency ) );

			expect( getQuery().store_currency_is ).toEqual( currency );
		} );
	} );
} );
