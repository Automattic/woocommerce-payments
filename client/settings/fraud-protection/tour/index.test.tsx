/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import FraudProtectionTour from './';

declare const global: {
	wcpaySettings: {
		fraudProtection: {
			isWelcomeTourDismissed: boolean;
		};
	};
};

jest.mock( 'wcpay/data', () => ( {
	useSettings: jest.fn().mockReturnValue( { isLoading: false } ),
} ) );

jest.mock( '@wordpress/data', () => ( {
	useDispatch: jest.fn( () => ( {
		createNotice: jest.fn(),
		updateOptions: jest.fn(),
	} ) ),
} ) );

jest.mock( '@woocommerce/components', () => ( {
	TourKit: () => <div>Tour Component</div>,
} ) );

describe( 'FraudProtectionTour', () => {
	beforeEach( () => {
		global.wcpaySettings = {
			fraudProtection: {
				isWelcomeTourDismissed: false,
			},
		};
	} );

	afterAll( () => {
		jest.clearAllMocks();
	} );

	it( 'should render the tour component correctly', () => {
		Reflect.defineProperty( window, 'location', {
			configurable: true,
			enumerable: true,
			value: {
				search:
					'?page=wc-settings&tab=checkout&anchor=%23fp-settings&section=woocommerce_payments/',
			},
		} );

		const { baseElement } = render( <FraudProtectionTour /> );

		expect( baseElement ).toMatchSnapshot();
	} );

	it( 'should not render the tour component if it was already dismissed', () => {
		global.wcpaySettings = {
			fraudProtection: {
				isWelcomeTourDismissed: true,
			},
		};

		const { baseElement } = render( <FraudProtectionTour /> );

		expect( baseElement ).toMatchSnapshot();
	} );

	it( 'should not render the tour component if settings page accessed directly', () => {
		Reflect.deleteProperty( window, 'location' );
		Reflect.defineProperty( window, 'location', {
			configurable: true,
			enumerable: true,
			value: {
				search:
					'?page=wc-settings&tab=checkout&section=woocommerce_payments/',
			},
		} );

		const { baseElement } = render( <FraudProtectionTour /> );

		expect( baseElement ).toMatchSnapshot();
	} );
} );
