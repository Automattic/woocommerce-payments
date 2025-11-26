/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import SpotlightPromotion from '../index';
import { usePromotions, usePromotionActions } from 'data';

interface MockSpotlightProps {
	badge?: React.ReactNode;
	heading?: React.ReactNode;
	description?: React.ReactNode;
	disclaimer?: React.ReactNode;
	primaryButtonLabel?: string;
	secondaryButtonLabel?: string;
	onPrimaryClick?: () => void;
	onSecondaryClick?: () => void;
	onDismiss?: () => void;
}

// Mock the dependencies
jest.mock( 'data', () => ( {
	usePromotions: jest.fn(),
	usePromotionActions: jest.fn(),
} ) );

jest.mock( 'components/spotlight', () => ( {
	__esModule: true,
	default: ( props: MockSpotlightProps ) => (
		<div data-testid="spotlight-mock">
			<div data-testid="spotlight-badge">{ props.badge }</div>
			<div data-testid="spotlight-heading">{ props.heading }</div>
			<div data-testid="spotlight-description">{ props.description }</div>
			{ props.disclaimer && (
				<div data-testid="spotlight-disclaimer">
					{ props.disclaimer }
				</div>
			) }
			<button onClick={ props.onPrimaryClick }>
				{ props.primaryButtonLabel }
			</button>
			<button onClick={ props.onSecondaryClick }>
				{ props.secondaryButtonLabel }
			</button>
			<button onClick={ props.onDismiss }>Close</button>
		</div>
	),
} ) );

// Mock the SVG import
jest.mock(
	'assets/images/illustrations/klarna-promotion-spotlight.svg?asset',
	() => 'mocked-image-url'
);

// Mock window.wcpaySettings
const mockWcpaySettings = {
	accountStatus: {
		status: 'complete',
	},
};

( window as any ).wcpaySettings = mockWcpaySettings;

describe( 'SpotlightPromotion', () => {
	const mockActivatePromotion = jest.fn();
	const mockDismissPromotion = jest.fn();

	const mockPromotionData = [
		{
			promo_id: 'promo_123',
			discount_rate: '100%',
			duration_days: 90,
			variations: [
				{
					id: 'promo_123__spotlight_1',
					type: 'spotlight',
					badge: 'Limited time offer',
					heading: 'Activate Klarna',
					description: 'Offer your customers flexible payments',
					cta_label: 'Activate now',
					cta_url: 'https://example.com/learn-more',
					footnote: '*Terms apply',
					tc_url: 'https://example.com/terms',
				},
			],
		},
	];

	beforeEach( () => {
		jest.clearAllMocks();

		( usePromotionActions as jest.Mock ).mockReturnValue( {
			activatePromotion: mockActivatePromotion,
			dismissPromotion: mockDismissPromotion,
		} );
	} );

	it( 'renders spotlight when account is onboarded and promotion available', () => {
		( usePromotions as jest.Mock ).mockReturnValue( {
			promotions: mockPromotionData,
			isLoading: false,
		} );

		render( <SpotlightPromotion /> );

		expect( screen.getByTestId( 'spotlight-mock' ) ).toBeInTheDocument();
		expect( screen.getByTestId( 'spotlight-badge' ) ).toHaveTextContent(
			'Limited time offer'
		);
		expect( screen.getByTestId( 'spotlight-heading' ) ).toHaveTextContent(
			'Activate Klarna'
		);
		expect(
			screen.getByTestId( 'spotlight-description' )
		).toHaveTextContent( 'Offer your customers flexible payments' );
	} );

	it( 'does not render when account is not onboarded', () => {
		( window as any ).wcpaySettings = {
			accountStatus: {
				status: 'pending',
			},
		};

		( usePromotions as jest.Mock ).mockReturnValue( {
			promotions: mockPromotionData,
			isLoading: false,
		} );

		const { container } = render( <SpotlightPromotion /> );

		expect( container.firstChild ).toBeNull();

		// Reset to original
		( window as any ).wcpaySettings = mockWcpaySettings;
	} );

	it( 'does not render when promotions are loading', () => {
		( usePromotions as jest.Mock ).mockReturnValue( {
			promotions: mockPromotionData,
			isLoading: true,
		} );

		const { container } = render( <SpotlightPromotion /> );

		expect( container.firstChild ).toBeNull();
	} );

	it( 'does not render when no spotlight variation available', () => {
		( usePromotions as jest.Mock ).mockReturnValue( {
			promotions: [
				{
					promo_id: 'promo_123',
					discount_rate: '100%',
					duration_days: 90,
					variations: [
						{
							id: 'promo_123__banner_1',
							type: 'banner', // Not a spotlight type
							heading: 'Different promotion',
							description: 'Banner description',
							cta_label: 'Click',
							cta_url: '#',
						},
					],
				},
			],
			isLoading: false,
		} );

		const { container } = render( <SpotlightPromotion /> );

		expect( container.firstChild ).toBeNull();
	} );

	it( 'does not render when no promotions available', () => {
		( usePromotions as jest.Mock ).mockReturnValue( {
			promotions: [],
			isLoading: false,
		} );

		const { container } = render( <SpotlightPromotion /> );

		expect( container.firstChild ).toBeNull();
	} );

	it( 'calls activatePromotion when primary button is clicked', () => {
		( usePromotions as jest.Mock ).mockReturnValue( {
			promotions: mockPromotionData,
			isLoading: false,
		} );

		render( <SpotlightPromotion /> );

		const activateButton = screen.getByText( 'Activate now' );
		activateButton.click();

		expect( mockActivatePromotion ).toHaveBeenCalledWith( 'promo_123' );
	} );

	it( 'calls dismissPromotion when close button is clicked', () => {
		( usePromotions as jest.Mock ).mockReturnValue( {
			promotions: mockPromotionData,
			isLoading: false,
		} );

		render( <SpotlightPromotion /> );

		const closeButton = screen.getByText( 'Close' );
		closeButton.click();

		expect( mockDismissPromotion ).toHaveBeenCalledWith(
			'promo_123',
			'promo_123__spotlight_1'
		);
	} );

	it( 'opens learn more URL when secondary button is clicked', () => {
		( usePromotions as jest.Mock ).mockReturnValue( {
			promotions: mockPromotionData,
			isLoading: false,
		} );

		const windowOpenSpy = jest
			.spyOn( window, 'open' )
			.mockImplementation( () => null );

		render( <SpotlightPromotion /> );

		const learnMoreButton = screen.getByText( 'Learn more' );
		learnMoreButton.click();

		expect( windowOpenSpy ).toHaveBeenCalledWith(
			'https://example.com/learn-more',
			'_blank',
			'noopener,noreferrer'
		);

		windowOpenSpy.mockRestore();
	} );

	it( 'renders disclaimer with terms link when both footnote and tc_url provided', () => {
		( usePromotions as jest.Mock ).mockReturnValue( {
			promotions: mockPromotionData,
			isLoading: false,
		} );

		render( <SpotlightPromotion /> );

		expect( screen.getByText( /Terms apply/i ) ).toBeInTheDocument();
		expect(
			screen.getByText( 'Terms and conditions' )
		).toBeInTheDocument();
	} );

	it( 'renders disclaimer without link when only footnote provided', () => {
		const dataWithoutTcUrl = [
			{
				promo_id: 'promo_123',
				discount_rate: '100%',
				duration_days: 90,
				variations: [
					{
						id: 'promo_123__spotlight_1',
						type: 'spotlight',
						badge: 'Limited time offer',
						heading: 'Activate Klarna',
						description: 'Offer your customers flexible payments',
						cta_label: 'Activate now',
						cta_url: 'https://example.com/learn-more',
						footnote: '*Terms apply',
					},
				],
			},
		];

		( usePromotions as jest.Mock ).mockReturnValue( {
			promotions: dataWithoutTcUrl,
			isLoading: false,
		} );

		render( <SpotlightPromotion /> );

		expect( screen.getByText( '*Terms apply' ) ).toBeInTheDocument();
		expect(
			screen.queryByText( 'Terms and conditions' )
		).not.toBeInTheDocument();
	} );

	it( 'renders for enabled account status', () => {
		( window as any ).wcpaySettings = {
			accountStatus: {
				status: 'enabled',
			},
		};

		( usePromotions as jest.Mock ).mockReturnValue( {
			promotions: mockPromotionData,
			isLoading: false,
		} );

		render( <SpotlightPromotion /> );

		expect( screen.getByTestId( 'spotlight-mock' ) ).toBeInTheDocument();

		// Reset to original
		( window as any ).wcpaySettings = mockWcpaySettings;
	} );
} );
