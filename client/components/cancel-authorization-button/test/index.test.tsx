/** @format */
/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';
import user from '@testing-library/user-event';
import React from 'react';

/**
 * Internal dependencies
 */
import CancelAuthorizationButton from '../';
import { useAuthorization } from 'wcpay/data';
import { Authorization } from 'wcpay/types/authorizations';

jest.mock( 'wcpay/data', () => ( {
	useAuthorization: jest.fn(),
} ) );

const mockUseAuthorization = useAuthorization as jest.MockedFunction<
	typeof useAuthorization
>;

function renderCancelAuthorizationButton(
	orderId: number,
	paymentIntentId: string,
	isDestructive = true,
	isSmall = false
) {
	return render(
		<CancelAuthorizationButton
			orderId={ orderId }
			paymentIntentId={ paymentIntentId }
			isDestructive={ isDestructive }
			isSmall={ isSmall }
		/>
	);
}
const defaultUseAuthorization = {
	doCancelAuthorization: jest.fn(),
	doCaptureAuthorization: jest.fn(),
	isLoading: false,
	isRequesting: false,
	authorization: {} as Authorization,
};

describe( 'CancelAuthorizationButton', () => {
	afterEach( () => {
		jest.clearAllMocks();
	} );

	test( 'should render normal status', () => {
		mockUseAuthorization.mockReturnValue( defaultUseAuthorization );

		const { container } = renderCancelAuthorizationButton(
			42,
			'paymentIntentId'
		);

		expect( container ).toMatchSnapshot();
	} );

	test( 'should transition to busy state when clicked', async () => {
		const doCancelAuthorizationMock = jest.fn();

		mockUseAuthorization.mockReturnValue( {
			...defaultUseAuthorization,
			doCancelAuthorization: doCancelAuthorizationMock,
		} );

		const { container, rerender } = renderCancelAuthorizationButton(
			42,
			'paymentIntentId'
		);

		expect( doCancelAuthorizationMock.mock.calls.length ).toBe( 0 );

		await user.click( screen.getByRole( 'button' ) );

		mockUseAuthorization.mockReturnValue( {
			...defaultUseAuthorization,
			isLoading: true,
			doCancelAuthorization: doCancelAuthorizationMock,
		} );

		expect( doCancelAuthorizationMock.mock.calls.length ).toBe( 1 );

		rerender(
			<CancelAuthorizationButton
				orderId={ 42 }
				paymentIntentId={ 'paymentIntentId' }
			/>
		);

		expect( screen.getByRole( 'button' ) ).toHaveClass( 'is-busy' );
		expect( screen.getByRole( 'button' ) ).toBeDisabled();
		expect( container ).toMatchSnapshot();
	} );

	test( 'should be disabled when requesting is true', () => {
		mockUseAuthorization.mockReturnValue( {
			...defaultUseAuthorization,
			isRequesting: true,
		} );

		const { container } = renderCancelAuthorizationButton(
			42,
			'paymentIntentId',
			false,
			true
		);

		expect( screen.getByRole( 'button' ) ).toBeDisabled();
		expect( container ).toMatchSnapshot();
	} );
} );
