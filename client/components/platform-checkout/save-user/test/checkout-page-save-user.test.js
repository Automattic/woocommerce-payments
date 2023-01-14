/**
 * External dependencies
 */
import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
// eslint-disable-next-line import/no-unresolved
import { extensionCartUpdate } from '@woocommerce/blocks-checkout';

/**
 * Internal dependencies
 */
import CheckoutPageSaveUser from '../checkout-page-save-user';
import usePlatformCheckoutUser from '../../hooks/use-platform-checkout-user';
import useSelectedPaymentMethod from '../../hooks/use-selected-payment-method';
import { getConfig } from 'utils/checkout';

global.jQuery = jest.fn( () => ( {
	on: jest.fn(),
	off: jest.fn(),
} ) );

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
jest.mock( '@wordpress/data', () => ( {
	useDispatch: jest.fn().mockReturnValue( {
		setBillingAddress: jest.fn(),
		setShippingAddress: jest.fn(),
	} ),
} ) );

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

		const billingCountryField = document.createElement( 'select' );
		billingCountryField.setAttribute( 'id', 'billing_country' );
		document.body.appendChild( billingCountryField );
	} );

	afterEach( () => {
		jest.restoreAllMocks();
	} );

	it( 'should render checkbox for saving Platform Checkout user when user is not registered and selected payment method is card', () => {
		render( <CheckoutPageSaveUser /> );
		expect(
			screen.queryByLabelText(
				'Save my information for a faster and secure checkout'
			)
		).toBeInTheDocument();
		expect(
			screen.queryByLabelText(
				'Save my information for a faster and secure checkout'
			)
		).not.toBeChecked();
	} );

	it( 'should not render checkbox for saving Platform Checkout user when user is already registered', () => {
		usePlatformCheckoutUser.mockImplementation( () => true );

		render( <CheckoutPageSaveUser /> );
		expect(
			screen.queryByLabelText(
				'Save my information for a faster and secure checkout'
			)
		).not.toBeInTheDocument();
	} );

	it( 'should not render checkbox for saving Platform Checkout user when forceNetworkSavedCards is false', () => {
		getConfig.mockImplementation( () => false );

		render( <CheckoutPageSaveUser /> );
		expect(
			screen.queryByLabelText(
				'Save my information for a faster and secure checkout'
			)
		).not.toBeInTheDocument();
	} );

	it( 'should render checkbox for saving Platform Checkout user when selected payment method is not card', () => {
		useSelectedPaymentMethod.mockImplementation( () => ( {
			isWCPayChosen: false,
		} ) );

		render( <CheckoutPageSaveUser /> );
		expect(
			screen.queryByLabelText(
				'Save my information for a faster and secure checkout'
			)
		).not.toBeInTheDocument();
	} );

	it( 'should render the save user form when checkbox is checked for classic checkout', () => {
		render( <CheckoutPageSaveUser /> );

		const saveUserForm = screen.getByTestId( 'save-user-form' );
		const label = screen.getByLabelText(
			'Save my information for a faster and secure checkout'
		);

		expect( label ).not.toBeChecked();
		expect( saveUserForm.classList.contains( 'visible' ) ).toBe( false );

		// click on the checkbox
		userEvent.click( label );

		expect( label ).toBeChecked();
		expect( saveUserForm.classList.contains( 'visible' ) ).toBe( true );
	} );

	it( 'should render the save user form when checkbox is checked for blocks checkout', () => {
		render( <CheckoutPageSaveUser isBlocksCheckout={ true } /> );

		const saveUserForm = screen.getByTestId( 'save-user-form' );
		const label = screen.getByLabelText(
			'Save my information for a faster and secure checkout'
		);

		expect( label ).not.toBeChecked();
		expect( saveUserForm.classList.contains( 'visible' ) ).toBe( false );

		// click on the checkbox
		userEvent.click( label );

		expect( label ).toBeChecked();
		expect( saveUserForm.classList.contains( 'visible' ) ).toBe( true );
	} );

	it( 'should not call `extensionCartUpdate` on classic checkout when checkbox is clicked', () => {
		extensionCartUpdate.mockResolvedValue( {} );

		render( <CheckoutPageSaveUser isBlocksCheckout={ false } /> );

		expect( extensionCartUpdate ).not.toHaveBeenCalled();

		// click on the checkbox
		userEvent.click(
			screen.queryByLabelText(
				'Save my information for a faster and secure checkout'
			)
		);

		expect( extensionCartUpdate ).not.toHaveBeenCalled();
	} );

	it( 'call `extensionCartUpdate` on blocks checkout when checkbox is clicked', async () => {
		extensionCartUpdate.mockResolvedValue( {} );
		const placeOrderButton = document.createElement( 'button' );
		placeOrderButton.classList.add(
			'wc-block-components-checkout-place-order-button'
		);
		document.body.appendChild( placeOrderButton );
		const phoneField = document.createElement( 'input' );
		phoneField.setAttribute( 'id', 'phone' );
		phoneField.value = '+12015555555';
		document.body.appendChild( phoneField );

		render( <CheckoutPageSaveUser isBlocksCheckout={ true } /> );

		const label = screen.getByLabelText(
			'Save my information for a faster and secure checkout'
		);

		expect( label ).not.toBeChecked();
		expect( extensionCartUpdate ).not.toHaveBeenCalled();

		// click on the checkbox to select
		userEvent.click( label );

		expect( label ).toBeChecked();
		await waitFor( () =>
			expect( extensionCartUpdate ).toHaveBeenCalledWith( {
				namespace: 'platform-checkout',
				data: {
					save_user_in_platform_checkout: true,
					platform_checkout_marketing_optin: true,
					platform_checkout_user_phone_field: {
						full: '+12015555555',
					},
				},
			} )
		);

		// click on the checkbox to unselect
		userEvent.click( label );

		expect( label ).not.toBeChecked();
		await waitFor( () =>
			expect( extensionCartUpdate ).toHaveBeenCalledWith( {
				namespace: 'platform-checkout',
				data: {},
			} )
		);
	} );
} );
