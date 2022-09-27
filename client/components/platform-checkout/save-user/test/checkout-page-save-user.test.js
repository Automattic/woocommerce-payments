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
import { getConfig } from 'utils/checkout';
// eslint-disable-next-line import/no-unresolved
import { extensionCartUpdate } from '@woocommerce/blocks-checkout';

jest.mock( '../../hooks/use-platform-checkout-user', () => jest.fn() );
jest.mock( '../../hooks/use-selected-payment-method', () => jest.fn() );
jest.mock( 'utils/checkout', () => ( {
	getConfig: jest.fn(),
} ) );
jest.mock(
	'@woocommerce/blocks-checkout',
	() => ( {
		extensionCartUpdate: jest.fn(),
	} ),
	{ virtual: true }
);

describe( 'CheckoutPageSaveUser', () => {
	beforeEach( () => {
		usePlatformCheckoutUser.mockImplementation( () => false );

		useSelectedPaymentMethod.mockImplementation( () => ( {
			isWCPayChosen: true,
			isNewPaymentTokenChosen: true,
		} ) );

		getConfig.mockImplementation(
			( setting ) => 'forceNetworkSavedCards' === setting
		);
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

	it( 'should not render checkbox for saving Platform Checkout user when forceNetworkSavedCards is false', () => {
		getConfig.mockImplementation( () => false );

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

	it( 'should render the save user form when checkbox is checked for classic checkout', () => {
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

	it( 'should render the save user form when checkbox is checked for blocks checkout', () => {
		render( <CheckoutPageSaveUser isBlocksCheckout={ true } /> );

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

	it( 'call `extensionCartUpdate` on blocks checkout when `place order` button is clicked', () => {
		extensionCartUpdate.mockResolvedValue( {} );
		const placeOrderButton = document.createElement( 'button' );
		placeOrderButton.classList.add(
			'wc-block-components-checkout-place-order-button'
		);
		document.body.appendChild( placeOrderButton );

		render( <CheckoutPageSaveUser isBlocksCheckout={ true } /> );

		expect( extensionCartUpdate ).not.toHaveBeenCalled();

		// click on the button
		userEvent.click( placeOrderButton );

		expect( extensionCartUpdate ).toHaveBeenCalledTimes( 1 );
	} );
} );
