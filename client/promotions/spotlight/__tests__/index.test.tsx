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
import { usePmPromotions, usePmPromotionActions } from 'wcpay/data';
import { recordEvent } from 'tracks';

interface MockSpotlightProps {
	heading?: React.ReactNode;
	description?: React.ReactNode;
	footnote?: React.ReactNode;
	image?: string;
	primaryButtonLabel?: string;
	secondaryButtonLabel?: string;
	onPrimaryClick?: () => void;
	onSecondaryClick?: () => void;
	onDismiss?: () => void;
	onView?: () => void;
}

// Mock the dependencies
jest.mock( 'wcpay/data', () => ( {
	usePmPromotions: jest.fn(),
	usePmPromotionActions: jest.fn(),
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
			{ props.footnote && (
				<div data-testid="spotlight-footnote">{ props.footnote }</div>
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

describe( 'SpotlightPromotion', () => {
	const mockActivatePmPromotion = jest.fn();
	const mockDismissPmPromotion = jest.fn();

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

		( usePmPromotionActions as jest.Mock ).mockReturnValue( {
			activatePmPromotion: mockActivatePmPromotion,
			dismissPmPromotion: mockDismissPmPromotion,
		} );
	} );

	it( 'renders spotlight when promotion available', () => {
		( usePmPromotions as jest.Mock ).mockReturnValue( {
			pmPromotions: mockPromotionData,
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

	it( 'does not render when promotions are loading', () => {
		( usePmPromotions as jest.Mock ).mockReturnValue( {
			pmPromotions: mockPromotionData,
			isLoading: true,
		} );

		const { container } = render( <SpotlightPromotion /> );

		expect( container.firstChild ).toBeNull();
	} );

	it( 'does not render when no spotlight type promotion available', () => {
		( usePmPromotions as jest.Mock ).mockReturnValue( {
			pmPromotions: [
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
		( usePmPromotions as jest.Mock ).mockReturnValue( {
			pmPromotions: [],
			isLoading: false,
		} );

		const { container } = render( <SpotlightPromotion /> );

		expect( container.firstChild ).toBeNull();
	} );

	it( 'calls activatePmPromotion when primary button is clicked', () => {
		( usePmPromotions as jest.Mock ).mockReturnValue( {
			pmPromotions: mockPromotionData,
			isLoading: false,
		} );

		render( <SpotlightPromotion /> );

		const activateButton = screen.getByText( 'Activate now' );
		activateButton.click();

		expect( mockActivatePmPromotion ).toHaveBeenCalledWith(
			'klarna-promo__spotlight'
		);
	} );

	it( 'calls dismissPmPromotion with single id when close button is clicked', () => {
		( usePmPromotions as jest.Mock ).mockReturnValue( {
			pmPromotions: mockPromotionData,
			isLoading: false,
		} );

		render( <SpotlightPromotion /> );

		const closeButton = screen.getByText( 'Close' );
		closeButton.click();

		// Now dismissPmPromotion is called with just the id (flat structure).
		expect( mockDismissPmPromotion ).toHaveBeenCalledWith(
			'klarna-promo__spotlight'
		);
	} );

	it( 'opens tc_url when secondary button is clicked', () => {
		( usePmPromotions as jest.Mock ).mockReturnValue( {
			pmPromotions: mockPromotionData,
			isLoading: false,
		} );

		const windowOpenSpy = jest
			.spyOn( window, 'open' )
			.mockImplementation( () => null );

		render( <SpotlightPromotion /> );

		// Secondary button uses tc_label from promotion data.
		const termsButton = screen.getByText( 'See terms' );
		termsButton.click();

		expect( windowOpenSpy ).toHaveBeenCalledWith(
			'https://example.com/terms',
			'_blank',
			'noopener,noreferrer'
		);

		windowOpenSpy.mockRestore();
	} );

	it( 'renders footnote text', () => {
		( usePmPromotions as jest.Mock ).mockReturnValue( {
			pmPromotions: mockPromotionData,
			isLoading: false,
		} );

		render( <SpotlightPromotion /> );

		expect( screen.getByText( /Terms apply/i ) ).toBeInTheDocument();
	} );

	it( 'does not render footnote when not provided', () => {
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

		( usePmPromotions as jest.Mock ).mockReturnValue( {
			pmPromotions: dataWithoutFootnote,
			isLoading: false,
		} );

		render( <SpotlightPromotion /> );

		expect( screen.getByTestId( 'spotlight-mock' ) ).toBeInTheDocument();
		expect(
			screen.queryByTestId( 'spotlight-footnote' )
		).not.toBeInTheDocument();
	} );

	describe( 'tracks events', () => {
		const expectedBaseProperties = {
			promo_id: 'klarna-promo',
			payment_method: 'klarna',
			display_context: 'spotlight',
			source: 'unknown',
			path: '/',
		};

		beforeEach( () => {
			( usePmPromotions as jest.Mock ).mockReturnValue( {
				pmPromotions: mockPromotionData,
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

		it( 'records link_click event when secondary button is clicked', () => {
			const windowOpenSpy = jest
				.spyOn( window, 'open' )
				.mockImplementation( () => null );

			render( <SpotlightPromotion /> );

			// Secondary button now uses tc_label.
			const termsButton = screen.getByText( 'See terms' );
			termsButton.click();

			expect( recordEvent ).toHaveBeenCalledWith(
				'wcpay_payment_method_promotion_link_click',
				{
					...expectedBaseProperties,
					link_type: 'terms',
				}
			);

			windowOpenSpy.mockRestore();
		} );

		it( 'records dismiss event when close button is clicked', () => {
			render( <SpotlightPromotion /> );

			const closeButton = screen.getByText( 'Close' );
			closeButton.click();

			expect( recordEvent ).toHaveBeenCalledWith(
				'wcpay_payment_method_promotion_dismiss_click',
				expectedBaseProperties
			);
		} );
	} );
} );
