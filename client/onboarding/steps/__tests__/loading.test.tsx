/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';
/**
 * Internal dependencies
 */
import Loading from '../loading';
import { redirectTo } from 'wcpay/utils';

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

// jsdom marks `window.location` unforgeable, so the redirect is asserted
// through the `redirectTo` helper the step calls.
jest.mock( 'wcpay/utils', () => ( {
	...jest.requireActual( 'wcpay/utils' ),
	redirectTo: jest.fn(),
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
	expect( link ).toContain( 'business_type' );
};

describe( 'Loading', () => {
	beforeEach( () => {
		( redirectTo as jest.Mock ).mockClear();
		global.wcpaySettings = {
			connectUrl: 'http://wcpay-connect-url',
		};
	} );

	it( 'renders loading screen', async () => {
		data = {
			country: 'US',
			business_type: 'individual',
			mcc: 'most_popular__software_services',
		};

		render( <Loading /> );

		expect( redirectTo ).toHaveBeenCalledTimes( 1 );
		checkLinkToContainNecessaryParams(
			( redirectTo as jest.Mock ).mock.calls[ 0 ][ 0 ]
		);
	} );
} );
