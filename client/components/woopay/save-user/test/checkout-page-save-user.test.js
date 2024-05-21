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
import useWooPayUser from '../../hooks/use-woopay-user';
import useSelectedPaymentMethod from '../../hooks/use-selected-payment-method';
import { getConfig } from 'utils/checkout';

jest.mock( '../../hooks/use-woopay-user', () => jest.fn() );
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

jest.mock( 'tracks', () => ( {
	recordUserEvent: jest.fn().mockReturnValue( true ),
	events: {
		WOOPAY_EMAIL_CHECK: 'checkout_email_address_woopay_check',
		WOOPAY_OFFERED: 'checkout_woopay_save_my_info_offered',
		WOOPAY_SKIPPED: 'woopay_skipped',
		WOOPAY_BUTTON_LOAD: 'woopay_button_load',
		WOOPAY_BUTTON_CLICK: 'woopay_button_click',
		WOOPAY_SAVE_MY_INFO_COUNTRY_CLICK:
			'checkout_woopay_save_my_info_country_click',
		WOOPAY_SAVE_MY_INFO_CLICK: 'checkout_save_my_info_click',
		WOOPAY_SAVE_MY_INFO_MOBILE_ENTER:
			'checkout_woopay_save_my_info_mobile_enter',
		WOOPAY_SAVE_MY_INFO_TOS_CLICK: 'checkout_save_my_info_tos_click',
		WOOPAY_SAVE_MY_INFO_PRIVACY_CLICK:
			'checkout_save_my_info_privacy_policy_click',
		WOOPAY_SAVE_MY_INFO_TOOLTIP_CLICK:
			'checkout_save_my_info_tooltip_click',
		WOOPAY_SAVE_MY_INFO_TOOLTIP_LEARN_MORE_CLICK:
			'checkout_save_my_info_tooltip_learn_more_click',
	},
} ) );

const BlocksCheckoutEnvironmentMock = ( { children } ) => (
	<div>
		<button className="wc-block-components-checkout-place-order-button">
			Place order
		</button>
		<input type="text" id="phone" value="+12015555551" />
		<input type="text" id="shipping-phone" value="+12015555552" />
		<input type="text" id="billing-phone" value="+12015555553" />
		{ children }
	</div>
);

describe( 'CheckoutPageSaveUser', () => {
	beforeEach( () => {
		useWooPayUser.mockImplementation( () => false );
		extensionCartUpdate.mockResolvedValue( {} );

		useSelectedPaymentMethod.mockImplementation( () => ( {
			isWCPayChosen: true,
			isNewPaymentTokenChosen: true,
		} ) );

		getConfig.mockImplementation(
			( setting ) => setting === 'forceNetworkSavedCards'
		);

		window.wcpaySettings = {
			accountStatus: {
				country: 'US',
			},
		};
	} );

	afterEach( () => {
		jest.resetAllMocks();
	} );

	it( 'should render checkbox for saving WooPay user when user is not registered and selected payment method is card', () => {
		render( <CheckoutPageSaveUser /> );
		expect(
			screen.queryByLabelText(
				'Securely save my information for 1-click checkout'
			)
		).toBeInTheDocument();
		expect(
			screen.queryByLabelText(
				'Securely save my information for 1-click checkout'
			)
		).not.toBeChecked();
	} );

	it( 'should not render checkbox for saving WooPay user when user is already registered', () => {
		useWooPayUser.mockImplementation( () => true );

		render( <CheckoutPageSaveUser /> );
		expect(
			screen.queryByLabelText(
				'Securely save my information for 1-click checkout'
			)
		).not.toBeInTheDocument();
	} );

	it( 'should not render checkbox for saving WooPay user when forceNetworkSavedCards is false', () => {
		getConfig.mockImplementation( () => false );

		render( <CheckoutPageSaveUser /> );
		expect(
			screen.queryByLabelText(
				'Securely save my information for 1-click checkout'
			)
		).not.toBeInTheDocument();
	} );

	it( 'should render checkbox for saving WooPay user when selected payment method is not card', () => {
		useSelectedPaymentMethod.mockImplementation( () => ( {
			isWCPayChosen: false,
		} ) );

		render( <CheckoutPageSaveUser /> );
		expect(
			screen.queryByLabelText(
				'Securely save my information for 1-click checkout'
			)
		).not.toBeInTheDocument();
	} );

	it( 'should render the save user form when checkbox is checked for classic checkout', () => {
		render( <CheckoutPageSaveUser /> );

		const label = screen.getByLabelText(
			'Securely save my information for 1-click checkout'
		);

		expect( label ).not.toBeChecked();
		expect(
			screen.queryByTestId( 'save-user-form' )
		).not.toBeInTheDocument();

		// click on the checkbox
		userEvent.click( label );

		expect( label ).toBeChecked();
		expect( screen.queryByTestId( 'save-user-form' ) ).toBeInTheDocument();
	} );

	it( 'should render the save user form when checkbox is checked for blocks checkout', () => {
		render( <CheckoutPageSaveUser isBlocksCheckout={ true } />, {
			wrapper: BlocksCheckoutEnvironmentMock,
		} );

		const label = screen.getByLabelText(
			'Securely save my information for 1-click checkout'
		);

		expect( label ).not.toBeChecked();
		expect(
			screen.queryByTestId( 'save-user-form' )
		).not.toBeInTheDocument();

		// click on the checkbox
		userEvent.click( label );

		expect( label ).toBeChecked();
		expect( screen.queryByTestId( 'save-user-form' ) ).toBeInTheDocument();
	} );

	it( 'should not call `extensionCartUpdate` on classic checkout when checkbox is clicked', () => {
		extensionCartUpdate.mockResolvedValue( {} );

		render( <CheckoutPageSaveUser isBlocksCheckout={ false } /> );

		expect( extensionCartUpdate ).not.toHaveBeenCalled();

		// click on the checkbox
		userEvent.click(
			screen.queryByLabelText(
				'Securely save my information for 1-click checkout'
			)
		);

		expect( extensionCartUpdate ).not.toHaveBeenCalled();
	} );

	it( 'call `extensionCartUpdate` on blocks checkout when checkbox is clicked', async () => {
		render( <CheckoutPageSaveUser isBlocksCheckout={ true } />, {
			wrapper: BlocksCheckoutEnvironmentMock,
		} );

		const label = screen.getByLabelText(
			'Securely save my information for 1-click checkout'
		);

		expect( label ).not.toBeChecked();
		expect( extensionCartUpdate ).not.toHaveBeenCalled();

		// click on the checkbox to select
		userEvent.click( label );

		expect( label ).toBeChecked();
		await waitFor( () =>
			expect( extensionCartUpdate ).toHaveBeenCalledWith( {
				namespace: 'woopay',
				data: {
					save_user_in_woopay: true,
					woopay_source_url: 'http://localhost/',
					woopay_is_blocks: true,
					woopay_viewport: '0x0',
					woopay_user_phone_field: {
						full: '+12015555551',
					},
				},
			} )
		);

		// click on the checkbox to unselect
		userEvent.click( label );

		expect( label ).not.toBeChecked();
		await waitFor( () =>
			expect( extensionCartUpdate ).toHaveBeenCalledWith( {
				namespace: 'woopay',
				data: {},
			} )
		);
	} );

	it( 'fills the phone input on blocks checkout with phone number field fallback', async () => {
		render( <CheckoutPageSaveUser isBlocksCheckout={ true } />, {
			wrapper: BlocksCheckoutEnvironmentMock,
		} );

		const saveMyInfoCheckbox = screen.getByLabelText(
			'Securely save my information for 1-click checkout'
		);
		// initial state
		expect( saveMyInfoCheckbox ).not.toBeChecked();

		// click on the checkbox to show the phone field, input should be filled with the first phone input field
		userEvent.click( saveMyInfoCheckbox );
		expect( saveMyInfoCheckbox ).toBeChecked();
		expect( screen.getByLabelText( 'Mobile phone number' ).value ).toEqual(
			'2015555551'
		);

		// click on the checkbox to hide/show it again (and reset the previously entered values)
		userEvent.click( saveMyInfoCheckbox );
		document.getElementById( 'phone' ).remove();
		await waitFor( () => expect( extensionCartUpdate ).toHaveBeenCalled() );

		userEvent.click( saveMyInfoCheckbox );
		expect( saveMyInfoCheckbox ).toBeChecked();
		expect( screen.getByLabelText( 'Mobile phone number' ).value ).toEqual(
			'2015555552'
		);

		// click on the checkbox to hide/show it again (and reset the previously entered values)
		userEvent.click( saveMyInfoCheckbox );
		document.getElementById( 'shipping-phone' ).remove();
		await waitFor( () => expect( extensionCartUpdate ).toHaveBeenCalled() );

		userEvent.click( saveMyInfoCheckbox );
		expect( saveMyInfoCheckbox ).toBeChecked();
		expect( screen.getByLabelText( 'Mobile phone number' ).value ).toEqual(
			'2015555553'
		);

		// click on the checkbox to hide/show it again (and reset the previously entered values)
		userEvent.click( saveMyInfoCheckbox );
		document.getElementById( 'billing-phone' ).remove();
		await waitFor( () => expect( extensionCartUpdate ).toHaveBeenCalled() );

		userEvent.click( saveMyInfoCheckbox );
		expect( saveMyInfoCheckbox ).toBeChecked();
		expect( screen.getByLabelText( 'Mobile phone number' ).value ).toEqual(
			''
		);
		await waitFor( () => expect( extensionCartUpdate ).toHaveBeenCalled() );
	} );

	it( 'call `extensionCartUpdate` on blocks checkout when checkbox is clicked with a phone without country code', async () => {
		render( <CheckoutPageSaveUser isBlocksCheckout={ true } />, {
			wrapper: BlocksCheckoutEnvironmentMock,
		} );

		const label = screen.getByLabelText(
			'Securely save my information for 1-click checkout'
		);

		expect( label ).not.toBeChecked();
		expect( extensionCartUpdate ).not.toHaveBeenCalled();

		// click on the checkbox to select
		userEvent.click( label );

		expect( label ).toBeChecked();
		await waitFor( () =>
			expect( extensionCartUpdate ).toHaveBeenCalledWith( {
				namespace: 'woopay',
				data: {
					save_user_in_woopay: true,
					woopay_source_url: 'http://localhost/',
					woopay_is_blocks: true,
					woopay_viewport: '0x0',
					woopay_user_phone_field: {
						full: '+12015555551',
					},
				},
			} )
		);

		// click on the checkbox to unselect
		userEvent.click( label );

		expect( label ).not.toBeChecked();
		await waitFor( () =>
			expect( extensionCartUpdate ).toHaveBeenCalledWith( {
				namespace: 'woopay',
				data: {},
			} )
		);
	} );
} );
