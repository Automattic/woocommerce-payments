/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';
import apiFetch from '@wordpress/api-fetch';
/**
 * Internal dependencies
 */
import Loading from '../loading';

// Mock Api Fetch module and function
jest.mock( '@wordpress/api-fetch', () => jest.fn() );
const mockApiFetch = apiFetch as jest.MockedFunction< typeof apiFetch >;

// Mock wcpaySettings
declare const global: {
	wcpaySettings: {
		connectUrl: string;
	};
};

// Mock data, setData from OnboardingContext
let data = {};
const setData = jest.fn();

jest.mock( '../../context', () => ( {
	useOnboardingContext: jest.fn( () => ( {
		data,
		setData,
	} ) ),
} ) );

describe( 'Loading', () => {
	const originalWindowLocation = window.location;

	beforeEach( () => {
		// Prevent window.location.href redirect
		Object.defineProperty( window, 'location', {
			configurable: true,
			enumerable: true,
			value: new URL( window.location.href ),
		} );
		global.wcpaySettings = {
			connectUrl: 'http://wcpay-connect-url',
		};
	} );

	afterEach( () => {
		// Roll back window.location.href behavior after test
		Object.defineProperty( window, 'location', {
			configurable: true,
			enumerable: true,
			value: originalWindowLocation,
		} );
	} );

	it( 'renders loading screen and sends request to server', async () => {
		data = {
			country: 'US',
			business_type: 'individual',
			mcc: 'computers_peripherals_and_software',
			annual_revenue: 'less_than_250k',
			go_live_timeframe: 'within_1month',
		};

		mockApiFetch.mockResolvedValueOnce( {
			result: 'eligible',
			data: [],
		} );

		render( <Loading /> );

		await expect( mockApiFetch ).toHaveBeenCalledWith( {
			data: {
				business: {
					country: 'US',
					type: 'individual',
					mcc: 'computers_peripherals_and_software',
					annual_revenue: 'less_than_250k',
					go_live_timeframe: 'within_1month',
				},
			},
			method: 'POST',
			path: `/wc/v3/payments/onboarding/router/po_eligible`,
		} );
	} );
} );
