/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';
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

	it( 'renders loading screen', async () => {
		data = {
			country: 'US',
			business_type: 'individual',
			mcc: 'most_popular__software_services',
			annual_revenue: 'less_than_250k',
			go_live_timeframe: 'within_1month',
		};

		render( <Loading name="loading" /> );

		checkLinkToContainNecessaryParams( window.location.href );
	} );
} );
