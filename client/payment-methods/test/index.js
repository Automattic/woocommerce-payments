/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { act, fireEvent, render, screen } from '@testing-library/react';
import user from '@testing-library/user-event';
import { select } from '@wordpress/data';

/**
 * Internal dependencies
 */
import PaymentMethods from '..';
import {
	useAccountDomesticCurrency,
	useEnabledPaymentMethodIds,
	useGetAvailablePaymentMethodIds,
	useGetPaymentMethodStatuses,
	useManualCapture,
	useSelectedPaymentMethod,
	useUnselectedPaymentMethod,
} from 'wcpay/data';
import { upeCapabilityStatuses } from 'wcpay/additional-methods-setup/constants';

jest.mock( '@woocommerce/components', () => {
	return {
		Pill: ( { className, children } ) => (
			<span className={ className }>{ children }</span>
		),
	};
} );

jest.mock( '../../data', () => ( {
	useEnabledPaymentMethodIds: jest.fn(),
	useGetAvailablePaymentMethodIds: jest.fn(),
	useCurrencies: jest.fn().mockReturnValue( { isLoading: true } ),
	useEnabledCurrencies: jest.fn().mockReturnValue( {} ),
	useGetPaymentMethodStatuses: jest.fn().mockReturnValue( {} ),
	useManualCapture: jest.fn(),
	useSelectedPaymentMethod: jest.fn(),
	useUnselectedPaymentMethod: jest.fn(),
	useAccountDomesticCurrency: jest.fn(),
} ) );

jest.mock( '@wordpress/data', () => ( {
	useDispatch: jest
		.fn()
		.mockReturnValue( { updateAvailablePaymentMethodIds: jest.fn() } ),
	select: jest.fn(),
} ) );

describe( 'PaymentMethods', () => {
	beforeEach( () => {
		useEnabledPaymentMethodIds.mockReturnValue( [ [], jest.fn() ] );
		useSelectedPaymentMethod.mockReturnValue( [ null, jest.fn() ] );
		useUnselectedPaymentMethod.mockReturnValue( [ null, jest.fn() ] );
		useGetAvailablePaymentMethodIds.mockReturnValue( [
			'card',
			'au_becs_debit',
			'bancontact',
			'eps',
			'giropay',
			'ideal',
			'p24',
			'sepa_debit',
			'sofort',
		] );
		useGetPaymentMethodStatuses.mockReturnValue( {
			card_payments: upeCapabilityStatuses.ACTIVE,
			au_becs_debit: upeCapabilityStatuses.ACTIVE,
			bancontact_payments: upeCapabilityStatuses.ACTIVE,
			eps_payments: upeCapabilityStatuses.ACTIVE,
			giropay_payments: upeCapabilityStatuses.ACTIVE,
			ideal_payments: upeCapabilityStatuses.ACTIVE,
			p24_payments: upeCapabilityStatuses.ACTIVE,
			sepa_debit_payments: upeCapabilityStatuses.ACTIVE,
			sofort_payments: upeCapabilityStatuses.ACTIVE,
		} );
		useManualCapture.mockReturnValue( [ false, jest.fn() ] );
		global.wcpaySettings = {
			isMultiCurrencyEnabled: true,
			storeCurrency: 'USD',
			accountEmail: 'admin@example.com',
			capabilityRequestNotices: {},
		};
		useAccountDomesticCurrency.mockReturnValue( 'usd' );
		select.mockImplementation( () => ( {
			getSettings: jest.fn().mockReturnValue( {
				account_country: 'US',
			} ),
		} ) );
	} );

	test( 'payment methods are rendered correctly', () => {
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ 'card', 'sepa_debit' ],
		] );

		render( <PaymentMethods /> );

		const cc = screen.getByRole( 'checkbox', {
			name: 'Credit / Debit card',
		} );
		const becs = screen.getByRole( 'checkbox', {
			name: 'BECS Direct Debit',
		} );
		const sepa = screen.getByRole( 'checkbox', {
			name: 'SEPA Direct Debit',
		} );
		const bancontact = screen.getByRole( 'checkbox', {
			name: 'Bancontact',
		} );
		const eps = screen.getByRole( 'checkbox', { name: 'EPS' } );
		const giropay = screen.getByRole( 'checkbox', { name: 'giropay' } );
		const sofort = screen.getByRole( 'checkbox', { name: 'Sofort' } );
		const p24 = screen.getByRole( 'checkbox', {
			name: 'Przelewy24 (P24)',
		} );
		const ideal = screen.getByRole( 'checkbox', { name: 'iDEAL' } );

		const allMethods = [
			becs,
			bancontact,
			eps,
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
		useSelectedPaymentMethod.mockReturnValue( [
			[
				'Credit / Debit card',
				'BECS Direct Debit',
				'Bancontact',
				'EPS',
				'giropay',
				'iDEAL',
				'Przelewy24 (P24)',
				'SEPA Direct Debit',
				'Sofort',
			],
			updateEnabledMethodsMock,
		] );
		useGetPaymentMethodStatuses.mockReturnValue( {
			card_payments: {
				status: upeCapabilityStatuses.ACTIVE,
				requirements: [],
			},
			au_becs_debit: {
				status: upeCapabilityStatuses.INACTIVE,
				requirements: [],
			},
			bancontact_payments: {
				status: upeCapabilityStatuses.INACTIVE,
				requirements: [],
			},
			eps_payments: {
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

		render( <PaymentMethods /> );

		expect( screen.queryAllByText( /Pending /i ).length ).toEqual( 4 );
	} );

	test( 'affirm afterpay pms renders correctly', () => {
		useGetAvailablePaymentMethodIds.mockReturnValue( [
			'card',
			'au_becs_debit',
			'affirm',
			'afterpay_clearpay',
			'bancontact',
			'eps',
			'giropay',
			'ideal',
			'p24',
			'sepa_debit',
			'sofort',
		] );

		render( <PaymentMethods /> );

		const affirm = screen.getByRole( 'checkbox', { name: 'Affirm' } );
		const afterpay = screen.getByRole( 'checkbox', {
			name: 'Afterpay',
		} );

		expect( affirm ).toBeInTheDocument();
		expect( afterpay ).toBeInTheDocument();
	} );

	test( 'affirm and afterpay appear checked when enabled', () => {
		useGetAvailablePaymentMethodIds.mockReturnValue( [
			'card',
			'au_becs_debit',
			'affirm',
			'afterpay_clearpay',
			'bancontact',
			'eps',
			'giropay',
			'ideal',
			'p24',
			'sepa_debit',
			'sofort',
		] );
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ 'card', 'affirm', 'afterpay_clearpay' ],
		] );

		useGetPaymentMethodStatuses.mockReturnValue( {
			card_payments: {
				status: upeCapabilityStatuses.ACTIVE,
				requirements: [],
			},
			affirm_payments: {
				status: upeCapabilityStatuses.ACTIVE,
				requirements: [],
			},
			afterpay_clearpay_payments: {
				status: upeCapabilityStatuses.ACTIVE,
				requirements: [],
			},
		} );

		const renderPaymentElements = () => {
			render( <PaymentMethods /> );
		};

		renderPaymentElements();

		const affirm = screen.getByRole( 'checkbox', {
			name: 'Affirm',
		} );
		const afterpay = screen.getByRole( 'checkbox', {
			name: 'Afterpay',
		} );

		expect( affirm ).toBeChecked();
		expect( afterpay ).toBeChecked();
	} );

	test( 'renders the payment methods component', () => {
		render( <PaymentMethods /> );

		expect( screen.queryByText( 'Payment methods' ) ).toBeInTheDocument();
		expect(
			screen.queryByText( 'Payment methods' ).parentElement
		).toHaveTextContent( 'Payment methods' );
	} );

	it( 'should render the activation modal when requirements exist for the payment method', () => {
		useEnabledPaymentMethodIds.mockReturnValue( [ [], jest.fn() ] );
		useGetAvailablePaymentMethodIds.mockReturnValue( [ 'bancontact' ] );
		useGetPaymentMethodStatuses.mockReturnValue( {
			bancontact_payments: {
				status: upeCapabilityStatuses.UNREQUESTED,
				requirements: [ 'company.tax_id' ],
			},
		} );

		render( <PaymentMethods /> );

		expect(
			screen.queryByRole( 'checkbox', { name: /Bancontact/ } )
		).toBeInTheDocument();

		const bancontactCheckbox = screen.queryByRole( 'checkbox', {
			name: /Bancontact/,
		} );

		expect( bancontactCheckbox ).not.toBeChecked();

		jest.useFakeTimers();

		act( () => {
			// Enabling a PM with requirements should show the activation modal
			user.click( bancontactCheckbox );
			jest.runOnlyPendingTimers();
		} );

		expect(
			screen.queryByText(
				/You need to provide more information to enable Bancontact on your checkout/
			)
		).toBeInTheDocument();

		jest.useRealTimers();
	} );

	it( 'should render the delete modal on an already active payment method', () => {
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ 'bancontact' ],
			jest.fn(),
		] );
		useGetAvailablePaymentMethodIds.mockReturnValue( [ 'bancontact' ] );
		useGetPaymentMethodStatuses.mockReturnValue( {
			bancontact_payments: {
				status: upeCapabilityStatuses.ACTIVE,
				requirements: [],
			},
		} );

		render( <PaymentMethods /> );

		expect( screen.queryByLabelText( 'Bancontact' ) ).toBeInTheDocument();

		const bancontactCheckbox = screen.getByLabelText( 'Bancontact' );

		expect( bancontactCheckbox ).toBeChecked();

		jest.useFakeTimers();

		act( () => {
			// Disabling an already active PM should show the delete modal
			user.click( bancontactCheckbox );
			jest.runOnlyPendingTimers();
		} );

		expect(
			screen.queryByText(
				/Your customers will no longer be able to pay using Bancontact\./
			)
		).toBeInTheDocument();

		jest.useRealTimers();
	} );

	it( "should render the setup tooltip correctly when multi currency is disabled and store currency doesn't support the LPM", () => {
		global.wcpaySettings.isMultiCurrencyEnabled = false;
		global.wcpaySettings.storeCurrency = 'TRY';
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ 'bancontact' ],
			jest.fn(),
		] );
		useGetAvailablePaymentMethodIds.mockReturnValue( [ 'bancontact' ] );
		useGetPaymentMethodStatuses.mockReturnValue( {
			bancontact_payments: {
				status: upeCapabilityStatuses.ACTIVE,
				requirements: [],
			},
		} );

		const { container } = render( <PaymentMethods /> );

		// Checkbox shouldn't be rendered.
		expect(
			screen.queryByLabelText( 'Bancontact' )
		).not.toBeInTheDocument();

		const svgIcon = container.querySelectorAll(
			'.gridicons-notice-outline'
		)[ 0 ];

		expect( svgIcon ).toBeInTheDocument();

		jest.useFakeTimers();

		act( () => {
			fireEvent.mouseOver( svgIcon, {
				view: window,
				bubbles: true,
				cancelable: true,
			} );
			jest.runAllTimers();
		} );

		expect(
			screen.queryByText( /Bancontact requires the EUR currency\./ )
		).toBeInTheDocument();
		jest.useRealTimers();
	} );
} );
