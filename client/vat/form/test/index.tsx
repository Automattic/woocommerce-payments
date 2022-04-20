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
const mockOnCompleted = jest.fn();

const waitForVatValidationRequest = async ( vatNumber: string ) => {
	return waitFor( () => {
		expect( mockApiFetch ).toHaveBeenCalledWith( {
			path: `/wc/v3/payments/vat/${ vatNumber }`,
		} );
	} );
};

const waitForVatSaveDetailsRequest = async ( data: {
	vat_number?: string;
	name: string;
	address: string;
} ) => {
	return waitFor( () => {
		expect( mockApiFetch ).toHaveBeenCalledWith( {
			data: data,
			method: 'POST',
			path: `/wc/v3/payments/vat`,
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

			render( <VatForm onCompleted={ mockOnCompleted } /> );

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

		render( <VatForm onCompleted={ mockOnCompleted } /> );
	} );

	afterEach( () => {
		mockOnCompleted.mockClear();
	} );

	it( 'should start with the first task active', () => {
		expect( screen.getByRole( 'list' ).firstChild ).toHaveClass(
			'is-active'
		);
		expect( screen.getByRole( 'list' ).firstChild ).not.toHaveClass(
			'is-completed'
		);
	} );

	it( 'should start with the second task inactive', () => {
		expect( screen.getByRole( 'list' ).lastChild ).not.toHaveClass(
			'is-active'
		);
		expect( screen.getByRole( 'list' ).lastChild ).not.toHaveClass(
			'is-completed'
		);
	} );

	describe( 'when not registered for VAT', () => {
		it( 'should enable the Continue button', () => {
			expect( screen.getByText( 'Continue' ) ).toBeEnabled();
		} );

		describe( 'after submitting the vat number step', () => {
			beforeEach( () => {
				user.click( screen.getByText( 'Continue' ) );
			} );

			it( 'should proceed to the company-data step', () => {
				expect( screen.getByRole( 'list' ).firstChild ).toHaveClass(
					'is-completed'
				);

				expect( screen.getByRole( 'list' ).lastChild ).toHaveClass(
					'is-active'
				);
			} );

			it( 'should disable the Confirm button', () => {
				expect( screen.getByText( 'Confirm' ) ).toBeDisabled();
			} );

			describe( 'after filling the company details', () => {
				beforeEach( () => {
					user.type(
						screen.getByLabelText( 'Business name' ),
						'Test company'
					);
					user.type(
						screen.getByLabelText( 'Address' ),
						'Test address'
					);
				} );

				it( 'should enable the Confirm button', () => {
					expect( screen.getByText( 'Confirm' ) ).toBeEnabled();
				} );

				it( 'should display an error message when VAT details fail to be submitted', async () => {
					mockApiFetch.mockRejectedValue(
						new Error(
							'An error occurred when saving the VAT details'
						)
					);

					user.click( screen.getByText( 'Confirm' ) );

					await waitForVatSaveDetailsRequest( {
						name: 'Test company',
						address: 'Test address',
					} );

					expect(
						screen.getByRole( 'list' ).lastChild
					).not.toHaveClass( 'is-completed' );

					// This will fail if no notices are in the document, and will pass if one or more are found.
					// The "more" part is needed because notices are added twice to the document due to a11y.
					screen.getAllByText(
						'An error occurred when saving the VAT details'
					);

					expect( mockOnCompleted ).not.toHaveBeenCalled();
				} );

				it( 'should complete the form when the VAT details are submitted successfully', async () => {
					mockApiFetch.mockResolvedValueOnce( {
						address: 'Test address',
						name: 'Test company',
						vat_number: null,
					} );

					user.click( screen.getByText( 'Confirm' ) );

					await waitForVatSaveDetailsRequest( {
						name: 'Test company',
						address: 'Test address',
					} );

					expect( screen.getByRole( 'list' ).lastChild ).toHaveClass(
						'is-completed'
					);

					expect( mockOnCompleted ).toHaveBeenCalledWith(
						null,
						'Test company',
						'Test address'
					);
				} );
			} );
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
				mockApiFetch.mockResolvedValueOnce( {
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

				expect( screen.getByRole( 'list' ).lastChild ).toHaveClass(
					'is-active'
				);
			} );

			describe( 'after submitting the vat number step', () => {
				beforeEach( async () => {
					mockApiFetch.mockResolvedValueOnce( {
						address: 'Test address',
						country_code: 'GB',
						name: 'Test company',
						valid: true,
						vat_number: '123456789',
					} );

					user.click( screen.getByText( 'Continue' ) );

					await waitForVatValidationRequest( '123456789' );
				} );

				it( 'should pre-fill the business name with the value from the VAT check', () => {
					expect(
						screen.getByLabelText( 'Business name' )
					).toHaveValue( 'Test company' );
				} );

				it( 'should pre-fill the business address with the value from the VAT check', () => {
					expect( screen.getByLabelText( 'Address' ) ).toHaveValue(
						'Test address'
					);
				} );

				it( 'should display an error message when VAT details fail to be submitted', async () => {
					mockApiFetch.mockRejectedValue(
						new Error(
							'An error occurred when saving the VAT details'
						)
					);

					user.click( screen.getByText( 'Confirm' ) );

					await waitForVatSaveDetailsRequest( {
						vat_number: '123456789',
						name: 'Test company',
						address: 'Test address',
					} );

					expect(
						screen.getByRole( 'list' ).lastChild
					).not.toHaveClass( 'is-completed' );

					// This will fail if no notices are in the document, and will pass if one or more are found.
					// The "more" part is needed because notices are added twice to the document due to a11y.
					screen.getAllByText(
						'An error occurred when saving the VAT details'
					);

					expect( mockOnCompleted ).not.toHaveBeenCalled();
				} );

				it( 'should complete the form when the VAT details are submitted successfully', async () => {
					mockApiFetch.mockResolvedValueOnce( {
						address: 'Test address',
						name: 'Test company',
						vat_number: '123456789',
					} );

					user.click( screen.getByText( 'Confirm' ) );

					await waitForVatSaveDetailsRequest( {
						vat_number: '123456789',
						name: 'Test company',
						address: 'Test address',
					} );

					expect( screen.getByRole( 'list' ).lastChild ).toHaveClass(
						'is-completed'
					);

					expect( mockOnCompleted ).toHaveBeenCalledWith(
						'123456789',
						'Test company',
						'Test address'
					);
				} );
			} );
		} );
	} );
} );
