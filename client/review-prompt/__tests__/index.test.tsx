/**
 * External dependencies
 */
import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';

/**
 * Internal dependencies
 */
import ReviewPrompt from '..';
import { recordEvent } from 'wcpay/tracks';

// Mock window.open
const mockWindowOpen = jest.fn();
window.open = mockWindowOpen;

// Mock window.location.href
Object.defineProperty( window, 'location', {
	writable: true,
	value: { href: '' },
} );

// Mock the recordEvent function
jest.mock( 'wcpay/tracks', () => ( {
	recordEvent: jest.fn(),
} ) );

// Mock the Spotlight component to simplify testing
jest.mock( 'components/spotlight', () => {
	return ( {
		heading,
		description,
		primaryButtonLabel,
		onPrimaryClick,
		secondaryButtonLabel,
		onSecondaryClick,
		onDismiss,
		onView,
	}: {
		heading: string;
		description: string;
		primaryButtonLabel: string;
		onPrimaryClick: () => void;
		secondaryButtonLabel: string;
		onSecondaryClick: () => void;
		onDismiss: () => void;
		onView: () => void;
	} ) => {
		React.useEffect( () => {
			onView();
		}, [ onView ] );

		return (
			<div data-testid="spotlight">
				<h2>{ heading }</h2>
				<p>{ description }</p>
				<button onClick={ onPrimaryClick }>
					{ primaryButtonLabel }
				</button>
				<button onClick={ onSecondaryClick }>
					{ secondaryButtonLabel }
				</button>
				<button onClick={ onDismiss } aria-label="Dismiss">
					X
				</button>
			</div>
		);
	};
} );

// Mock the useUserPreferences hook
let preferences = {
	wc_payments_review_prompt_dismissed: undefined,
	wc_payments_review_prompt_maybe_later: undefined,
};
jest.mock( '@woocommerce/data', () => {
	return {
		useUserPreferences: jest.fn( () => ( {
			...preferences,
			updateUserPreferences: jest.fn( ( newPrefs ) => {
				preferences = { ...preferences, ...newPrefs };
				return preferences;
			} ),
		} ) ),
	};
} );

// Mock the wcpayReviewPromptSettings global
declare const global: {
	wcpayReviewPromptSettings: {
		version: string;
		experiment: string;
		variant: string;
	};
};

describe( 'ReviewPrompt', () => {
	beforeEach( () => {
		// Reset mocks
		jest.clearAllMocks();
		mockWindowOpen.mockClear();

		// Mock window.open to return a truthy value (successful popup)
		mockWindowOpen.mockReturnValue( {} );

		// Reset preferences
		preferences = {
			wc_payments_review_prompt_dismissed: undefined,
			wc_payments_review_prompt_maybe_later: undefined,
		};

		// Mock the global settings
		global.wcpayReviewPromptSettings = {
			version: '1.0.0',
			experiment: 'woopayments_review_prompt_design_v1',
			variant: 'control',
		};
	} );

	it( 'renders the prompt with correct copy', () => {
		render( <ReviewPrompt /> );

		expect(
			screen.getByText( 'Enjoying WooPayments so far?' )
		).toBeInTheDocument();
		expect(
			screen.getByText(
				'Your feedback shapes our roadmap and supports the WooCommerce community. We are all ears!'
			)
		).toBeInTheDocument();
		expect( screen.getByText( 'Leave review' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Maybe later' ) ).toBeInTheDocument();
	} );

	it( 'records wcpay_review_prompt_shown event on view', () => {
		render( <ReviewPrompt /> );

		expect( recordEvent ).toHaveBeenCalledWith(
			'wcpay_review_prompt_shown',
			expect.objectContaining( {
				prompt_id: 'review_prompt_settings_001',
				extension: 'woopayments',
				location: 'payments_settings_top_level',
				trigger: 'none',
				flag_enabled: true,
				version: '1.0.0',
				experiment: 'woopayments_review_prompt_design_v1',
				variant: 'control',
			} )
		);
	} );

	it.each( [
		[
			'treatment_illustration',
			'treatment_illustration',
			'We built it. You use it. What do you think?',
			'Leave a quick review and help shape what WooPayments does next.',
		],
		[
			'treatment_revised',
			'treatment_revised',
			'Quick check-in?',
			'Your review helps us improve WooPayments and build a better experience for every store owner.',
		],
		[
			'unknown variant fallback',
			'mystery_variant',
			'Enjoying WooPayments so far?',
			'Your feedback shapes our roadmap and supports the WooCommerce community. We are all ears!',
		],
	] )( 'renders %s copy', ( label, variant, heading, description ) => {
		global.wcpayReviewPromptSettings.variant = variant;

		render( <ReviewPrompt /> );

		expect( screen.getByText( heading ) ).toBeInTheDocument();
		expect( screen.getByText( description ) ).toBeInTheDocument();
	} );

	it( 'includes the variant in event props for treatments', () => {
		global.wcpayReviewPromptSettings.variant = 'treatment_revised';

		render( <ReviewPrompt /> );

		expect( recordEvent ).toHaveBeenCalledWith(
			'wcpay_review_prompt_shown',
			expect.objectContaining( {
				experiment: 'woopayments_review_prompt_design_v1',
				variant: 'treatment_revised',
			} )
		);
	} );

	it( 'opens the Marketplace review URL with attribution when "Leave review" is clicked', async () => {
		global.wcpayReviewPromptSettings.variant = 'treatment_illustration';

		render( <ReviewPrompt /> );

		fireEvent.click( screen.getByText( 'Leave review' ) );

		await waitFor( () => {
			expect( mockWindowOpen ).toHaveBeenCalledTimes( 1 );
		} );

		const [ openedUrl, target ] = mockWindowOpen.mock.calls[ 0 ];
		const url = new URL( openedUrl );

		expect( url.origin + url.pathname ).toBe(
			'https://woocommerce.com/products/woopayments/'
		);
		expect( url.searchParams.has( 'review' ) ).toBe( true );
		expect( url.searchParams.get( 'utm_content' ) ).toBe(
			'treatment_illustration'
		);
		expect( url.searchParams.get( 'utm_source' ) ).toBe( 'woopayments' );
		expect( target ).toBe( '_blank' );
	} );

	it( 'records correct telemetry events when "Leave review" is clicked', async () => {
		render( <ReviewPrompt /> );

		const writeReviewButton = screen.getByText( 'Leave review' );
		fireEvent.click( writeReviewButton );

		await waitFor( () => {
			expect( recordEvent ).toHaveBeenCalledWith(
				'wcpay_review_prompt_action',
				expect.objectContaining( {
					experiment: 'woopayments_review_prompt_design_v1',
					variant: 'control',
					action: 'write_review',
					destination: 'marketplace',
					time_to_click_ms: expect.any( Number ),
				} )
			);
		} );
	} );

	it.each( [
		[
			'Maybe later',
			() => screen.getByText( 'Maybe later' ),
			'maybe_later',
		],
		[
			'dismiss (X)',
			() => screen.getByLabelText( 'Dismiss' ),
			'dismiss_x',
		],
	] )(
		'records correct event when %s is clicked',
		( label, getButton, action ) => {
			render( <ReviewPrompt /> );

			fireEvent.click( getButton() );

			expect( recordEvent ).toHaveBeenCalledWith(
				'wcpay_review_prompt_action',
				expect.objectContaining( {
					action,
					time_to_click_ms: expect.any( Number ),
				} )
			);
		}
	);

	it.each( [
		[ 'Leave review', () => screen.getByText( 'Leave review' ) ],
		[ 'Maybe later', () => screen.getByText( 'Maybe later' ) ],
		[ 'dismiss (X)', () => screen.getByLabelText( 'Dismiss' ) ],
	] )( 'hides prompt after %s is clicked', async ( label, getButton ) => {
		const { container } = render( <ReviewPrompt /> );

		fireEvent.click( getButton() );

		await waitFor( () => {
			expect( container.firstChild ).toBeNull();
		} );
	} );

	it( 'tracks time_to_click_ms correctly', async () => {
		jest.useFakeTimers();
		const startTime = Date.now();
		jest.setSystemTime( startTime );

		render( <ReviewPrompt /> );

		jest.advanceTimersByTime( 5000 );

		const writeReviewButton = screen.getByText( 'Leave review' );
		fireEvent.click( writeReviewButton );

		await waitFor( () => {
			expect( recordEvent ).toHaveBeenCalledWith(
				'wcpay_review_prompt_action',
				expect.objectContaining( {
					time_to_click_ms: 5000,
				} )
			);
		} );

		jest.useRealTimers();
	} );

	it( 'falls back to window.location when window.open fails', async () => {
		mockWindowOpen.mockReturnValueOnce( null );

		render( <ReviewPrompt /> );

		const writeReviewButton = screen.getByText( 'Leave review' );
		fireEvent.click( writeReviewButton );

		await waitFor( () => {
			expect( mockWindowOpen ).toHaveBeenCalledWith(
				expect.stringContaining(
					'https://woocommerce.com/products/woopayments/'
				),
				'_blank'
			);

			expect( window.location.href ).toContain(
				'https://woocommerce.com/products/woopayments/'
			);
		} );
	} );
} );
