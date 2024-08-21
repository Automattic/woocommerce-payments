/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { act, render, screen } from '@testing-library/react';
import user from '@testing-library/user-event';
import { select } from '@wordpress/data';

/**
 * Internal dependencies
 */
import BuyNowPayLaterSection from '..';
import {
	useEnabledPaymentMethodIds,
	useGetAvailablePaymentMethodIds,
	useGetPaymentMethodStatuses,
	useManualCapture,
	useSelectedPaymentMethod,
	useUnselectedPaymentMethod,
	useGetDuplicatedPaymentMethodIds,
} from 'wcpay/data';
import { upeCapabilityStatuses } from 'wcpay/additional-methods-setup/constants';

jest.mock( '@woocommerce/components', () => {
	return {
		Pill: ( { className, children } ) => (
			<span className={ className }>{ children }</span>
		),
	};
} );

jest.mock( 'wcpay/data', () => ( {
	useEnabledPaymentMethodIds: jest.fn(),
	useGetAvailablePaymentMethodIds: jest.fn(),
	useCurrencies: jest.fn().mockReturnValue( { isLoading: true } ),
	useEnabledCurrencies: jest.fn().mockReturnValue( {} ),
	useGetPaymentMethodStatuses: jest.fn().mockReturnValue( {} ),
	useManualCapture: jest.fn(),
	useSelectedPaymentMethod: jest.fn(),
	useUnselectedPaymentMethod: jest.fn(),
	useGetDuplicatedPaymentMethodIds: jest.fn(),
	useSettings: jest.fn().mockReturnValue( { isLoading: false } ),
} ) );

jest.mock( '@wordpress/data', () => ( {
	useDispatch: jest
		.fn()
		.mockReturnValue( { updateAvailablePaymentMethodIds: jest.fn() } ),
	select: jest.fn(),
} ) );

describe( 'BuyNowPayLaterSection', () => {
	beforeEach( () => {
		useEnabledPaymentMethodIds.mockReturnValue( [ [], jest.fn() ] );
		useSelectedPaymentMethod.mockReturnValue( [ null, jest.fn() ] );
		useUnselectedPaymentMethod.mockReturnValue( [ null, jest.fn() ] );
		useGetAvailablePaymentMethodIds.mockReturnValue( [
			'card',
			'bancontact',
			'klarna',
			'afterpay_clearpay',
			'affirm',
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
		select.mockImplementation( () => ( {
			getSettings: jest.fn().mockReturnValue( {
				account_country: 'US',
			} ),
		} ) );
		useGetDuplicatedPaymentMethodIds.mockReturnValue( [] );
	} );

	test( 'payment methods are rendered correctly', () => {
		useEnabledPaymentMethodIds.mockReturnValue( [ [ 'card', 'affirm' ] ] );

		useGetPaymentMethodStatuses.mockReturnValue( {
			card_payments: {
				status: upeCapabilityStatuses.ACTIVE,
				requirements: [],
			},
			klarna_payments: {
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

		render( <BuyNowPayLaterSection /> );

		expect(
			screen.queryByRole( 'checkbox', {
				name: 'Credit / Debit card',
			} )
		).not.toBeInTheDocument();
		expect(
			screen.getByRole( 'checkbox', {
				name: 'Klarna',
			} )
		).not.toBeChecked();
		expect(
			screen.getByRole( 'checkbox', {
				name: 'Affirm',
			} )
		).toBeChecked();
	} );

	it( 'should render the activation modal when requirements exist for the payment method', () => {
		useEnabledPaymentMethodIds.mockReturnValue( [ [], jest.fn() ] );
		useGetAvailablePaymentMethodIds.mockReturnValue( [ 'affirm' ] );
		useGetPaymentMethodStatuses.mockReturnValue( {
			affirm_payments: {
				status: upeCapabilityStatuses.UNREQUESTED,
				requirements: [ 'company.tax_id' ],
			},
		} );

		render( <BuyNowPayLaterSection /> );

		const affirmCheckbox = screen.queryByRole( 'checkbox', {
			name: /Affirm/,
		} );

		expect( affirmCheckbox ).toBeInTheDocument();
		expect( affirmCheckbox ).not.toBeChecked();

		jest.useFakeTimers();

		act( () => {
			// Enabling a PM with requirements should show the activation modal
			user.click( affirmCheckbox );
			jest.runOnlyPendingTimers();
		} );

		expect(
			screen.queryByText(
				/You need to provide more information to enable Affirm on your checkout/
			)
		).toBeInTheDocument();

		jest.useRealTimers();
	} );

	it( 'should render the delete modal on an already active payment method', () => {
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ 'affirm' ],
			jest.fn(),
		] );
		useGetAvailablePaymentMethodIds.mockReturnValue( [ 'affirm' ] );
		useGetPaymentMethodStatuses.mockReturnValue( {
			affirm_payments: {
				status: upeCapabilityStatuses.ACTIVE,
				requirements: [],
			},
		} );

		render( <BuyNowPayLaterSection /> );

		const affirmCheckbox = screen.queryByRole( 'checkbox', {
			name: /Affirm/,
		} );

		expect( affirmCheckbox ).toBeInTheDocument();
		expect( affirmCheckbox ).toBeChecked();

		jest.useFakeTimers();

		act( () => {
			// Disabling an already active PM should show the delete modal
			user.click( affirmCheckbox );
			jest.runOnlyPendingTimers();
		} );

		expect(
			screen.queryByText(
				/Your customers will no longer be able to pay using Affirm\./
			)
		).toBeInTheDocument();

		jest.useRealTimers();
	} );
} );
