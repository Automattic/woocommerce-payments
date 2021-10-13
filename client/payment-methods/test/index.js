/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { fireEvent, render, screen } from '@testing-library/react';
import user from '@testing-library/user-event';
import WCPaySettingsContext from '../../settings/wcpay-settings-context';

/**
 * Internal dependencies
 */
import PaymentMethods from '..';
import {
	useEnabledPaymentMethodIds,
	useGetAvailablePaymentMethodIds,
} from 'wcpay/data';
import WcPayUpeContextProvider from '../../settings/wcpay-upe-toggle/provider';
import WcPayUpeContext from '../../settings/wcpay-upe-toggle/context';

jest.mock( '../../data', () => ( {
	useEnabledPaymentMethodIds: jest.fn(),
	useGetAvailablePaymentMethodIds: jest.fn(),
	useCurrencies: jest.fn().mockReturnValue( { isLoading: true } ),
	useEnabledCurrencies: jest.fn().mockReturnValue( {} ),
} ) );

describe( 'PaymentMethods', () => {
	beforeEach( () => {
		useEnabledPaymentMethodIds.mockReturnValue( [ [], jest.fn() ] );
		useGetAvailablePaymentMethodIds.mockReturnValue( [
			'card',
			'bancontact',
			'giropay',
			'ideal',
			'p24',
			'sepa_debit',
			'sofort',
		] );
	} );

	test( 'does not render the "Add payment method" button when there is only one payment method available', () => {
		useGetAvailablePaymentMethodIds.mockReturnValue( [ 'card' ] );

		render(
			<WcPayUpeContextProvider defaultIsUpeEnabled={ true }>
				<PaymentMethods />
			</WcPayUpeContextProvider>
		);

		const addPaymentMethodButton = screen.queryByRole( 'button', {
			name: 'Add payment method',
		} );

		expect( addPaymentMethodButton ).not.toBeInTheDocument();
	} );

	test( 'renders the "Add payment method" button when there are at least 2 payment methods', () => {
		render(
			<WcPayUpeContextProvider defaultIsUpeEnabled={ true }>
				<PaymentMethods />
			</WcPayUpeContextProvider>
		);

		const addPaymentMethodButton = screen.queryByRole( 'button', {
			name: 'Add payment method',
		} );

		expect( addPaymentMethodButton ).toBeInTheDocument();
	} );

	test( '"Add payment method" button opens the payment methods selector modal', () => {
		render(
			<WcPayUpeContextProvider defaultIsUpeEnabled={ true }>
				<PaymentMethods />
			</WcPayUpeContextProvider>
		);

		const addPaymentMethodButton = screen.getByRole( 'button', {
			name: 'Add payment method',
		} );

		fireEvent.click( addPaymentMethodButton );

		expect(
			screen.queryByText( 'Add payment methods' )
		).toBeInTheDocument();
	} );

	test( 'payment methods are rendered in expected lists', () => {
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ 'card', 'sepa_debit' ],
		] );

		render(
			<WcPayUpeContextProvider defaultIsUpeEnabled={ true }>
				<PaymentMethods />
			</WcPayUpeContextProvider>
		);

		const cc = screen.getByText( 'Credit card / debit card' );
		const sepa = screen.getByText( 'SEPA Direct Debit' );
		[ cc, sepa ].forEach( ( method ) => {
			expect( method.closest( 'ul' ) ).toHaveClass(
				'payment-methods__enabled-methods'
			);
		} );

		const bancontact = screen.getByLabelText( 'Bancontact' );
		const giropay = screen.getByLabelText( 'giropay' );
		const sofort = screen.getByLabelText( 'Sofort' );
		const p24 = screen.getByLabelText( 'Przelewy24 (P24)' );
		const ideal = screen.getByLabelText( 'iDEAL' );

		[ bancontact, giropay, sofort, ideal, p24 ].forEach( ( method ) => {
			expect( method.closest( 'ul' ) ).toHaveClass(
				'payment-methods__available-methods'
			);
		} );
	} );

	test( 'enabled methods are rendered with "Delete" buttons', () => {
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ 'card', 'sepa_debit' ],
		] );

		render(
			<WcPayUpeContextProvider defaultIsUpeEnabled={ true }>
				<PaymentMethods />
			</WcPayUpeContextProvider>
		);

		expect(
			screen.queryByRole( 'button', {
				name: 'Delete Credit card / debit card from checkout',
			} )
		).toBeInTheDocument();
	} );

	test( 'when only one enabled method is rendered, the "Delete" button is not visible', () => {
		useEnabledPaymentMethodIds.mockReturnValue( [ [ 'card' ] ] );

		render(
			<WcPayUpeContextProvider defaultIsUpeEnabled={ true }>
				<PaymentMethods />
			</WcPayUpeContextProvider>
		);

		expect(
			screen.queryByRole( 'button', {
				name: 'Delete Credit card / debit card from checkout',
			} )
		).not.toBeInTheDocument();
	} );

	test( 'clicking delete updates enabled method IDs', () => {
		const updateEnabledMethodsMock = jest.fn( () => {} );
		useEnabledPaymentMethodIds.mockReturnValue( [
			[
				'card',
				'bancontact',
				'giropay',
				'ideal',
				'p24',
				'sepa_debit',
				'sofort',
			],
			updateEnabledMethodsMock,
		] );

		render(
			<WcPayUpeContextProvider defaultIsUpeEnabled={ true }>
				<PaymentMethods />
			</WcPayUpeContextProvider>
		);

		const ccDeleteButton = screen.getByRole( 'button', {
			name: 'Delete Credit card / debit card from checkout',
		} );
		user.click( ccDeleteButton );
		user.click(
			screen.getByRole( 'button', {
				name: 'Remove',
			} )
		);

		expect( updateEnabledMethodsMock ).toHaveBeenCalledWith( [
			'bancontact',
			'giropay',
			'ideal',
			'p24',
			'sepa_debit',
			'sofort',
		] );
	} );

	test( 'express payments rendered when UPE preview feture flag is enabled', () => {
		const featureFlagContext = {
			featureFlags: { upeSettingsPreview: true, upe: false },
		};
		const upeContext = {
			isUpeEnabled: false,
			setIsUpeEnabled: () => null,
			status: 'resolved',
		};

		render(
			<WCPaySettingsContext.Provider value={ featureFlagContext }>
				<WcPayUpeContext.Provider value={ upeContext }>
					<PaymentMethods />
				</WcPayUpeContext.Provider>
			</WCPaySettingsContext.Provider>
		);

		const enableWooCommercePaymentText = screen.getByText(
			'Enable the new WooCommerce Payments checkout experience'
		);

		expect( enableWooCommercePaymentText ).toBeInTheDocument();
	} );

	test.each( [
		[ false, false ],
		[ false, true ],
		[ true, true ],
	] )(
		'express payments should not rendered when UPE preview = %s and UPE = %s',
		( upeSettingsPreview, upe ) => {
			const featureFlagContext = {
				featureFlags: { upeSettingsPreview, upe },
			};
			const upeContext = {
				isUpeEnabled: upe,
				setIsUpeEnabled: () => null,
				status: 'resolved',
			};

			render(
				<WCPaySettingsContext.Provider value={ featureFlagContext }>
					<WcPayUpeContext.Provider value={ upeContext }>
						<PaymentMethods />
					</WcPayUpeContext.Provider>
				</WCPaySettingsContext.Provider>
			);

			const enableWooCommercePaymentText = screen.queryByText(
				'Enable the new WooCommerce Payments checkout experience'
			);

			expect( enableWooCommercePaymentText ).toBeNull();
		}
	);

	test( 'renders the feedback elements when UPE is enabled', () => {
		render(
			<WcPayUpeContextProvider defaultIsUpeEnabled={ true }>
				<PaymentMethods />
			</WcPayUpeContextProvider>
		);
		const disableUPEButton = screen.queryByRole( 'button', {
			name: 'Add feedback or disable',
		} );

		expect( disableUPEButton ).toBeInTheDocument();
		expect(
			screen.queryByText( 'Payment methods' ).parentElement
		).toHaveTextContent( 'Payment methods Early access' );
	} );

	test( 'Does not render the feedback elements when UPE is disabled', () => {
		render(
			<WcPayUpeContextProvider defaultIsUpeEnabled={ false }>
				<PaymentMethods />
			</WcPayUpeContextProvider>
		);

		const disableUPEButton = screen.queryByRole( 'button', {
			name: 'Add feedback or disable',
		} );

		expect( disableUPEButton ).not.toBeInTheDocument();
		expect(
			screen.queryByText( 'Payment methods' )
		).not.toBeInTheDocument();
	} );

	test( 'clicking "Enable in your store" in express payments enable UPE and redirects', async () => {
		Object.defineProperty( window, 'location', {
			value: {
				href: 'example.com/',
			},
		} );

		const setIsUpeEnabledMock = jest.fn().mockResolvedValue( true );
		const featureFlagContext = {
			featureFlags: { upeSettingsPreview: true, upe: false },
		};

		render(
			<WCPaySettingsContext.Provider value={ featureFlagContext }>
				<WcPayUpeContext.Provider
					value={ {
						setIsUpeEnabled: setIsUpeEnabledMock,
						status: 'resolved',
						isUpeEnabled: false,
					} }
				>
					<PaymentMethods />
				</WcPayUpeContext.Provider>
			</WCPaySettingsContext.Provider>
		);

		const enableInYourStoreButton = screen.queryByRole( 'button', {
			name: 'Enable in your store',
		} );

		expect( enableInYourStoreButton ).toBeInTheDocument();

		expect( setIsUpeEnabledMock ).not.toHaveBeenCalled();
		await user.click( enableInYourStoreButton );
		expect( setIsUpeEnabledMock ).toHaveBeenCalledWith( true );
		expect( window.location.href ).toEqual(
			'admin.php?page=wc-admin&task=woocommerce-payments--additional-payment-methods'
		);
	} );
} );
