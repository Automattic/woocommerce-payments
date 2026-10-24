/**
 * External dependencies
 */
import { fireEvent, render, screen, waitFor } from '@testing-library/react';

/**
 * Internal dependencies
 */
import SupportPhoneInput from '..';
import {
	useGetSavingError,
	useAccountBusinessSupportPhone,
	useTestModeOnboarding,
} from 'wcpay/data/settings';

jest.mock( 'wcpay/data/settings', () => ( {
	useAccountBusinessSupportPhone: jest.fn(),
	useGetSavingError: jest.fn(),
	useTestModeOnboarding: jest.fn(),
} ) );

// The phone input is lazy-loaded behind a Suspense boundary, so it mounts (and
// the number is validated) asynchronously. Wait for the country dropdown to
// appear before asserting on validation state.
const waitForPhoneInputReady = () =>
	screen.findByRole( 'combobox', { name: /:\s*\+/ } );

const LABEL = 'Support phone number (required)';
const ERROR_MESSAGE =
	'A support phone number is required. Please enter a valid phone number.';
const HELP_TEXT =
	// eslint-disable-next-line max-len
	"This number may appear on customer bank statements and in-person purchase receipts, but not in order emails. Use a number you're comfortable sharing publicly.";

describe( 'SupportPhoneInput', () => {
	beforeEach( () => {
		useAccountBusinessSupportPhone.mockReturnValue( [
			'+12345678901',
			jest.fn(),
		] );
		useGetSavingError.mockReturnValue( null );
		useTestModeOnboarding.mockReturnValue( false );
		window.wcpaySettings = {
			accountStatus: {
				country: 'US',
			},
		};
	} );

	it( 'explains where the number is exposed', async () => {
		render( <SupportPhoneInput /> );

		await waitForPhoneInputReady();

		expect( screen.getByText( HELP_TEXT ) ).toBeInTheDocument();
	} );

	it( 'updates phone input', async () => {
		const setSupportPhone = jest.fn();
		useAccountBusinessSupportPhone.mockReturnValue( [
			'+12345678901',
			setSupportPhone,
		] );
		render( <SupportPhoneInput /> );

		await waitForPhoneInputReady();

		const newPhone = '+12377778888';
		fireEvent.change( screen.getByLabelText( LABEL ), {
			target: { value: newPhone },
		} );

		expect( setSupportPhone ).toHaveBeenCalledWith( newPhone );
	} );

	it( 'displays error message for empty phone input when it has been set', async () => {
		useAccountBusinessSupportPhone.mockReturnValue( [
			'+12345678901',
			jest.fn(),
		] );
		const { container } = render( <SupportPhoneInput /> );

		await waitForPhoneInputReady();

		// In the first render, the phone number has been set correctly, so the error message is not displayed.
		expect(
			container.querySelector( '.components-notice.is-error' )
		).toBeNull();

		// Mock that the phone number input is set to empty.
		useAccountBusinessSupportPhone.mockReturnValue( [ '', jest.fn() ] );

		fireEvent.change( screen.getByLabelText( LABEL ), {
			target: { value: '' },
		} );

		// The error message is displayed.
		await waitFor( () =>
			expect(
				container.querySelector( '.components-notice.is-error' )
					.textContent
			).toMatch( ERROR_MESSAGE )
		);
	} );

	it( 'error message for empty phone input when it has not been set', async () => {
		useAccountBusinessSupportPhone.mockReturnValue( [ '', jest.fn() ] );

		const { container } = render( <SupportPhoneInput /> );

		await waitForPhoneInputReady();

		await waitFor( () =>
			expect(
				container.querySelector( '.components-notice.is-error' )
					.textContent
			).toMatch( ERROR_MESSAGE )
		);
	} );

	it( 'displays the error message for invalid phone', async () => {
		useAccountBusinessSupportPhone.mockReturnValue( [
			'+12345', // invalid phone number.
			jest.fn(),
		] );

		const { container } = render( <SupportPhoneInput /> );

		await waitForPhoneInputReady();

		await waitFor( () =>
			expect(
				container.querySelector( '.components-notice.is-error' )
					.textContent
			).toMatch( ERROR_MESSAGE )
		);
	} );

	it( 'Singapore phone number validation special cases - starting with 800, 805, 806, 807, 808 or 809', async () => {
		useAccountBusinessSupportPhone.mockReturnValue( [
			'+6580600000', // test phone number.
			jest.fn(),
		] );
		useTestModeOnboarding.mockReturnValue( true );

		const { container } = render( <SupportPhoneInput /> );

		await waitForPhoneInputReady();

		await waitFor( () =>
			expect(
				container.querySelector( '.components-notice.is-error' )
			).toBeNull()
		);

		for ( const value of [
			'+6580000000',
			'+6580500000',
			'+6580700000',
			'+6580800000',
			'+6580900000',
		] ) {
			fireEvent.change( screen.getByLabelText( LABEL ), {
				target: { value },
			} );
			await waitFor( () =>
				expect(
					container.querySelector( '.components-notice.is-error' )
				).toBeNull()
			);
		}
	} );

	it( 'Hong Kong phone number validation special cases - starting with 4, 7, 8', async () => {
		useAccountBusinessSupportPhone.mockReturnValue( [
			'+85221234567', // test phone number.
			jest.fn(),
		] );
		useTestModeOnboarding.mockReturnValue( true );

		const { container } = render( <SupportPhoneInput /> );

		await waitForPhoneInputReady();

		await waitFor( () =>
			expect(
				container.querySelector( '.components-notice.is-error' )
			).toBeNull()
		);

		for ( const value of [
			'+85241234567',
			'+85271234567',
			'+85281234567',
		] ) {
			fireEvent.change( screen.getByLabelText( LABEL ), {
				target: { value },
			} );
			await waitFor( () =>
				expect(
					container.querySelector( '.components-notice.is-error' )
				).toBeNull()
			);
		}
	} );

	it( 'for test accounts, allow all 0s number', async () => {
		useAccountBusinessSupportPhone.mockReturnValue( [
			'+10000000000', // test phone number.
			jest.fn(),
		] );
		useTestModeOnboarding.mockReturnValue( true );

		const { container } = render( <SupportPhoneInput /> );

		await waitForPhoneInputReady();

		await waitFor( () =>
			expect(
				container.querySelector( '.components-notice.is-error' )
			).toBeNull()
		);
	} );
} );
