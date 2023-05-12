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

jest.mock( 'components/stepper', () => ( {
	useStepperContext: jest.fn( () => ( {
		currentStep: 'loading',
	} ) ),
} ) );

const checkLinkToContainNecessaryParams = ( link: string ) => {
	expect( link ).toContain( 'self_assessment' );
	expect( link ).toContain( 'progressive' );
	expect( link ).toContain( 'country' );
	expect( link ).toContain( 'mcc' );
	expect( link ).toContain( 'annual_revenue' );
	expect( link ).toContain( 'business_type' );
	expect( link ).toContain( 'go_live_timeframe' );
};

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

		render( <Loading name="loading" /> );

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

		checkLinkToContainNecessaryParams( window.location.href );
	} );

	it( 'renders loading screen and sends request to server in case of po not eligible', async () => {
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

		render( <Loading name="loading" /> );

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

		checkLinkToContainNecessaryParams( window.location.href );
	} );
} );
