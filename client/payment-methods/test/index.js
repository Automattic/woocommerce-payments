/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';
import user from '@testing-library/user-event';
import WCPaySettingsContext from '../../settings/wcpay-settings-context';

/**
 * Internal dependencies
 */
import PaymentMethods from '..';
import {
	useEnabledPaymentMethodIds,
	useGetAvailablePaymentMethodIds,
	useGetPaymentMethodStatuses,
} from 'wcpay/data';
import WcPayUpeContextProvider from '../../settings/wcpay-upe-toggle/provider';
import WcPayUpeContext from '../../settings/wcpay-upe-toggle/context';
import { upeCapabilityStatuses } from 'wcpay/additional-methods-setup/constants';

jest.mock( '../../data', () => ( {
	useEnabledPaymentMethodIds: jest.fn(),
	useGetAvailablePaymentMethodIds: jest.fn(),
	useCurrencies: jest.fn().mockReturnValue( { isLoading: true } ),
	useEnabledCurrencies: jest.fn().mockReturnValue( {} ),
	useGetPaymentMethodStatuses: jest.fn().mockReturnValue( {} ),
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
		useGetPaymentMethodStatuses.mockReturnValue( {
			card_payments: upeCapabilityStatuses.ACTIVE,
			bancontact_payments: upeCapabilityStatuses.ACTIVE,
			giropay_payments: upeCapabilityStatuses.ACTIVE,
			ideal_payments: upeCapabilityStatuses.ACTIVE,
			p24_payments: upeCapabilityStatuses.ACTIVE,
			sepa_debit_payments: upeCapabilityStatuses.ACTIVE,
			sofort_payments: upeCapabilityStatuses.ACTIVE,
		} );
		global.wcSettings = {
			currentUserData: {
				email: 'admin@example.com',
			},
		};
	} );

	test( 'payment methods are rendered correctly', () => {
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ 'card', 'sepa_debit' ],
		] );

		render(
			<WcPayUpeContextProvider defaultIsUpeEnabled={ true }>
				<PaymentMethods />
			</WcPayUpeContextProvider>
		);

		const cc = screen.getByRole( 'checkbox', { name: 'card' } );
		const sepa = screen.getByRole( 'checkbox', { name: 'sepa_debit' } );
		const bancontact = screen.getByRole( 'checkbox', {
			name: 'bancontact',
		} );
		const giropay = screen.getByRole( 'checkbox', { name: 'giropay' } );
		const sofort = screen.getByRole( 'checkbox', { name: 'sofort' } );
		const p24 = screen.getByRole( 'checkbox', {
			name: 'p24',
		} );
		const ideal = screen.getByRole( 'checkbox', { name: 'ideal' } );

		const allMethods = [
			bancontact,
			giropay,
			sofort,
			ideal,
			p24,
			cc,
			sepa,
		];
		const enabledMethods = [ cc, sepa ];

		enabledMethods.forEach( ( method ) => {
			expect( method ).toBeChecked();
		} );
		allMethods.forEach( ( method ) => {
			expect( method.closest( 'ul' ) ).toHaveClass(
				'payment-methods__available-methods'
			);
		} );
		allMethods
			.filter( ( method ) => ! enabledMethods.includes( method ) )
			.forEach( ( method ) => {
				expect( method ).not.toBeChecked();
			} );
	} );

	test( 'inactive and pending payment methods have notice pills', () => {
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
		useGetPaymentMethodStatuses.mockReturnValue( {
			card_payments: {
				status: upeCapabilityStatuses.ACTIVE,
				requirements: [],
			},
			bancontact_payments: {
				status: upeCapabilityStatuses.INACTIVE,
				requirements: [],
			},
			giropay_payments: {
				status: upeCapabilityStatuses.PENDING_APPROVAL,
				requirements: [],
			},
			ideal_payments: {
				status: upeCapabilityStatuses.INACTIVE,
				requirements: [],
			},
			p24_payments: {
				status: upeCapabilityStatuses.INACTIVE,
				requirements: [],
			},
			sepa_debit_payments: {
				status: upeCapabilityStatuses.ACTIVE,
				requirements: [],
			},
			sofort_payments: {
				status: upeCapabilityStatuses.PENDING_VERIFICATION,
				requirements: [ 'individual.identification_number' ],
			},
		} );

		render(
			<WcPayUpeContextProvider defaultIsUpeEnabled={ true }>
				<PaymentMethods />
			</WcPayUpeContextProvider>
		);

		expect( screen.queryAllByText( /Pending /i ).length ).toEqual( 2 );

		expect(
			screen.queryAllByText( /Contact WooCommerce Support/i ).length
		).toEqual( 3 );
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
