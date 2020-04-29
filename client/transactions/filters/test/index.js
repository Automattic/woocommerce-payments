/** @format */

/**
 * External dependencies
 */
import { render, cleanup } from '@testing-library/react';
import user from '@testing-library/user-event';
import { getQuery, updateQueryString } from '@woocommerce/navigation';
import { isEmpty } from 'lodash';

/**
 * Internal dependencies
 */
import { TransactionsFilters } from '../';

describe( 'Transactions filters', () => {
	let rerender, getByText, getByLabelText, getAllByLabelText;

	beforeEach( () => {
		jest.clearAllMocks();
		// the query string is preserved across tests, so we need to reset it
		if ( ! isEmpty( getQuery() ) ) {
			updateQueryString( {}, '/', {} );
		}

		( { rerender, getByText, getByLabelText, getAllByLabelText } = render( <TransactionsFilters /> ) );

		// select advanced filter view
		user.click( getByText( 'All transactions' ) );
		user.click( getByText( 'Advanced filters' ) );
		rerender( <TransactionsFilters /> );
	} );

	afterEach( () => {
		cleanup();
	} );

	describe( 'when filtering by date', () => {
		let ruleSelector;

		beforeEach( () => {
			addFilter( 'Date' );
			ruleSelector = getByLabelText( 'Select a transaction date filter match' );
		} );

		test( 'should filter by before', () => {
			user.selectOptions( ruleSelector, 'before' );

			user.type( getByLabelText( 'Choose a date' ), '2020-04-29' );
			user.click( getByText( 'Filter' ) );

			expect( getQuery().date_before ).toEqual( '2020-04-29' );
		} );

		test( 'should filter by after', () => {
			user.selectOptions( ruleSelector, 'after' );

			user.type( getByLabelText( 'Choose a date' ), '2020-04-29' );
			user.click( getByText( 'Filter' ) );

			expect( getQuery().date_after ).toEqual( '2020-04-29' );
		} );

		test( 'should filter by between', () => {
			user.selectOptions( ruleSelector, 'between' );

			const dateInputs = getAllByLabelText( 'Choose a date' );
			user.type( dateInputs[ 0 ], '2020-04-19' );
			user.type( dateInputs[ 1 ], '2020-04-29' );
			user.click( getByText( 'Filter' ) );

			expect( getQuery().date_between ).toEqual( [ '2020-04-19', '2020-04-29' ] );
		} );
	} );

	describe( 'when filtering by type', () => {
		let ruleSelector;

		beforeEach( () => {
			addFilter( 'Type' );
			ruleSelector = getByLabelText( 'Select a transaction type filter match' );
		} );

		test( 'should filter by is', () => {
			user.selectOptions( ruleSelector, 'is' );

			user.selectOptions( getByLabelText( 'Select a transaction type' ), 'charge' );
			user.click( getByText( 'Filter' ) );

			expect( getQuery().type_is ).toEqual( 'charge' );
		} );

		test( 'should filter by is_not', () => {
			user.selectOptions( ruleSelector, 'is_not' );

			user.selectOptions( getByLabelText( 'Select a transaction type' ), 'dispute' );
			user.click( getByText( 'Filter' ) );

			expect( getQuery().type_is_not ).toEqual( 'dispute' );
		} );

		test( 'should filter by refund', () => {
			user.selectOptions( ruleSelector, 'is' );

			user.selectOptions( getByLabelText( 'Select a transaction type' ), 'refund' );
			user.click( getByText( 'Filter' ) );

			expect( getQuery().type_is ).toEqual( 'refund' );
		} );
	} );

	function addFilter( filter ) {
		user.click( getByText( 'Add a Filter' ) );
		user.click( getByText( filter ) );
	}
} );
