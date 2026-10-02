/**
 * External dependencies
 */
import React from 'react';
import { render, act } from '@testing-library/react';

/**
 * Internal dependencies
 */
import FraudProtectionTour from '..';

jest.mock( '@woocommerce/components', () => ( {
	TourKit: () => <div data-testid="tour-kit" />,
} ) );

jest.mock( 'wcpay/data/settings', () => ( {
	useSettings: jest.fn().mockReturnValue( { isLoading: false } ),
} ) );

jest.mock( '@wordpress/data', () => ( {
	// Slice stores self-register on import; stub the registration APIs.
	createReduxStore: jest.fn(),
	register: jest.fn(),
	combineReducers: jest.fn(),
	useDispatch: jest.fn( () => ( {
		updateOptions: jest.fn(),
	} ) ),
} ) );

describe( 'FraudProtectionTour', () => {
	let mockIntersectionObserver;
	let referenceElement;

	const reportReferenceVisible = () => {
		act( () => {
			mockIntersectionObserver.mock.calls.forEach( ( [ callback ] ) =>
				callback( [ { isIntersecting: true } ] )
			);
		} );
	};

	beforeEach( () => {
		global.wcpaySettings = {
			fraudProtection: {
				isWelcomeTourDismissed: false,
			},
		};

		jest.clearAllMocks();
		jest.requireMock( 'wcpay/data/settings' ).useSettings.mockReturnValue( {
			isLoading: false,
		} );

		mockIntersectionObserver = jest.fn();
		mockIntersectionObserver.mockReturnValue( {
			observe: jest.fn(),
			disconnect: jest.fn(),
		} );
		window.IntersectionObserver = mockIntersectionObserver;

		referenceElement = document.createElement( 'div' );
		referenceElement.id = 'fp-settings';
		document.body.appendChild( referenceElement );
	} );

	afterEach( () => {
		referenceElement.remove();
	} );

	it( 'should not render the tour component initially', () => {
		const { queryByTestId } = render( <FraudProtectionTour /> );
		expect( queryByTestId( 'tour-kit' ) ).not.toBeInTheDocument();
	} );

	it( 'should render the tour when reference element is visible', () => {
		const { queryByTestId } = render( <FraudProtectionTour /> );

		reportReferenceVisible();

		expect( queryByTestId( 'tour-kit' ) ).toBeInTheDocument();
	} );

	it( 'should not render the tour component if it was already dismissed', () => {
		global.wcpaySettings = {
			fraudProtection: {
				isWelcomeTourDismissed: true,
			},
		};

		const { queryByTestId } = render( <FraudProtectionTour /> );
		reportReferenceVisible();

		expect( queryByTestId( 'tour-kit' ) ).not.toBeInTheDocument();
	} );

	it( 'should not render the tour component if settings are loading', () => {
		jest.requireMock( 'wcpay/data/settings' ).useSettings.mockReturnValue( {
			isLoading: true,
		} );

		const { queryByTestId } = render( <FraudProtectionTour /> );
		reportReferenceVisible();

		expect( queryByTestId( 'tour-kit' ) ).not.toBeInTheDocument();
	} );

	it( 'should not render the tour if reference element is not found', () => {
		referenceElement.remove();

		const { queryByTestId } = render( <FraudProtectionTour /> );
		reportReferenceVisible();

		expect( queryByTestId( 'tour-kit' ) ).not.toBeInTheDocument();
	} );
} );
