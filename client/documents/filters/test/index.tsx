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
import { DocumentsFilters } from '../';

function addAdvancedFilter( filter: string ) {
	user.click( screen.getByRole( 'button', { name: /Add a Filter/i } ) );
	user.click( screen.getByRole( 'button', { name: filter } ) );
}

describe( 'Documents filters', () => {
	beforeEach( () => {
		// the query string is preserved across tests, so we need to reset it
		updateQueryString( {}, '/', {} );

		const { rerender } = render( <DocumentsFilters /> );

		// select advanced filter view
		user.click( screen.getByRole( 'button', { name: /All documents/i } ) );
		user.click(
			screen.getByRole( 'button', { name: /Advanced filters/i } )
		);
		rerender( <DocumentsFilters /> );
	} );

	describe( 'when filtering by date', () => {
		let ruleSelector: HTMLElement;

		beforeEach( () => {
			addAdvancedFilter( 'Date' );
			ruleSelector = screen.getByRole( 'combobox', {
				name: /document date filter/i,
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
				name: /document type filter/i,
			} );
		} );

		test( 'should render all types', () => {
			const typeSelect = screen.getByRole( 'combobox', {
				name: /document type$/i,
			} ) as HTMLSelectElement;
			expect( typeSelect.options ).toMatchSnapshot();
		} );

		test( 'should filter by is', () => {
			user.selectOptions( ruleSelector, 'is' );

			// need to include $ in name, otherwise "Select a document type filter" is also matched.
			user.selectOptions(
				screen.getByRole( 'combobox', { name: /document type$/i } ),
				'test_document'
			);
			user.click( screen.getByRole( 'link', { name: /Filter/ } ) );

			expect( getQuery().type_is ).toEqual( 'test_document' );
		} );

		test( 'should filter by is_not', () => {
			user.selectOptions( ruleSelector, 'is_not' );

			// need to include $ in name, otherwise "Select a document type filter" is also matched.
			user.selectOptions(
				screen.getByRole( 'combobox', { name: /document type$/i } ),
				'test_document'
			);
			user.click( screen.getByRole( 'link', { name: /Filter/ } ) );

			expect( getQuery().type_is_not ).toEqual( 'test_document' );
		} );
	} );
} );
