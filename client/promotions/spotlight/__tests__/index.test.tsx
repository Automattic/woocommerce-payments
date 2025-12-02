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
import { recordEvent } from 'tracks';

interface MockSpotlightProps {
	heading?: React.ReactNode;
	description?: React.ReactNode;
	disclaimer?: React.ReactNode;
	image?: string;
	primaryButtonLabel?: string;
	secondaryButtonLabel?: string;
	onPrimaryClick?: () => void;
	onSecondaryClick?: () => void;
	onDismiss?: () => void;
	onView?: () => void;
}

// Mock the dependencies
jest.mock( 'data', () => ( {
	usePromotions: jest.fn(),
	usePromotionActions: jest.fn(),
} ) );

jest.mock( 'tracks', () => ( {
	recordEvent: jest.fn(),
} ) );

jest.mock( 'components/spotlight', () => ( {
	__esModule: true,
	default: ( props: MockSpotlightProps ) => (
		<div data-testid="spotlight-mock">
			<div data-testid="spotlight-heading">{ props.heading }</div>
			<div data-testid="spotlight-description">{ props.description }</div>
			{ props.disclaimer && (
				<div data-testid="spotlight-disclaimer">
					{ props.disclaimer }
				</div>
			) }
			{ props.image && (
				<div data-testid="spotlight-image">{ props.image }</div>
			) }
			<button onClick={ props.onPrimaryClick }>
				{ props.primaryButtonLabel }
			</button>
			<button onClick={ props.onSecondaryClick }>
				{ props.secondaryButtonLabel }
			</button>
			<button onClick={ props.onDismiss }>Close</button>
			<button onClick={ props.onView }>View</button>
		</div>
	),
} ) );

// Mock window.wcpaySettings
const mockWcpaySettings = {
	accountStatus: {
		status: 'complete',
	},
};

( ( window as unknown ) as Record<
	string,
	unknown
> ).wcpaySettings = mockWcpaySettings;

describe( 'SpotlightPromotion', () => {
	const mockActivatePromotion = jest.fn();
	const mockDismissPromotion = jest.fn();

	// New flat promotion structure (no nested variations).
	const mockPromotionData = [
		{
			id: 'klarna-promo__spotlight',
			promo_id: 'klarna-promo',
			payment_method: 'klarna',
			payment_method_title: 'Klarna',
			type: 'spotlight',
			title: 'Activate Klarna',
			description: 'Offer your customers flexible payments',
			cta_label: 'Activate now',
			tc_url: 'https://example.com/terms',
			tc_label: 'See terms',
			footnote: '*Terms apply',
			image: 'https://example.com/image.png',
		},
	];

	beforeEach( () => {
		jest.clearAllMocks();

		( usePromotionActions as jest.Mock ).mockReturnValue( {
			activatePromotion: mockActivatePromotion,
			dismissPromotion: mockDismissPromotion,
		} );

		( ( window as unknown ) as Record<
			string,
			unknown
		> ).wcpaySettings = mockWcpaySettings;
	} );

	it( 'renders spotlight when account is onboarded and promotion available', () => {
		( usePromotions as jest.Mock ).mockReturnValue( {
			promotions: mockPromotionData,
			isLoading: false,
		} );

		render( <SpotlightPromotion /> );

		expect( screen.getByTestId( 'spotlight-mock' ) ).toBeInTheDocument();
		expect( screen.getByTestId( 'spotlight-heading' ) ).toHaveTextContent(
			'Activate Klarna'
		);
		expect(
			screen.getByTestId( 'spotlight-description' )
		).toHaveTextContent( 'Offer your customers flexible payments' );
	} );

	it( 'does not render when account is not onboarded', () => {
		( ( window as unknown ) as Record< string, unknown > ).wcpaySettings = {
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
	} );

	it( 'does not render when promotions are loading', () => {
		( usePromotions as jest.Mock ).mockReturnValue( {
			promotions: mockPromotionData,
			isLoading: true,
		} );

		const { container } = render( <SpotlightPromotion /> );

		expect( container.firstChild ).toBeNull();
	} );

	it( 'does not render when no spotlight type promotion available', () => {
		( usePromotions as jest.Mock ).mockReturnValue( {
			promotions: [
				{
					id: 'klarna-promo__badge',
					promo_id: 'klarna-promo',
					payment_method: 'klarna',
					payment_method_title: 'Klarna',
					type: 'badge', // Not a spotlight type
					title: 'Different promotion',
					description: 'Badge description',
					cta_label: 'Click',
					tc_url: 'https://example.com/terms',
					tc_label: 'See terms',
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

		expect( mockActivatePromotion ).toHaveBeenCalledWith( 'klarna-promo' );
	} );

	it( 'calls dismissPromotion with single id when close button is clicked', () => {
		( usePromotions as jest.Mock ).mockReturnValue( {
			promotions: mockPromotionData,
			isLoading: false,
		} );

		render( <SpotlightPromotion /> );

		const closeButton = screen.getByText( 'Close' );
		closeButton.click();

		// Now dismissPromotion is called with just the id (flat structure).
		expect( mockDismissPromotion ).toHaveBeenCalledWith(
			'klarna-promo__spotlight'
		);
	} );

	it( 'opens tc_url when secondary button is clicked', () => {
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
			'https://example.com/terms',
			'_blank',
			'noopener,noreferrer'
		);

		windowOpenSpy.mockRestore();
	} );

	it( 'renders disclaimer with terms link when footnote and tc_url provided', () => {
		( usePromotions as jest.Mock ).mockReturnValue( {
			promotions: mockPromotionData,
			isLoading: false,
		} );

		render( <SpotlightPromotion /> );

		expect( screen.getByText( /Terms apply/i ) ).toBeInTheDocument();
		expect( screen.getByText( 'See terms' ) ).toBeInTheDocument();
	} );

	it( 'does not render disclaimer when no footnote provided', () => {
		const dataWithoutFootnote = [
			{
				id: 'klarna-promo__spotlight',
				promo_id: 'klarna-promo',
				payment_method: 'klarna',
				payment_method_title: 'Klarna',
				type: 'spotlight',
				title: 'Activate Klarna',
				description: 'Offer your customers flexible payments',
				cta_label: 'Activate now',
				tc_url: 'https://example.com/terms',
				tc_label: 'See terms',
				// No footnote.
			},
		];

		( usePromotions as jest.Mock ).mockReturnValue( {
			promotions: dataWithoutFootnote,
			isLoading: false,
		} );

		render( <SpotlightPromotion /> );

		expect( screen.getByTestId( 'spotlight-mock' ) ).toBeInTheDocument();
		expect(
			screen.queryByTestId( 'spotlight-disclaimer' )
		).not.toBeInTheDocument();
	} );

	it( 'renders for enabled account status', () => {
		( ( window as unknown ) as Record< string, unknown > ).wcpaySettings = {
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
	} );

	describe( 'tracks events', () => {
		const expectedBaseProperties = {
			promotion_id: 'klarna-promo',
			payment_method: 'klarna',
			id: 'klarna-promo__spotlight',
			display_context: 'spotlight',
			source: 'unknown',
			path: '/',
		};

		beforeEach( () => {
			( usePromotions as jest.Mock ).mockReturnValue( {
				promotions: mockPromotionData,
				isLoading: false,
			} );
		} );

		it( 'records view event when spotlight becomes visible', () => {
			render( <SpotlightPromotion /> );

			const viewButton = screen.getByText( 'View' );
			viewButton.click();

			expect( recordEvent ).toHaveBeenCalledWith(
				'wcpay_payment_method_promotion_view',
				expectedBaseProperties
			);
		} );

		it( 'records activate_click event when primary button is clicked', () => {
			render( <SpotlightPromotion /> );

			const activateButton = screen.getByText( 'Activate now' );
			activateButton.click();

			expect( recordEvent ).toHaveBeenCalledWith(
				'wcpay_payment_method_promotion_activate_click',
				expectedBaseProperties
			);
		} );

		it( 'records secondary_click event when secondary button is clicked', () => {
			jest.spyOn( window, 'open' ).mockImplementation( () => null );

			render( <SpotlightPromotion /> );

			const learnMoreButton = screen.getByText( 'Learn more' );
			learnMoreButton.click();

			expect( recordEvent ).toHaveBeenCalledWith(
				'wcpay_payment_method_promotion_secondary_click',
				expectedBaseProperties
			);
		} );

		it( 'records dismiss event when close button is clicked', () => {
			render( <SpotlightPromotion /> );

			const closeButton = screen.getByText( 'Close' );
			closeButton.click();

			expect( recordEvent ).toHaveBeenCalledWith(
				'wcpay_payment_method_promotion_dismiss',
				expectedBaseProperties
			);
		} );

		it( 'records link_click event when terms link is clicked', () => {
			render( <SpotlightPromotion /> );

			const termsLink = screen.getByText( 'See terms' );
			termsLink.click();

			expect( recordEvent ).toHaveBeenCalledWith(
				'wcpay_payment_method_promotion_link_click',
				{
					...expectedBaseProperties,
					link_type: 'terms',
				}
			);
		} );
	} );
} );
