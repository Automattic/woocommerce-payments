/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';
import user from '@testing-library/user-event';
import { act } from 'react-dom/test-utils';

/**
 * Internal dependencies
 */
import PaymentMethod from '../payment-method';
import DuplicatedPaymentMethodsContext from 'wcpay/settings/settings-manager/duplicated-payment-methods-context';

describe( 'PaymentMethod', () => {
	let checked = false;
	const handleOnCheckClickMock = jest.fn( () => {
		checked = true;
	} );
	const handleOnUnCheckClickMock = jest.fn( () => {
		checked = false;
	} );
	const setDismissedDuplicateNoticesMock = jest.fn();

	// Clear the mocks (including the mock call count) after each test.
	afterEach( () => {
		jest.clearAllMocks();
	} );

	const getComponent = ( required = false ) => {
		return (
			<PaymentMethod
				label="Foo"
				id="foo"
				checked={ checked }
				onCheckClick={ handleOnCheckClickMock }
				onUncheckClick={ handleOnUnCheckClickMock }
				description="Bar"
				Icon={ (): null => null }
				status={ '' }
				isAllowingManualCapture={ false }
				required={ required }
				locked={ false }
				isPoEnabled={ false }
				isPoComplete={ false }
			/>
		);
	};

	test( 'renders label and description', () => {
		render( getComponent() );

		expect( screen.queryByLabelText( 'Foo' ) ).toBeInTheDocument();
		expect( screen.queryByText( 'Bar' ) ).toBeInTheDocument();
	} );

	test( 'clicking a checkbox calls onCheckClick and onUnCheckClick', () => {
		const component = render( getComponent() );

		act( () => {
			user.click( screen.getByLabelText( 'Foo' ) );
			jest.runOnlyPendingTimers();
			// Since we are using a variable instead of a state, we need to re-render the component on each variable change.
			component.rerender( getComponent() );
		} );

		expect( handleOnCheckClickMock ).toHaveBeenCalledTimes( 1 );
		expect( handleOnCheckClickMock ).toHaveBeenCalledWith( 'foo' );

		act( () => {
			user.click( screen.getByLabelText( 'Foo' ) );
			jest.runOnlyPendingTimers();
			component.rerender( getComponent() );
		} );

		expect( handleOnUnCheckClickMock ).toHaveBeenCalledTimes( 1 );
		expect( handleOnUnCheckClickMock ).toHaveBeenCalledWith( 'foo' );
		jest.useRealTimers();
	} );

	it( 'shows the required label on payment methods which are required', () => {
		const component = render( getComponent( true ) );
		expect( component.container ).toContainHTML(
			'<span class="payment-method__required-label">(Required)</span>'
		);
	} );

	const getLockedComponent = () => {
		return (
			<PaymentMethod
				label="Locked"
				id="locked"
				checked={ checked }
				onCheckClick={ handleOnCheckClickMock }
				onUncheckClick={ handleOnUnCheckClickMock }
				description="Locked payment method"
				locked={ true }
				Icon={ (): null => null }
				status={ '' }
				isAllowingManualCapture={ false }
				required={ false }
				isPoEnabled={ false }
				isPoComplete={ false }
			/>
		);
	};

	test( 'clicking a locked checkbox does not call onCheckClick or onUnCheckClick', () => {
		const component = render( getLockedComponent() );

		jest.useFakeTimers();
		act( () => {
			user.click( screen.getByLabelText( 'Locked' ) );
			jest.runOnlyPendingTimers();
			// Since we are using a variable instead of a state, we need to re-render the component on each variable change.
			component.rerender( getLockedComponent() );
		} );

		expect( handleOnCheckClickMock ).not.toHaveBeenCalled();

		act( () => {
			user.click( screen.getByLabelText( 'Locked' ) );
			jest.runOnlyPendingTimers();
			component.rerender( getLockedComponent() );
		} );

		expect( handleOnUnCheckClickMock ).not.toHaveBeenCalled();
		jest.useRealTimers();
	} );

	const getDuplicateComponent = ( id: string ) => (
		<PaymentMethod
			label="Test Method"
			id={ id }
			checked={ false }
			onCheckClick={ handleOnCheckClickMock }
			onUncheckClick={ handleOnUnCheckClickMock }
			description="Test Description"
			Icon={ () => null }
			status=""
			isAllowingManualCapture={ false }
			required={ false }
			locked={ false }
			isPoEnabled={ false }
			isPoComplete={ false }
		/>
	);

	test( 'does not render DuplicateNotice if payment method is not in duplicates', () => {
		render(
			<DuplicatedPaymentMethodsContext.Provider
				value={ {
					duplicates: [ 'ideal' ],
					dismissedDuplicateNotices: [],
					setDismissedDuplicateNotices: setDismissedDuplicateNoticesMock,
				} }
			>
				{ getDuplicateComponent( 'card' ) }
			</DuplicatedPaymentMethodsContext.Provider>
		);

		expect(
			screen.queryByText(
				'This payment method is enabled by other extensions. Review extensions to improve the shopper experience.'
			)
		).not.toBeInTheDocument();
	} );

	test( 'render DuplicateNotice if payment method is in duplicates', () => {
		render(
			<DuplicatedPaymentMethodsContext.Provider
				value={ {
					duplicates: [ 'card' ],
					dismissedDuplicateNotices: [],
					setDismissedDuplicateNotices: setDismissedDuplicateNoticesMock,
				} }
			>
				{ getDuplicateComponent( 'card' ) }
			</DuplicatedPaymentMethodsContext.Provider>
		);

		expect(
			screen.queryByText(
				'This payment method is enabled by other extensions. Review extensions to improve the shopper experience.'
			)
		).toBeInTheDocument();
	} );
} );
