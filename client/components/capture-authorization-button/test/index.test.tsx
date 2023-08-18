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
	capturableAmount: number,
	buttonIsPrimary: boolean,
	buttonIsSmall: boolean
) {
	return render(
		<CaptureAuthorizationButton
			orderId={ orderId }
			capturableAmount={ capturableAmount }
			paymentIntentId={ paymentIntentId }
			buttonIsPrimary={ buttonIsPrimary }
			buttonIsSmall={ buttonIsSmall }
		/>
	);
}
const defaultUseAuthorization = {
	doCaptureAuthorization: jest.fn(),
	doCancelAuthorization: jest.fn(),
	isLoading: false,
	isRequesting: false,
	authorization: {} as Authorization,
};

describe( 'CaptureAuthorizationButton', () => {
	afterEach( () => {
		jest.clearAllMocks();
	} );

	test( 'should render normal status', () => {
		mockUseAuthorization.mockReturnValue( defaultUseAuthorization );

		const { container } = renderCaptureAuthorizationButton(
			42,
			'paymentIntentId',
			1000,
			false,
			true
		);

		expect( container ).toMatchSnapshot();
	} );

	test( 'should transition to busy state when clicked', async () => {
		const doCaptureAuthorizationMock = jest.fn();

		mockUseAuthorization.mockReturnValue( {
			...defaultUseAuthorization,
			doCaptureAuthorization: doCaptureAuthorizationMock,
		} );

		const { container, rerender } = renderCaptureAuthorizationButton(
			42,
			'paymentIntentId',
			1000,
			false,
			true
		);

		expect( doCaptureAuthorizationMock.mock.calls.length ).toBe( 0 );

		await user.click( screen.getByRole( 'button' ) );

		mockUseAuthorization.mockReturnValue( {
			...defaultUseAuthorization,
			isLoading: true,
			doCaptureAuthorization: doCaptureAuthorizationMock,
		} );

		expect( doCaptureAuthorizationMock.mock.calls.length ).toBe( 1 );

		rerender(
			<CaptureAuthorizationButton
				orderId={ 42 }
				paymentIntentId={ 'paymentIntentId' }
				capturableAmount={ 1000 }
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

		const { container } = renderCaptureAuthorizationButton(
			42,
			'paymentIntentId',
			1000,
			false,
			true
		);

		expect( screen.getByRole( 'button' ) ).toBeDisabled();
		expect( container ).toMatchSnapshot();
	} );
} );
