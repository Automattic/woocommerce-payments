/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */

import WcPayUpeContextProvider from '../provider';
import WcPayUpeContext from '../context';
import { useEnabledPaymentMethodIds } from '../../../data';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );
jest.mock( '../../../data', () => ( {
	useEnabledPaymentMethodIds: jest.fn(),
} ) );
jest.mock( '@wordpress/data', () => ( {
	useDispatch: jest
		.fn()
		.mockReturnValue( { updateAvailablePaymentMethodIds: jest.fn() } ),
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
			upeType: '',
		} );
		expect( apiFetch ).not.toHaveBeenCalled();
	} );

	it( 'should render the initial state given a default value for isUpeEnabled', () => {
		const childrenMock = jest.fn().mockReturnValue( null );
		render(
			<WcPayUpeContextProvider>
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
} );
