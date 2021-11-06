/** @format */

/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';
import user from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import PaymentMethod from '../payment-method';

describe( 'PaymentMethod', () => {
	test( 'renders label and description', () => {
		render( <PaymentMethod label="Foo" description="Bar" /> );

		expect( screen.queryByText( 'Foo' ) ).toBeInTheDocument();
		expect( screen.queryByText( 'Bar' ) ).toBeInTheDocument();
	} );

	test( 'clicking an unchecked checkbox calls onCheckClick() and onUnCheckClick', () => {
		const handleOnCheckClickMock = jest.fn();
		const handleOnUnCheckClickMock = jest.fn();
		render(
			<PaymentMethod
				label="Foo"
				id="foo"
				onCheckClick={ handleOnCheckClickMock }
				onUnCheckClick={ handleOnUnCheckClickMock }
			/>
		);

		user.click(
			screen.getByRole( 'checkbox', {
				name: 'Foo',
			} )
		);
		expect( handleOnCheckClickMock ).toHaveBeenCalledTimes( 1 );
		expect( handleOnCheckClickMock ).toHaveBeenCalledWith( 'foo' );

		user.click(
			screen.getByRole( 'checkbox', {
				name: 'Foo',
			} )
		);

		expect( handleOnUnCheckClickMock ).toHaveBeenCalledTimes( 1 );
		expect( handleOnUnCheckClickMock ).toHaveBeenCalledWith( 'foo' );
	} );
} );
