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
import CaptureAuthorizationButton from '../';
import { useAuthorization } from 'wcpay/data';
import { Authorization } from 'wcpay/types/authorizations';

jest.mock( 'wcpay/data', () => ( {
	useAuthorization: jest.fn(),
} ) );

const mockUseAuthorization = useAuthorization as jest.MockedFunction<
	typeof useAuthorization
>;

function renderCaptureAuthorizationButton(
	orderId: number,
	paymentIntentId: string
) {
	return render(
		<CaptureAuthorizationButton
			orderId={ orderId }
			paymentIntentId={ paymentIntentId }
		/>
	);
}

describe( 'CaptureAuthorizationButton', () => {
	afterEach( () => {
		jest.clearAllMocks();
	} );

	test( 'should render normal status', () => {
		mockUseAuthorization.mockReturnValue( {
			doCaptureAuthorization: jest.fn(),
			isLoading: false,
			authorization: {} as Authorization,
		} );
		const { container } = renderCaptureAuthorizationButton(
			42,
			'paymentIntentId'
		);

		expect( container ).toMatchSnapshot();
	} );

	test( 'should render busy status', () => {
		mockUseAuthorization.mockReturnValue( {
			doCaptureAuthorization: jest.fn(),
			isLoading: true,
			authorization: {} as Authorization,
		} );
		const { container } = renderCaptureAuthorizationButton(
			42,
			'paymentIntentId'
		);

		expect( container ).toMatchSnapshot();
	} );

	test( 'should transition to busy state when clicked', async () => {
		const doCaptureAuthorizationMock = jest.fn();
		const mockAuthorization = {} as Authorization;

		mockUseAuthorization.mockReturnValue( {
			doCaptureAuthorization: doCaptureAuthorizationMock,
			isLoading: false,
			authorization: mockAuthorization,
		} );

		const { rerender } = renderCaptureAuthorizationButton(
			42,
			'paymentIntentId'
		);

		expect( doCaptureAuthorizationMock.mock.calls.length ).toBe( 0 );

		await user.click( screen.getByRole( 'button' ) );

		mockUseAuthorization.mockReturnValue( {
			doCaptureAuthorization: doCaptureAuthorizationMock,
			isLoading: true,
			authorization: mockAuthorization,
		} );

		expect( doCaptureAuthorizationMock.mock.calls.length ).toBe( 1 );

		rerender(
			<CaptureAuthorizationButton
				orderId={ 42 }
				paymentIntentId={ 'paymentIntentId' }
			/>
		);

		expect( screen.getByRole( 'button' ) ).toHaveClass( 'is-busy' );
		expect( screen.getByRole( 'button' ) ).toBeDisabled();
	} );
} );
