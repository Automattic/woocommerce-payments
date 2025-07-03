/**
 * External dependencies
 */
import React from 'react';
import { render, act } from '@testing-library/react';

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

jest.mock( '@woocommerce/components', () => ( {
	TourKit: () => <div data-testid="tour-kit" />,
} ) );

jest.mock( 'wcpay/data', () => ( {
	useSettings: jest.fn().mockReturnValue( { isLoading: false } ),
} ) );

jest.mock( '@wordpress/data', () => ( {
	useDispatch: jest.fn( () => ( {
		updateOptions: jest.fn(),
	} ) ),
} ) );

describe( 'FraudProtectionTour', () => {
	let mockIntersectionObserver: jest.Mock;

	beforeEach( () => {
		global.wcpaySettings = {
			fraudProtection: {
				isWelcomeTourDismissed: false,
			},
		};

		jest.clearAllMocks();

		mockIntersectionObserver = jest.fn();
		mockIntersectionObserver.mockReturnValue( {
			observe: jest.fn(),
			disconnect: jest.fn(),
		} );
		window.IntersectionObserver = mockIntersectionObserver;
	} );

	it( 'should not render the tour component initially', () => {
		const { queryByTestId } = render( <FraudProtectionTour /> );
		expect( queryByTestId( 'tour-kit' ) ).not.toBeInTheDocument();
	} );

	it( 'should render the tour when reference element is visible', () => {
		const referenceElement = document.createElement( 'div' );
		referenceElement.id = 'fp-settings';
		document.body.appendChild( referenceElement );

		const { queryByTestId } = render( <FraudProtectionTour /> );

		// Simulate intersection
		const [ observerCallback ] = mockIntersectionObserver.mock.calls[ 0 ];
		act( () => {
			observerCallback( [ { isIntersecting: true } ] );
		} );

		expect( queryByTestId( 'tour-kit' ) ).toBeInTheDocument();

		document.body.removeChild( referenceElement );
	} );

	it( 'should not render the tour component if it was already dismissed', () => {
		global.wcpaySettings = {
			fraudProtection: {
				isWelcomeTourDismissed: true,
			},
		};

		const { queryByTestId } = render( <FraudProtectionTour /> );
		expect( queryByTestId( 'tour-kit' ) ).not.toBeInTheDocument();
	} );

	it( 'should not render the tour component if settings are loading', () => {
		jest.requireMock( 'wcpay/data' ).useSettings.mockReturnValue( {
			isLoading: true,
		} );

		const { queryByTestId } = render( <FraudProtectionTour /> );
		expect( queryByTestId( 'tour-kit' ) ).not.toBeInTheDocument();
	} );

	it( 'should not render the tour if reference element is not found', () => {
		const { queryByTestId } = render( <FraudProtectionTour /> );
		expect( queryByTestId( 'tour-kit' ) ).not.toBeInTheDocument();
	} );
} );
