/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';
import { userEvent as user } from 'jest-utils/user-event-timers';
import { select } from '@wordpress/data';

/**
 * Internal dependencies
 */
import PaymentMethodsSection from '..';
import {
	useEnabledPaymentMethodIds,
	useGetAvailablePaymentMethodIds,
	useGetPaymentMethodStatuses,
	useManualCapture,
	useSelectedPaymentMethod,
	useUnselectedPaymentMethod,
	useGetDuplicatedPaymentMethodIds,
} from 'wcpay/data';
import { upeCapabilityStatuses } from 'wcpay/settings/constants';
import DuplicatedPaymentMethodsContext from 'wcpay/settings/settings-manager/duplicated-payment-methods-context';

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
	useGetPaymentMethodStatuses: jest.fn().mockReturnValue( {} ),
	useManualCapture: jest.fn(),
	useSelectedPaymentMethod: jest.fn(),
	useUnselectedPaymentMethod: jest.fn(),
	useGetDuplicatedPaymentMethodIds: jest.fn(),
	useSettings: jest.fn().mockReturnValue( { isLoading: false } ),
} ) );

jest.mock( 'multi-currency/interface/data', () => ( {
	useCurrencies: jest.fn().mockReturnValue( { isLoading: true } ),
	useEnabledCurrencies: jest.fn().mockReturnValue( {} ),
} ) );

jest.mock( 'multi-currency/interface/data', () => ( {
	useCurrencies: jest.fn().mockReturnValue( { isLoading: true } ),
	useEnabledCurrencies: jest.fn().mockReturnValue( {} ),
} ) );

jest.mock( '@wordpress/data', () => ( {
	useDispatch: jest.fn().mockReturnValue( {} ),
	select: jest.fn(),
} ) );

describe( 'PaymentMethodsSection', () => {
	beforeEach( () => {
		useEnabledPaymentMethodIds.mockReturnValue( [ [], jest.fn() ] );
		useSelectedPaymentMethod.mockReturnValue( [ [], jest.fn() ] );
		useUnselectedPaymentMethod.mockReturnValue( [ [], jest.fn() ] );
		useGetAvailablePaymentMethodIds.mockReturnValue( [
			'card',
			'au_becs_debit',
			'bancontact',
			'eps',
			'ideal',
			'p24',
			'sepa_debit',
		] );
		useGetPaymentMethodStatuses.mockReturnValue( {
			card_payments: upeCapabilityStatuses.ACTIVE,
			au_becs_debit: upeCapabilityStatuses.ACTIVE,
			bancontact_payments: upeCapabilityStatuses.ACTIVE,
			eps_payments: upeCapabilityStatuses.ACTIVE,
			ideal_payments: upeCapabilityStatuses.ACTIVE,
			p24_payments: upeCapabilityStatuses.ACTIVE,
			sepa_debit_payments: upeCapabilityStatuses.ACTIVE,
		} );
		useManualCapture.mockReturnValue( [ false, jest.fn() ] );
		global.wcpaySettings = {
			isMultiCurrencyEnabled: true,
			storeCurrency: 'USD',
			accountEmail: 'admin@example.com',
		};
		select.mockImplementation( () => ( {
			getSettings: jest.fn().mockReturnValue( {
				account_country: 'US',
			} ),
		} ) );
		useGetDuplicatedPaymentMethodIds.mockReturnValue( [] );
	} );

	it( 'renders payment methods', () => {
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ 'card', 'sepa_debit' ],
		] );

		render( <PaymentMethodsSection /> );

		const card = screen.getByRole( 'checkbox', {
			name: 'Credit / Debit Cards',
		} );
		const becs = screen.getByRole( 'checkbox', {
			name: 'BECS Direct Debit',
		} );
		const sepa = screen.getByRole( 'checkbox', {
			name: 'SEPA Direct Debit',
		} );
		const klarna = screen.queryByRole( 'checkbox', { name: 'Klarna' } );

		// BNPLs should not be present
		expect( klarna ).not.toBeInTheDocument();
		expect( card ).toBeChecked();
		expect( sepa ).toBeChecked();
		expect( becs ).not.toBeChecked();
	} );

	it( 'renders notice pills on inactive and pending payment methods', () => {
		useGetAvailablePaymentMethodIds.mockReturnValue( [
			'card',
			'au_becs_debit',
			'bancontact',
			'eps',
			'ideal',
			'p24',
			'sepa_debit',
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
				status: upeCapabilityStatuses.PENDING_VERIFICATION,
				requirements: [ 'individual.identification_number' ],
			},
		} );

		render( <PaymentMethodsSection /> );

		expect(
			screen.queryAllByText( /pending/i, {
				ignore: '.a11y-speak-region,.components-flex-item',
			} ).length
		).toEqual( 4 );
		expect(
			screen.queryAllByText( /more information needed/i, {
				ignore: '.a11y-speak-region,.components-flex-item',
			} ).length
		).toEqual( 6 );
	} );

	it( 'renders the activation modal when requirements exist for the payment method', async () => {
		useEnabledPaymentMethodIds.mockReturnValue( [ [], jest.fn() ] );
		useGetAvailablePaymentMethodIds.mockReturnValue( [ 'bancontact' ] );
		useGetPaymentMethodStatuses.mockReturnValue( {
			bancontact_payments: {
				status: upeCapabilityStatuses.UNREQUESTED,
				requirements: [ 'company.tax_id' ],
			},
		} );

		render( <PaymentMethodsSection /> );

		expect(
			screen.queryByRole( 'checkbox', { name: /Bancontact/ } )
		).toBeInTheDocument();

		const bancontactCheckbox = screen.queryByRole( 'checkbox', {
			name: /Bancontact/,
		} );

		expect( bancontactCheckbox ).not.toBeChecked();

		jest.useFakeTimers();

		// Enabling a PM with requirements should show the activation modal
		await user.click( bancontactCheckbox );
		jest.runOnlyPendingTimers();

		expect(
			screen.queryByText(
				/You need to provide more information to enable Bancontact on your checkout/
			)
		).toBeInTheDocument();

		jest.useRealTimers();
	} );

	it( 'renders the delete modal on an already active payment method', async () => {
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

		render( <PaymentMethodsSection /> );

		expect( screen.queryByLabelText( 'Bancontact' ) ).toBeInTheDocument();

		const bancontactCheckbox = screen.getByLabelText( 'Bancontact' );

		expect( bancontactCheckbox ).toBeChecked();

		jest.useFakeTimers();

		// Disabling an already active PM should show the delete modal
		await user.click( bancontactCheckbox );
		jest.runOnlyPendingTimers();

		expect(
			screen.queryByText(
				/Your customers will no longer be able to pay using Bancontact\./
			)
		).toBeInTheDocument();

		jest.useRealTimers();
	} );

	it( "renders the setup tooltip correctly when multi currency is disabled and store currency doesn't support the LPM", () => {
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

		render( <PaymentMethodsSection /> );

		// Checkbox should be rendered.
		expect( screen.queryByLabelText( 'Bancontact' ) ).toBeInTheDocument();
		expect( screen.queryByLabelText( 'Bancontact' ) ).toBeEnabled();

		expect(
			screen.queryByText( /Bancontact requires the EUR currency\./, {
				ignore: '.a11y-speak-region',
			} )
		).toBeInTheDocument();
	} );

	it( 'should not render duplicate notices when they have been dismissed', () => {
		useGetAvailablePaymentMethodIds.mockReturnValue( [ 'card' ] );
		useGetDuplicatedPaymentMethodIds.mockReturnValue( [ 'card' ] );

		render(
			<DuplicatedPaymentMethodsContext.Provider
				value={ {
					duplicates: [ 'card' ],
					dismissedDuplicateNotices: [ 'card' ],
					setDismissedDuplicateNotices: jest.fn(),
				} }
			>
				<PaymentMethodsSection />
			</DuplicatedPaymentMethodsContext.Provider>
		);

		expect(
			screen.queryByText(
				'This payment method is enabled by other extensions. Review extensions to improve the shopper experience.'
			)
		).not.toBeInTheDocument();
	} );

	it( 'should render duplicate notice when they have not been dismissed', () => {
		useGetAvailablePaymentMethodIds.mockReturnValue( [ 'card' ] );
		useGetDuplicatedPaymentMethodIds.mockReturnValue( [ 'card' ] );

		render(
			<DuplicatedPaymentMethodsContext.Provider
				value={ {
					duplicates: { card: '' },
					dismissedDuplicateNotices: {},
					setDismissedDuplicateNotices: jest.fn(),
				} }
			>
				<PaymentMethodsSection />
			</DuplicatedPaymentMethodsContext.Provider>
		);

		expect(
			screen.queryByText(
				'This payment method is enabled by other extensions. Review extensions to improve the shopper experience.'
			)
		).toBeInTheDocument();
	} );
} );
