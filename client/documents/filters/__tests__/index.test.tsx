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
import { DocumentsFilters } from '../';

// TODO: this is a bit of a hack as we're mocking an old version of WC, we should relook at this.
jest.mock( '@woocommerce/settings', () => ( {
	...jest.requireActual( '@woocommerce/settings' ),
	getSetting: jest.fn( ( key ) => ( key === 'wcVersion' ? 7.8 : '' ) ),
} ) );

async function addAdvancedFilter( filter: string ) {
	await user.click( screen.getByRole( 'button', { name: /Add a Filter/i } ) );
	await user.click( screen.getByRole( 'button', { name: filter } ) );
}

describe( 'Documents filters', () => {
	beforeAll( () => {
		jest.spyOn( console, 'error' ).mockImplementation( () => null );
		jest.spyOn( console, 'warn' ).mockImplementation( () => null );
	} );

	beforeEach( async () => {
		// the query string is preserved across tests, so we need to reset it
		updateQueryString( {}, '/', {} );

		const { rerender } = render( <DocumentsFilters /> );

		// select advanced filter view
		await user.click(
			screen.getByRole( 'button', { name: /All documents/i } )
		);
		await user.click(
			screen.getByRole( 'button', { name: /Advanced filters/i } )
		);
		rerender( <DocumentsFilters /> );
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
			ruleSelector = await screen.findByRole( 'combobox', {
				name: /document date filter/i,
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
				name: /document type filter/i,
			} );
		} );

		test( 'should render all types', () => {
			const typeSelect = screen.getByRole( 'combobox', {
				name: /document type$/i,
			} ) as HTMLSelectElement;
			expect( typeSelect.options ).toMatchSnapshot();
		} );

		test( 'should filter by is', async () => {
			await user.selectOptions( ruleSelector, 'is' );

			// need to include $ in name, otherwise "Select a document type filter" is also matched.
			await user.selectOptions(
				screen.getByRole( 'combobox', { name: /document type$/i } ),
				'vat_invoice'
			);
			await user.click( screen.getByRole( 'link', { name: /Filter/ } ) );

			expect( getQuery().type_is ).toEqual( 'vat_invoice' );
		} );

		test( 'should filter by is_not', async () => {
			await user.selectOptions( ruleSelector, 'is_not' );

			// need to include $ in name, otherwise "Select a document type filter" is also matched.
			await user.selectOptions(
				screen.getByRole( 'combobox', { name: /document type$/i } ),
				'vat_invoice'
			);
			await user.click( screen.getByRole( 'link', { name: /Filter/ } ) );

			expect( getQuery().type_is_not ).toEqual( 'vat_invoice' );
		} );
	} );
} );
