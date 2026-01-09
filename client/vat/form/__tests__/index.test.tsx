/** @format */

/**
 * External dependencies
 */
import { render, screen, waitFor } from '@testing-library/react';
import user from '@testing-library/user-event';
import React, { act } from 'react';
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

/**
 * Expected prefix and tax ID name for each supported country.
 */
const countryTaxNumberInfo = [
	[ 'GB', 'GB', 'VAT Number' ],
	[ 'DE', 'DE', 'VAT Number' ],
	[ 'GR', 'EL', 'VAT Number' ],
	[ 'CH', 'CHE', 'VAT Number' ],
	[ 'JP', '', 'Corporate Number' ],
	[ 'AU', '', 'ABN' ],
];

describe( 'VAT form', () => {
	it.each( countryTaxNumberInfo )(
		'should display the right prefix for country %s',
		async ( country, expectedPrefix, expectedTaxIdName ) => {
			global.wcpaySettings = {
				accountStatus: { country: country },
			};

			render( <VatForm onCompleted={ mockOnCompleted } /> );

			await act( async () => {
				await user.click(
					screen.getByLabelText(
						`I have a valid ${ expectedTaxIdName }`
					)
				);
			} );

			if ( expectedPrefix ) {
				expect(
					screen.getByRole( 'textbox', {
						name: `${ expectedTaxIdName }`,
					} )
				).toHaveValue( `${ expectedPrefix } ` );
			} else {
				// Special case no prefix (JP) – the value is not necessarily empty string.
				expect(
					screen.getByRole( 'textbox', {
						name: `${ expectedTaxIdName }`,
					} )
				).toBeEmptyDOMElement();
			}
		}
	);
} );

describe( 'VAT form error messages use correct tax ID name per country', () => {
	beforeAll( () => {
		jest.spyOn( console, 'error' ).mockImplementation( () => null );
		jest.spyOn( console, 'warn' ).mockImplementation( () => null );
	} );

	afterEach( () => {
		mockApiFetch.mockClear();
		mockOnCompleted.mockClear();
	} );

	it( 'should display "Corporate Number" in error message for Japan', async () => {
		global.wcpaySettings = {
			accountStatus: { country: 'JP' },
		};

		render( <VatForm onCompleted={ mockOnCompleted } /> );

		await act( async () => {
			await user.click(
				screen.getByLabelText( 'I have a valid Corporate Number' )
			);
		} );

		await act( async () => {
			await user.type(
				screen.getByRole( 'textbox', { name: 'Corporate Number' } ),
				'1234567890123'
			);
		} );

		mockApiFetch.mockRejectedValue( {
			code: 'wcpay_invalid_tax_number',
			message: 'The provided VAT number failed validation.',
		} );

		await act( async () => {
			await user.click( screen.getByText( 'Continue' ) );
		} );

		await waitForVatValidationRequest( '1234567890123' );

		screen.getAllByText(
			'The provided Corporate Number failed validation.'
		);
	} );

	it( 'should display "ABN" in error message for Australia', async () => {
		global.wcpaySettings = {
			accountStatus: { country: 'AU' },
		};

		render( <VatForm onCompleted={ mockOnCompleted } /> );

		await act( async () => {
			await user.click( screen.getByLabelText( 'I have a valid ABN' ) );
		} );

		await act( async () => {
			await user.type(
				screen.getByRole( 'textbox', { name: 'ABN' } ),
				'12345678901'
			);
		} );

		mockApiFetch.mockRejectedValue( {
			code: 'wcpay_invalid_tax_number',
			message: 'The provided VAT number failed validation.',
		} );

		await act( async () => {
			await user.click( screen.getByText( 'Continue' ) );
		} );

		await waitForVatValidationRequest( '12345678901' );

		screen.getAllByText( 'The provided ABN failed validation.' );
	} );
} );

describe( 'VAT form', () => {
	beforeAll( () => {
		jest.spyOn( console, 'error' ).mockImplementation( () => null );
		jest.spyOn( console, 'warn' ).mockImplementation( () => null );
	} );

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
			beforeEach( async () => {
				await act( async () => {
					await user.click( screen.getByText( 'Continue' ) );
				} );
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
				beforeEach( async () => {
					await act( async () => {
						await user.type(
							screen.getByLabelText( 'Business name' ),
							'Test company'
						);
						await user.type(
							screen.getByLabelText( 'Address' ),
							'Test address'
						);
					} );

					expect( screen.getByText( 'Confirm' ) ).toBeEnabled();
				} );

				it( 'should display an error message when VAT details fail to be submitted', async () => {
					mockApiFetch.mockRejectedValue(
						new Error(
							'An error occurred when saving the VAT details'
						)
					);

					await act( async () => {
						await user.click( screen.getByText( 'Confirm' ) );
					} );

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

					await act( async () => {
						await user.click( screen.getByText( 'Confirm' ) );
					} );

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
		beforeEach( async () => {
			await act( async () => {
				await user.click(
					screen.getByLabelText( 'I have a valid VAT Number' )
				);
			} );
		} );

		it( 'should disable the Continue button', () => {
			expect( screen.getByText( 'Continue' ) ).toBeDisabled();
		} );

		it( 'should not allow the prefix to be removed', async () => {
			const input = screen.getByRole( 'textbox', { name: 'VAT Number' } );

			await act( async () => {
				await user.clear( input );
			} );
			// Due to the way clear works, we need to "simulate" a keypress with
			// await user.type to fire a change event.
			await act( async () => {
				await user.type( input, ' ' );
			} );

			expect( input ).toHaveValue( 'GB ' );
		} );

		describe( 'after filling a VAT number', () => {
			beforeEach( async () => {
				await act( async () => {
					await user.type(
						screen.getByRole( 'textbox', { name: 'VAT Number' } ),
						'123456789'
					);
				} );
			} );

			it( 'should enable the Continue button', () => {
				expect( screen.getByText( 'Continue' ) ).toBeEnabled();
			} );

			it( 'should display a localized error message when an invalid VAT number is submitted', async () => {
				mockApiFetch.mockRejectedValue( {
					code: 'wcpay_invalid_tax_number',
					message: 'The provided VAT number failed validation.',
				} );

				await act( async () => {
					await user.click( screen.getByText( 'Continue' ) );
				} );

				await waitForVatValidationRequest( '123456789' );

				expect( screen.getByRole( 'list' ).firstChild ).not.toHaveClass(
					'is-completed'
				);

				// Verify the localized error message is displayed (uses "VAT Number" for GB).
				screen.getAllByText(
					'The provided VAT Number failed validation.'
				);
			} );

			it( 'should fall back to server message for unknown error codes', async () => {
				mockApiFetch.mockRejectedValue( {
					code: 'some_unknown_error_code',
					message: 'Some unknown server error message',
				} );

				await act( async () => {
					await user.click( screen.getByText( 'Continue' ) );
				} );

				await waitForVatValidationRequest( '123456789' );

				screen.getAllByText( 'Some unknown server error message' );
			} );

			it( 'should proceed to the company-data step when a valid VAT number is submitted', async () => {
				mockApiFetch.mockResolvedValueOnce( {
					address: 'Test address',
					country_code: 'GB',
					name: 'Test company',
					valid: true,
					vat_number: '123456789',
				} );

				await act( async () => {
					await user.click( screen.getByText( 'Continue' ) );
				} );

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

					await act( async () => {
						await user.click( screen.getByText( 'Continue' ) );
					} );

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

					await act( async () => {
						await user.click( screen.getByText( 'Confirm' ) );
					} );

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

					await act( async () => {
						await user.click( screen.getByText( 'Confirm' ) );
					} );

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
