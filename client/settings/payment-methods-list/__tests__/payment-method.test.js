/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import user from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import PaymentMethod from '../payment-method';
import DuplicatedPaymentMethodsContext from '../../settings-manager/duplicated-payment-methods-context';
import {
	useEnabledPaymentMethodIds,
	useGetPaymentMethodStatuses,
	useManualCapture,
} from 'wcpay/data';

jest.mock( 'wcpay/data', () => ( {
	useEnabledPaymentMethodIds: jest.fn(),
	useGetPaymentMethodStatuses: jest.fn(),
	useManualCapture: jest.fn(),
} ) );

describe( 'PaymentMethod', () => {
	beforeEach( () => {
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ 'card', 'bancontact' ],
		] );
		useGetPaymentMethodStatuses.mockReturnValue( {} );
		useManualCapture.mockReturnValue( [ false ] );
	} );

	// Clear the mocks (including the mock call count) after each test.
	afterEach( () => {
		jest.clearAllMocks();
	} );

	it( 'renders label and description', () => {
		render(
			<PaymentMethod
				id="bancontact"
				description="Bar"
				label="Bancontact"
			/>
		);

		expect( screen.queryByLabelText( 'Bancontact' ) ).toBeInTheDocument();
		expect( screen.queryByText( 'Bar' ) ).toBeInTheDocument();
	} );

	it( 'calls onCheckClick when clicking its checkbox', async () => {
		const handleCheckClickMock = jest.fn();
		const handleUnCheckClickMock = jest.fn();
		useEnabledPaymentMethodIds.mockReturnValue( [ [ 'card' ] ] );

		render(
			<PaymentMethod
				id="bancontact"
				description="Bar"
				label="Bancontact"
				onCheckClick={ handleCheckClickMock }
				onUncheckClick={ handleUnCheckClickMock }
			/>
		);

		await user.click( screen.getByLabelText( 'Bancontact' ) );

		await waitFor( () =>
			expect( handleCheckClickMock ).toHaveBeenCalledTimes( 1 )
		);
		expect( handleCheckClickMock ).toHaveBeenCalledWith( 'bancontact' );
		expect( handleUnCheckClickMock ).not.toHaveBeenCalled();
	} );

	test( 'calls onUnCheckClick when clicking its checkbox', async () => {
		const handleCheckClickMock = jest.fn();
		const handleUnCheckClickMock = jest.fn();

		render(
			<PaymentMethod
				id="bancontact"
				description="Bar"
				label="Bancontact"
				onCheckClick={ handleCheckClickMock }
				onUncheckClick={ handleUnCheckClickMock }
			/>
		);

		await user.click( screen.getByLabelText( 'Bancontact' ) );

		await waitFor( () =>
			expect( handleUnCheckClickMock ).toHaveBeenCalledTimes( 1 )
		);
		expect( handleUnCheckClickMock ).toHaveBeenCalledWith( 'bancontact' );
		expect( handleCheckClickMock ).not.toHaveBeenCalled();
	} );

	it( 'shows the required label on payment methods which are required', () => {
		render(
			<PaymentMethod
				id="card"
				label="Card"
				description="Bar"
				required={ true }
				locked={ false }
			/>
		);

		expect( screen.getAllByText( '(Required)' ) ).toHaveLength( 2 );
	} );

	it( 'does not call onCheckClick or onUnCheckClick when clicking a locked checkbox', async () => {
		const handleCheckClickMock = jest.fn();
		const handleUnCheckClickMock = jest.fn();
		render(
			<PaymentMethod
				label="Bancontact"
				id="bancontact"
				onCheckClick={ handleCheckClickMock }
				onUncheckClick={ handleUnCheckClickMock }
				description="Locked payment method"
				locked={ true }
			/>
		);

		await user.click( screen.getByLabelText( 'Bancontact' ) );

		expect( handleCheckClickMock ).not.toHaveBeenCalled();
		expect( handleUnCheckClickMock ).not.toHaveBeenCalled();
	} );

	it( 'does not render DuplicateNotice if the payment method is not in duplicated', () => {
		render(
			<DuplicatedPaymentMethodsContext.Provider
				value={ {
					duplicates: { ideal: [ 'woocommerce_payments' ] },
					dismissedDuplicateNotices: {},
					setDismissedDuplicateNotices: () => null,
				} }
			>
				<PaymentMethod
					label="Test Method"
					id="card"
					description="Test Description"
				/>
			</DuplicatedPaymentMethodsContext.Provider>
		);

		expect(
			screen.queryByText(
				'This payment method is enabled by other extensions. Review extensions to improve the shopper experience.'
			)
		).not.toBeInTheDocument();
	} );

	it( 'render DuplicateNotice if payment method is in duplicates', () => {
		render(
			<DuplicatedPaymentMethodsContext.Provider
				value={ {
					duplicates: { card: [ 'woocommerce_payments' ] },
					dismissedDuplicateNotices: {},
					setDismissedDuplicateNotices: () => null,
				} }
			>
				<PaymentMethod
					label="Test Method"
					id="card"
					description="Test Description"
				/>
			</DuplicatedPaymentMethodsContext.Provider>
		);

		expect(
			screen.queryByText(
				'This payment method is enabled by other extensions. Review extensions to improve the shopper experience.'
			)
		).toBeInTheDocument();
	} );
} );
