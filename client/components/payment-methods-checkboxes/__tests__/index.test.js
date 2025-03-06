/** @format */
/**
 * External dependencies
 */
import React from 'react';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { act } from 'react-dom/test-utils';

/**
 * Internal dependencies
 */
import PaymentMethodsCheckboxes from '..';
import PaymentMethodsCheckbox from '../payment-method-checkbox';
import { upeCapabilityStatuses } from '../../../additional-methods-setup/constants';
import WCPaySettingsContext from 'wcpay/settings/wcpay-settings-context';

jest.mock( '@woocommerce/components', () => {
	return {
		Pill: ( { className, children } ) => (
			<span className={ className }>{ children }</span>
		),
	};
} );

const renderWithSettingsProvider = ( ui ) =>
	render(
		<WCPaySettingsContext.Provider value={ global.wcpaySettings }>
			{ ui }
		</WCPaySettingsContext.Provider>
	);

describe( 'PaymentMethodsCheckboxes', () => {
	beforeEach( () => {
		global.wcpaySettings = {
			accountFees: {},
		};
	} );

	it( 'triggers the onChange when clicking the checkbox', () => {
		const handleChange = jest.fn();

		const upeMethods = [
			[ 'bancontact', true ],
			[ 'eps', false ],
			[ 'ideal', false ],
			[ 'p24', false ],
			[ 'sepa_debit', false ],
		];

		renderWithSettingsProvider(
			<PaymentMethodsCheckboxes>
				{ upeMethods.map( ( [ name, checked ] ) => (
					<PaymentMethodsCheckbox
						key={ name }
						onChange={ handleChange }
						checked={ checked }
						name={ name }
						status={ upeCapabilityStatuses.ACTIVE }
						fees={ '' }
						required={ false }
						locked={ false }
					/>
				) ) }
			</PaymentMethodsCheckboxes>
		);

		const paymentMethods = screen.getAllByRole( 'listitem' );
		const bancontact = within( paymentMethods[ 0 ] );
		const eps = within( paymentMethods[ 1 ] );
		const ideal = within( paymentMethods[ 2 ] );
		const p24 = within( paymentMethods[ 3 ] );
		const sepa = within( paymentMethods[ 4 ] );

		expect( bancontact.getByRole( 'checkbox' ) ).toBeChecked();
		expect( eps.getByRole( 'checkbox' ) ).not.toBeChecked();
		expect( ideal.getByRole( 'checkbox' ) ).not.toBeChecked();
		expect( p24.getByRole( 'checkbox' ) ).not.toBeChecked();
		expect( sepa.getByRole( 'checkbox' ) ).not.toBeChecked();

		jest.useFakeTimers();
		act( () => {
			userEvent.click( bancontact.getByRole( 'checkbox' ) );
			userEvent.click( eps.getByRole( 'checkbox' ) );
			userEvent.click( ideal.getByRole( 'checkbox' ) );
			userEvent.click( p24.getByRole( 'checkbox' ) );
			userEvent.click( sepa.getByRole( 'checkbox' ) );
			jest.runOnlyPendingTimers();
		} );

		expect( handleChange ).toHaveBeenCalledTimes( upeMethods.length );

		expect( handleChange ).toHaveBeenNthCalledWith(
			1,
			'bancontact',
			false
		);
		expect( handleChange ).toHaveBeenNthCalledWith( 3, 'ideal', true );
		expect( handleChange ).toHaveBeenNthCalledWith( 4, 'p24', true );
		expect( handleChange ).toHaveBeenNthCalledWith( 5, 'sepa_debit', true );
		jest.useRealTimers();
	} );

	it( 'can click the checkbox on payment methods with pending statuses', () => {
		const handleChange = jest.fn();
		renderWithSettingsProvider(
			<PaymentMethodsCheckboxes>
				<PaymentMethodsCheckbox
					key={ 'ideal' }
					onChange={ handleChange }
					checked={ false }
					name={ 'ideal' }
					status={ upeCapabilityStatuses.PENDING_APPROVAL }
					fees={ '' }
					required={ false }
					locked={ false }
				/>
			</PaymentMethodsCheckboxes>
		);

		const idealCheckbox = screen.getByRole( 'checkbox', {
			name: 'iDEAL',
		} );
		expect( idealCheckbox ).not.toBeChecked();
		jest.useFakeTimers();
		act( () => {
			userEvent.click( idealCheckbox );
			jest.runOnlyPendingTimers();
		} );
		expect( handleChange ).toHaveBeenCalledTimes( 1 );
		expect( handleChange ).toHaveBeenNthCalledWith( 1, 'ideal', true );
		jest.useRealTimers();
	} );

	it( 'shows the required label on payment methods which are required', () => {
		const handleChange = jest.fn();
		const page = renderWithSettingsProvider(
			<PaymentMethodsCheckboxes>
				<PaymentMethodsCheckbox
					key={ 'card' }
					onChange={ handleChange }
					checked={ true }
					name={ 'card' }
					required={ true }
					status={ upeCapabilityStatuses.ACTIVE }
					fees={ '' }
					locked={ false }
				/>
			</PaymentMethodsCheckboxes>
		);

		expect( page.container ).toContainHTML(
			'<span class="payment-method-checkbox__required-label">Required</span>'
		);
	} );

	it( 'shows the disabled notice pill on payment methods with disabled statuses', () => {
		const handleChange = jest.fn();
		const page = renderWithSettingsProvider(
			<PaymentMethodsCheckboxes>
				<PaymentMethodsCheckbox
					key={ 'ideal' }
					onChange={ handleChange }
					checked={ true }
					name={ 'ideal' }
					status={ upeCapabilityStatuses.INACTIVE }
					fees={ '' }
					required={ false }
					locked={ false }
				/>
			</PaymentMethodsCheckboxes>
		);

		expect( page.container ).toContainHTML(
			'<span class="wcpay-pill payment-status-inactive">More information needed</span>'
		);
	} );

	it( 'can not click the payment methods checkbox that are locked', () => {
		const handleChange = jest.fn();
		renderWithSettingsProvider(
			<PaymentMethodsCheckboxes>
				<PaymentMethodsCheckbox
					key={ 'card' }
					onChange={ handleChange }
					checked={ false }
					name={ 'card' }
					locked={ true }
					status={ upeCapabilityStatuses.ACTIVE }
					fees={ '' }
					required={ false }
				/>
			</PaymentMethodsCheckboxes>
		);
		const cardCheckbox = screen.getByRole( 'checkbox', {
			name: 'Credit / Debit Cards',
		} );
		expect( cardCheckbox ).not.toBeChecked();
		userEvent.click( cardCheckbox );
		expect( handleChange ).toHaveBeenCalledTimes( 0 ); // Because the input is disabled.
		expect( cardCheckbox ).not.toBeChecked();
	} );

	it( 'can not click the payment methods checkbox with disabled statuses', () => {
		const handleChange = jest.fn();
		renderWithSettingsProvider(
			<PaymentMethodsCheckboxes>
				<PaymentMethodsCheckbox
					key={ 'ideal' }
					onChange={ handleChange }
					checked={ false }
					name={ 'ideal' }
					status={ upeCapabilityStatuses.INACTIVE }
					fees={ '' }
					required={ false }
					locked={ false }
				/>
			</PaymentMethodsCheckboxes>
		);
		const idealCheckbox = screen.getByRole( 'checkbox', {
			name: 'iDEAL',
		} );
		expect( idealCheckbox ).not.toBeChecked();
		userEvent.click( idealCheckbox );
		expect( handleChange ).toHaveBeenCalledTimes( 0 ); // Because the input is disabled.
		expect( idealCheckbox ).not.toBeChecked();
	} );

	it( "doesn't show the disabled notice pill on payment methods with active and unrequested statuses", () => {
		const handleChange = jest.fn();
		renderWithSettingsProvider(
			<PaymentMethodsCheckboxes>
				<PaymentMethodsCheckbox
					key={ 'ideal' }
					onChange={ handleChange }
					checked={ true }
					name={ 'ideal' }
					status={ upeCapabilityStatuses.ACTIVE }
					fees={ '' }
					required={ false }
					locked={ false }
				/>
				<PaymentMethodsCheckbox
					key={ 'p24' }
					onChange={ handleChange }
					checked={ true }
					name={ 'p24' }
					status={ upeCapabilityStatuses.UNREQUESTED }
					fees={ '' }
					required={ false }
					locked={ false }
				/>
			</PaymentMethodsCheckboxes>
		);

		// Test that the Contact support pill content isn't shown
		expect(
			screen.queryByText( 'Contact WooCommerce Support' )
		).not.toBeInTheDocument();
	} );
} );
