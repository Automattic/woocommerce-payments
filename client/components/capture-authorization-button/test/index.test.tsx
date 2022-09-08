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
	authorizationId: string,
	orderId: number,
	paymentIntentId: string
) {
	return render(
		<CaptureAuthorizationButton
			id={ authorizationId }
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
			'authorizationId',
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
			'authorizationId',
			42,
			'paymentIntentId'
		);

		expect( container ).toMatchSnapshot();
	} );

	test( 'should call capture function when clicked', async () => {
		const doCaptureAuthorizationMock = jest.fn();
		mockUseAuthorization.mockReturnValue( {
			doCaptureAuthorization: doCaptureAuthorizationMock,
			isLoading: false,
			authorization: {} as Authorization,
		} );

		renderCaptureAuthorizationButton(
			'authorizationId',
			42,
			'paymentIntentId'
		);

		expect( doCaptureAuthorizationMock.mock.calls.length ).toBe( 0 );

		await user.click( screen.getByRole( 'button' ) );

		expect( doCaptureAuthorizationMock.mock.calls.length ).toBe( 1 );
	} );
} );
