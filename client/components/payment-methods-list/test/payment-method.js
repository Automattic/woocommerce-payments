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
	let checked = false;
	const handleOnCheckClickMock = jest.fn( () => {
		checked = true;
	} );
	const handleOnUnCheckClickMock = jest.fn( () => {
		checked = false;
	} );
	const getComponent = () => {
		return (
			<PaymentMethod
				label="Foo"
				id="foo"
				checked={ checked }
				onCheckClick={ handleOnCheckClickMock }
				onUncheckClick={ handleOnUnCheckClickMock }
				description="Bar"
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
			jest.runAllTimers();
			// Since we are using a variable instead of a state, we need to re-render the component on each variable change.
			component.rerender( getComponent() );
		} );

		expect( handleOnCheckClickMock ).toHaveBeenCalledTimes( 1 );
		expect( handleOnCheckClickMock ).toHaveBeenCalledWith( 'foo' );

		act( () => {
			user.click( screen.getByLabelText( 'Foo' ) );
			jest.runAllTimers();
			component.rerender( getComponent() );
		} );

		expect( handleOnUnCheckClickMock ).toHaveBeenCalledTimes( 1 );
		expect( handleOnUnCheckClickMock ).toHaveBeenCalledWith( 'foo' );
		jest.useRealTimers();
	} );
} );
