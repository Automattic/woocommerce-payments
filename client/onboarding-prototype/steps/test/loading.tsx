/**
 * External dependencies
 */
import React from 'react';
import { render, waitFor } from '@testing-library/react';
import apiFetch from '@wordpress/api-fetch';
import { mocked } from 'ts-jest/utils';
/**
 * Internal dependencies
 */
import Loading from '../loading';

// Mock Api Fetch module and function
jest.mock( '@wordpress/api-fetch', () => jest.fn() );

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

	it( 'renders loading screen and sends request to server in case of po eligible', async () => {
		data = {
			country: 'US',
			business_type: 'individual',
			mcc: 'computers_peripherals_and_software',
			annual_revenue: 'less_than_250k',
			go_live_timeframe: 'within_1month',
		};

		mocked( apiFetch ).mockResolvedValueOnce( {
			result: 'eligible',
			data: [],
		} );

		render( <Loading /> );

		await waitFor( () => {
			expect( apiFetch ).toHaveBeenCalledWith( {
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

		expect( window.location.href ).toContain( 'progressive' );
		expect( window.location.href ).toContain( 'country' );
		expect( window.location.href ).toContain( 'mcc' );
		expect( window.location.href ).toContain( 'annual_revenue' );
		expect( window.location.href ).toContain( 'business_type' );
		expect( window.location.href ).toContain( 'go_live_timeframe' );
	} );

	it( 'renders loading screen and sends request to server in case of non-po', async () => {
		data = {
			country: 'GB',
			business_type: 'individual',
			mcc: 'computers_peripherals_and_software',
			annual_revenue: 'less_than_250k',
			go_live_timeframe: 'within_1month',
		};

		mocked( apiFetch ).mockResolvedValueOnce( {
			result: 'not_eligible',
			data: [],
		} );

		render( <Loading /> );

		await waitFor( () => {
			expect( apiFetch ).toHaveBeenCalledWith( {
				data: {
					business: {
						country: 'GB',
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

		expect( window.location.href ).toContain( 'prefill' );
		// TODO GH-5476 check for other values
	} );
} );
