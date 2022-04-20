/** @format */

/**
 * External dependencies
 */
import { render, screen, waitFor } from '@testing-library/react';
import user from '@testing-library/user-event';
import React from 'react';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import VatForm from '..';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

const mockApiFetch = apiFetch as jest.MockedFunction< typeof apiFetch >;

const waitForVatValidationRequest = async ( vatNumber: string ) => {
	return waitFor( () => {
		expect( mockApiFetch ).toHaveBeenCalledWith( {
			path: `/wc/v3/payments/vat/${ vatNumber }`,
		} );
	} );
};

declare const global: {
	wcpaySettings: {
		accountStatus: {
			country: string;
		};
	};
};

const countries = [
	[ 'GB', 'GB' ],
	[ 'DE', 'DE' ],
	[ 'GR', 'EL' ],
	[ 'CH', 'CHE' ],
];

describe( 'VAT form', () => {
	it.each( countries )(
		'should display the right prefix for country %s',
		( country, expectedPrefix ) => {
			global.wcpaySettings = {
				accountStatus: { country: country },
			};

			render( <VatForm /> );

			user.click(
				screen.getByLabelText( 'I’m registered for a VAT number' )
			);

			expect(
				screen.getByRole( 'textbox', { name: 'VAT Number' } )
			).toHaveValue( `${ expectedPrefix } ` );
		}
	);
} );

describe( 'VAT form', () => {
	beforeEach( () => {
		global.wcpaySettings = {
			accountStatus: { country: 'GB' },
		};

		render( <VatForm /> );
	} );

	it( 'should display a check box to select if registered for VAT', () => {
		screen.getByLabelText( 'I’m registered for a VAT number' );
	} );

	it( 'should start with the first task active', () => {
		expect( screen.getByRole( 'list' ).firstChild ).toHaveClass(
			'is-active'
		);
		expect( screen.getByRole( 'list' ).firstChild ).not.toHaveClass(
			'is-completed'
		);
	} );

	describe( 'when not registered for VAT', () => {
		it( 'should enable the Continue button', () => {
			expect( screen.getByText( 'Continue' ) ).toBeEnabled();
		} );

		it( 'should proceed to the company-data step when submitted', () => {
			user.click( screen.getByText( 'Continue' ) );

			expect( screen.getByRole( 'list' ).firstChild ).toHaveClass(
				'is-completed'
			);
		} );
	} );

	describe( 'when registered for VAT', () => {
		beforeEach( () => {
			user.click(
				screen.getByLabelText( 'I’m registered for a VAT number' )
			);
		} );

		it( 'should disable the Continue button', () => {
			expect( screen.getByText( 'Continue' ) ).toBeDisabled();
		} );

		it( 'should not allow the prefix to be removed', () => {
			const input = screen.getByRole( 'textbox', { name: 'VAT Number' } );

			user.clear( input );
			// Due to the way clear works, we need to "simulate" a keypress with
			// user.type to fire a change event.
			user.type( input, ' ' );

			expect( input ).toHaveValue( 'GB ' );
		} );

		describe( 'after filling a VAT number', () => {
			beforeEach( () => {
				user.type(
					screen.getByRole( 'textbox', { name: 'VAT Number' } ),
					'123456789'
				);
			} );

			it( 'should enable the Continue button', () => {
				expect( screen.getByText( 'Continue' ) ).toBeEnabled();
			} );

			it( 'should display an error message when an invalid VAT number is submitted', async () => {
				mockApiFetch.mockRejectedValue(
					new Error( 'The provided VAT number failed validation' )
				);

				user.click( screen.getByText( 'Continue' ) );

				await waitForVatValidationRequest( '123456789' );

				expect( screen.getByRole( 'list' ).firstChild ).not.toHaveClass(
					'is-completed'
				);

				// This will fail if no notices are in the document, and will pass if one or more are found.
				// The "more" part is needed because notices are added twice to the document due to a11y.
				screen.getAllByText(
					'The provided VAT number failed validation'
				);
			} );

			it( 'should proceed to the company-data step when a valid VAT number is submitted', async () => {
				mockApiFetch.mockResolvedValue( {
					address: 'Test address',
					country_code: 'GB',
					name: 'Test company',
					valid: true,
					vat_number: '123456789',
				} );

				user.click( screen.getByText( 'Continue' ) );

				await waitForVatValidationRequest( '123456789' );

				expect( screen.getByRole( 'list' ).firstChild ).toHaveClass(
					'is-completed'
				);
			} );
		} );
	} );
} );
