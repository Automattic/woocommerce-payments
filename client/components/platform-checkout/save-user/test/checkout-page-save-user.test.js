/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import CheckoutPageSaveUser from '../checkout-page-save-user';
import usePlatformCheckoutUser from '../../hooks/use-platform-checkout-user';
import useSelectedPaymentMethod from '../../hooks/use-selected-payment-method';

jest.mock( '../../hooks/use-platform-checkout-user', () => jest.fn() );
jest.mock( '../../hooks/use-selected-payment-method', () => jest.fn() );

describe( 'CheckoutPageSaveUser', () => {
	beforeEach( () => {
		usePlatformCheckoutUser.mockImplementation( () => false );

		useSelectedPaymentMethod.mockImplementation( () => ( {
			isWCPayChosen: true,
		} ) );
	} );

	afterEach( () => {
		jest.restoreAllMocks();
	} );

	it( 'should render checkbox for saving Platform Checkout user when user is not registered and selected payment method is card', () => {
		render( <CheckoutPageSaveUser /> );
		expect(
			screen.queryByText( 'Remember your details?' )
		).toBeInTheDocument();
		expect(
			screen.queryByLabelText(
				'Save my information for faster checkouts'
			)
		).toBeInTheDocument();
		expect(
			screen.queryByLabelText(
				'Save my information for faster checkouts'
			)
		).not.toBeChecked();
	} );

	it( 'should not render checkbox for saving Platform Checkout user when user is already registered', () => {
		usePlatformCheckoutUser.mockImplementation( () => true );

		render( <CheckoutPageSaveUser /> );
		expect(
			screen.queryByText( 'Remember your details?' )
		).not.toBeInTheDocument();
		expect(
			screen.queryByLabelText(
				'Save my information for faster checkouts'
			)
		).not.toBeInTheDocument();
	} );

	it( 'should render checkbox for saving Platform Checkout user when selected payment method is not card', () => {
		useSelectedPaymentMethod.mockImplementation( () => ( {
			isWCPayChosen: false,
		} ) );

		render( <CheckoutPageSaveUser /> );
		expect(
			screen.queryByText( 'Remember your details?' )
		).not.toBeInTheDocument();
		expect(
			screen.queryByLabelText(
				'Save my information for faster checkouts'
			)
		).not.toBeInTheDocument();
	} );

	it( 'should render the save user form when checkbox is checked', () => {
		render( <CheckoutPageSaveUser /> );

		expect(
			screen.queryByLabelText(
				'Save my information for faster checkouts'
			)
		).not.toBeChecked();
		expect(
			screen.queryByLabelText( 'Mobile phone number' )
		).not.toBeInTheDocument();

		// click on the checkbox
		userEvent.click(
			screen.queryByLabelText(
				'Save my information for faster checkouts'
			)
		);

		expect(
			screen.queryByLabelText(
				'Save my information for faster checkouts'
			)
		).toBeChecked();
		expect(
			screen.queryByLabelText( 'Mobile phone number' )
		).toBeInTheDocument();
	} );
} );
