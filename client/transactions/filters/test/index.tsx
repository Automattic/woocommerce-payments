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
import { TransactionsFilters } from '../';

function addAdvancedFilter( filter: string ) {
	user.click( screen.getByRole( 'button', { name: /Add a Filter/i } ) );
	user.click( screen.getByRole( 'button', { name: filter } ) );
}

describe( 'Transactions filters', () => {
	beforeEach( () => {
		// the query string is preserved across tests, so we need to reset it
		updateQueryString( {}, '/', {} );

		const { rerender } = render( <TransactionsFilters /> );

		// select advanced filter view
		user.click(
			screen.getByRole( 'button', { name: /All transactions/i } )
		);
		user.click(
			screen.getByRole( 'button', { name: /Advanced filters/i } )
		);
		rerender( <TransactionsFilters /> );
	} );

	describe( 'when filtering by date', () => {
		let ruleSelector: HTMLElement;

		beforeEach( () => {
			addAdvancedFilter( 'Date' );
			ruleSelector = screen.getByRole( 'combobox', {
				name: /transaction date filter/i,
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

	describe( 'when filtering by type', () => {
		let ruleSelector: HTMLElement;

		beforeEach( () => {
			addAdvancedFilter( 'Type' );
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

		test( 'should filter by is', () => {
			user.selectOptions( ruleSelector, 'is' );

			// need to include $ in name, otherwise "Select a transaction type filter" is also matched.
			user.selectOptions(
				screen.getByRole( 'combobox', { name: /transaction type$/i } ),
				'charge'
			);
			user.click( screen.getByRole( 'link', { name: /Filter/ } ) );

			expect( getQuery().type_is ).toEqual( 'charge' );
		} );

		test( 'should filter by is_not', () => {
			user.selectOptions( ruleSelector, 'is_not' );

			// need to include $ in name, otherwise "Select a transaction type filter" is also matched.
			user.selectOptions(
				screen.getByRole( 'combobox', { name: /transaction type$/i } ),
				'dispute'
			);
			user.click( screen.getByRole( 'link', { name: /Filter/ } ) );

			expect( getQuery().type_is_not ).toEqual( 'dispute' );
		} );

		test( 'should filter by refund', () => {
			user.selectOptions( ruleSelector, 'is' );

			// need to include $ in name, otherwise "Select a transaction type filter" is also matched.
			user.selectOptions(
				screen.getByRole( 'combobox', { name: /transaction type$/i } ),
				'refund'
			);
			user.click( screen.getByRole( 'link', { name: /Filter/ } ) );

			expect( getQuery().type_is ).toEqual( 'refund' );
		} );
	} );
} );
