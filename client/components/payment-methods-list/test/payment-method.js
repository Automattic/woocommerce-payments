/** @format */

/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';
import user from '@testing-library/user-event';
import { act } from 'react-dom/test-utils';

/**
 * Internal dependencies
 */
import PaymentMethod from '../payment-method';

describe( 'PaymentMethod', () => {
	test( 'renders label and description', () => {
		render( <PaymentMethod id="foo" label="Foo" description="Bar" /> );

		expect( screen.queryByLabelText( 'Foo' ) ).toBeInTheDocument();
		expect( screen.queryByText( 'Bar' ) ).toBeInTheDocument();
	} );

	test( 'clicking a checkbox calls onCheckClick and onUnCheckClick', () => {
		const handleOnCheckClickMock = jest.fn();
		const handleOnUnCheckClickMock = jest.fn();
		render(
			<PaymentMethod
				label="Foo"
				id="foo"
				onCheckClick={ handleOnCheckClickMock }
				onUncheckClick={ handleOnUnCheckClickMock }
			/>
		);
		jest.useFakeTimers();
		act( () => {
			user.click( screen.getByLabelText( 'Foo' ) );
			jest.runAllTimers();
		} );

		expect( handleOnCheckClickMock ).toHaveBeenCalledTimes( 1 );
		expect( handleOnCheckClickMock ).toHaveBeenCalledWith( 'foo' );

		act( () => {
			user.click( screen.getByLabelText( 'Foo' ) );
			jest.runAllTimers();
		} );

		expect( handleOnUnCheckClickMock ).toHaveBeenCalledTimes( 1 );
		expect( handleOnUnCheckClickMock ).toHaveBeenCalledWith( 'foo' );
		jest.useRealTimers();
	} );
} );
