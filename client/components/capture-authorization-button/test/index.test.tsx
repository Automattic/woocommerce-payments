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
	paymentIntentId: string,
	buttonIsPrimary: boolean,
	buttonIsSmall: boolean
) {
	return render(
		<CaptureAuthorizationButton
			orderId={ orderId }
			paymentIntentId={ paymentIntentId }
			buttonIsPrimary={ buttonIsPrimary }
			buttonIsSmall={ buttonIsSmall }
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
			'paymentIntentId',
			false,
			true
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
			'paymentIntentId',
			false,
			true
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

		renderCaptureAuthorizationButton( 42, 'paymentIntentId', true, false );

		expect( doCaptureAuthorizationMock ).not.toHaveBeenCalled();

		await user.click( screen.getByRole( 'button' ) );

		expect( doCaptureAuthorizationMock ).toHaveBeenCalledTimes( 1 );
	} );
} );
