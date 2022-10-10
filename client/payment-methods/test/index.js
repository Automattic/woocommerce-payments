/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { act, render, screen } from '@testing-library/react';
import user from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import PaymentMethods from '..';
import {
	useEnabledPaymentMethodIds,
	useGetAvailablePaymentMethodIds,
	useGetPaymentMethodStatuses,
	useManualCapture,
} from 'wcpay/data';
import { upeCapabilityStatuses } from 'wcpay/additional-methods-setup/constants';

jest.mock( '../../data', () => ( {
	useEnabledPaymentMethodIds: jest.fn(),
	useGetAvailablePaymentMethodIds: jest.fn(),
	useCurrencies: jest.fn().mockReturnValue( { isLoading: true } ),
	useEnabledCurrencies: jest.fn().mockReturnValue( {} ),
	useGetPaymentMethodStatuses: jest.fn().mockReturnValue( {} ),
	useManualCapture: jest.fn(),
} ) );

jest.mock( '@wordpress/data', () => ( {
	useDispatch: jest
		.fn()
		.mockReturnValue( { updateAvailablePaymentMethodIds: jest.fn() } ),
} ) );

describe( 'PaymentMethods', () => {
	beforeEach( () => {
		useEnabledPaymentMethodIds.mockReturnValue( [ [], jest.fn() ] );
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
			accountEmail: 'admin@example.com',
		};
	} );

	test( 'payment methods are rendered correctly', () => {
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ 'card', 'sepa_debit' ],
		] );

		render( <PaymentMethods /> );

		const cc = screen.getByRole( 'checkbox', {
			name: 'Credit card / debit card',
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
		useEnabledPaymentMethodIds.mockReturnValue( [
			[
				'Credit card / debit card',
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

		expect( screen.queryAllByText( /Pending /i ).length ).toEqual( 2 );

		expect(
			screen.queryAllByText( /Contact WooCommerce Support/i ).length
		).toEqual( 4 );
	} );

	test( 'renders the feedback elements when UPE is enabled', () => {
		render( <PaymentMethods /> );
		const feedbackButton = screen.queryByRole( 'button', {
			name: 'Add feedback',
		} );

		expect( feedbackButton ).toBeInTheDocument();
	} );

	it( 'should render the activation modal when requirements exist for the payment method', () => {
		useEnabledPaymentMethodIds.mockReturnValue( [ [], jest.fn() ] );
		useGetAvailablePaymentMethodIds.mockReturnValue( [ 'card' ] );
		useGetPaymentMethodStatuses.mockReturnValue( {
			card_payments: {
				status: upeCapabilityStatuses.UNREQUESTED,
				requirements: [ 'company.tax_id' ],
			},
		} );

		render( <PaymentMethods /> );

		expect(
			screen.queryByLabelText( 'Credit card / debit card' )
		).toBeInTheDocument();

		const cardCheckbox = screen.getByLabelText(
			'Credit card / debit card'
		);

		expect( cardCheckbox ).not.toBeChecked();

		jest.useFakeTimers();

		act( () => {
			// Enabling a PM with requirements should show the activation modal
			user.click( cardCheckbox );
			jest.runAllTimers();
		} );

		expect(
			screen.queryByText(
				/You need to provide more information to enable Credit card \/ debit card on your checkout/
			)
		).toBeInTheDocument();

		jest.useRealTimers();
	} );

	it( 'should render the delete modal on an already active payment method', () => {
		useEnabledPaymentMethodIds.mockReturnValue( [ [ 'card' ], jest.fn() ] );
		useGetAvailablePaymentMethodIds.mockReturnValue( [ 'card' ] );
		useGetPaymentMethodStatuses.mockReturnValue( {
			card_payments: {
				status: upeCapabilityStatuses.ACTIVE,
				requirements: [],
			},
		} );

		render( <PaymentMethods /> );

		expect(
			screen.queryByLabelText( 'Credit card / debit card' )
		).toBeInTheDocument();

		const cardCheckbox = screen.getByLabelText(
			'Credit card / debit card'
		);

		expect( cardCheckbox ).toBeChecked();

		jest.useFakeTimers();

		act( () => {
			// Disabling an already active PM should show the delete modal
			user.click( cardCheckbox );
			jest.runAllTimers();
		} );

		expect(
			screen.queryByText(
				/Your customers will no longer be able to pay using Credit card \/ debit card\./
			)
		).toBeInTheDocument();

		jest.useRealTimers();
	} );
} );
