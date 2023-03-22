/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';
import { useDispatch } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { useSettings } from 'wcpay/data';
import FraudProtectionTour from '.';

declare const global: {
	wcpaySettings: {
		fraudProtection: {
			isWelcomeTourDismissed: boolean;
		};
	};
};

jest.mock( 'wcpay/data', () => ( { useSettings: jest.fn() } ) );
jest.mock( '@wordpress/data', () => ( { useDispatch: jest.fn() } ) );

const DefaultStructure: React.FC = () => (
	<>
		<div id="wpcontent">Content</div>
		<div id="fraud-protection-card-title">Card title</div>
		<div id="fraud-protection-level-select_advanced-level">
			Advanced level
		</div>
		<div id="toplevel_page_wc-admin-path--payments-overview">
			Payments overview
		</div>

		<FraudProtectionTour />
	</>
);

describe( 'FraudProtectionTour', () => {
	beforeEach( () => {
		( useSettings as jest.Mock ).mockReturnValue( { isLoading: false } );
		( useDispatch as jest.Mock ).mockReturnValue( {
			updateOptions: jest.fn(),
		} );

		global.wcpaySettings = {
			fraudProtection: {
				isWelcomeTourDismissed: false,
			},
		};

		window.scrollTo = jest.fn();
	} );

	afterAll( () => {
		jest.clearAllMocks();
	} );

	it( 'should render the tour component correctly', () => {
		const { baseElement } = render( <DefaultStructure /> );

		expect( baseElement ).toMatchSnapshot();
	} );

	it( 'should not render the tour component if it was already dismissed', () => {
		global.wcpaySettings = {
			fraudProtection: {
				isWelcomeTourDismissed: true,
			},
		};

		const { baseElement } = render( <DefaultStructure /> );

		expect( baseElement ).toMatchSnapshot();
	} );
} );
