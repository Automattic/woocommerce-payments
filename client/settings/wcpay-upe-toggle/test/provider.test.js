/**
 * External dependencies
 */
import React, { useEffect } from 'react';
import { render, waitFor } from '@testing-library/react';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */

import WcPayUpeContextProvider from '../provider';
import WcPayUpeContext from '../context';
import useIsUpeEnabled from '../hook';
import { useEnabledPaymentMethodIds } from '../../../data';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );
jest.mock( '../../../data', () => ( {
	useEnabledPaymentMethodIds: jest.fn(),
} ) );

describe( 'WcPayUpeContextProvider', () => {
	beforeEach( () => {
		useEnabledPaymentMethodIds.mockReturnValue( [ [], () => null ] );
	} );

	afterEach( () => {
		jest.clearAllMocks();

		apiFetch.mockResolvedValue( true );
	} );

	afterAll( () => {
		jest.restoreAllMocks();
	} );

	it( 'should render the initial state', () => {
		const childrenMock = jest.fn().mockReturnValue( null );
		render(
			<WcPayUpeContextProvider>
				<WcPayUpeContext.Consumer>
					{ childrenMock }
				</WcPayUpeContext.Consumer>
			</WcPayUpeContextProvider>
		);

		expect( childrenMock ).toHaveBeenCalledWith( {
			isUpeEnabled: false,
			setIsUpeEnabled: expect.any( Function ),
			status: 'resolved',
		} );
		expect( apiFetch ).not.toHaveBeenCalled();
	} );

	it( 'should render the initial state given a default value for isUpeEnabled', () => {
		const childrenMock = jest.fn().mockReturnValue( null );
		render(
			<WcPayUpeContextProvider defaultIsUpeEnabled={ true }>
				<WcPayUpeContext.Consumer>
					{ childrenMock }
				</WcPayUpeContext.Consumer>
			</WcPayUpeContextProvider>
		);

		expect( childrenMock ).toHaveBeenCalledWith(
			expect.objectContaining( {
				isUpeEnabled: true,
			} )
		);
		expect( apiFetch ).not.toHaveBeenCalled();
	} );

	it( 'should call the API and resolve when setIsUpeEnabled has been called', async () => {
		const childrenMock = jest.fn().mockReturnValue( null );
		const setEnabledPaymentMethodIds = jest.fn();
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ 'card', 'giropay' ],
			setEnabledPaymentMethodIds,
		] );

		const UpdateUpeEnabledFlagMock = () => {
			const [ , setIsUpeEnabled ] = useIsUpeEnabled();
			useEffect( () => {
				setIsUpeEnabled( true );
			}, [ setIsUpeEnabled ] );

			return null;
		};

		render(
			<WcPayUpeContextProvider>
				<UpdateUpeEnabledFlagMock />
				<WcPayUpeContext.Consumer>
					{ childrenMock }
				</WcPayUpeContext.Consumer>
			</WcPayUpeContextProvider>
		);

		expect( childrenMock ).toHaveBeenCalledWith( {
			isUpeEnabled: false,
			setIsUpeEnabled: expect.any( Function ),
			status: 'resolved',
		} );

		expect( childrenMock ).toHaveBeenCalledWith( {
			isUpeEnabled: false,
			setIsUpeEnabled: expect.any( Function ),
			status: 'pending',
		} );

		await waitFor( () =>
			expect( apiFetch ).toHaveBeenCalledWith( {
				path: '/wc/v3/payments/upe_flag_toggle',
				method: 'POST',
				// eslint-disable-next-line camelcase
				data: { is_upe_enabled: true },
			} )
		);

		await waitFor( () => expect( apiFetch ).toHaveReturned() );

		expect( childrenMock ).toHaveBeenCalledWith( {
			isUpeEnabled: true,
			setIsUpeEnabled: expect.any( Function ),
			status: 'resolved',
		} );
		expect( setEnabledPaymentMethodIds ).not.toHaveBeenCalled();
	} );

	it( 'should disable non-UPE payment methods when the flag is disabled', async () => {
		const childrenMock = jest.fn().mockReturnValue( null );
		const setEnabledPaymentMethodIds = jest.fn();
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ 'card', 'giropay' ],
			setEnabledPaymentMethodIds,
		] );

		const UpdateUpeDisabledFlagMock = () => {
			const [ , setIsUpeEnabled ] = useIsUpeEnabled();
			useEffect( () => {
				setIsUpeEnabled( false );
			}, [ setIsUpeEnabled ] );

			return null;
		};

		render(
			<WcPayUpeContextProvider defaultIsUpeEnabled>
				<UpdateUpeDisabledFlagMock />
				<WcPayUpeContext.Consumer>
					{ childrenMock }
				</WcPayUpeContext.Consumer>
			</WcPayUpeContextProvider>
		);

		expect( childrenMock ).toHaveBeenCalledWith( {
			isUpeEnabled: true,
			setIsUpeEnabled: expect.any( Function ),
			status: 'resolved',
		} );

		expect( childrenMock ).toHaveBeenCalledWith( {
			isUpeEnabled: true,
			setIsUpeEnabled: expect.any( Function ),
			status: 'pending',
		} );

		await waitFor( () =>
			expect( apiFetch ).toHaveBeenCalledWith( {
				path: '/wc/v3/payments/upe_flag_toggle',
				method: 'POST',
				// eslint-disable-next-line camelcase
				data: { is_upe_enabled: false },
			} )
		);

		await waitFor( () => expect( apiFetch ).toHaveReturned() );

		expect( childrenMock ).toHaveBeenCalledWith( {
			isUpeEnabled: false,
			setIsUpeEnabled: expect.any( Function ),
			status: 'resolved',
		} );
		expect( setEnabledPaymentMethodIds ).toHaveBeenCalledWith( [ 'card' ] );
	} );
} );
