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
		isLive: boolean;
		version: string;
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
			isLive: true,
			version: '1.0.0',
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

	it( 'records payments_review_prompt_shown event on view', () => {
		render( <ReviewPrompt /> );

		expect( recordEvent ).toHaveBeenCalledWith(
			'payments_review_prompt_shown',
			expect.objectContaining( {
				prompt_id: 'phase0_payments_settings_001',
				extension: 'woopayments',
				location: 'payments_settings_top_level',
				trigger: 'none',
				flag_enabled: true,
				version: '1.0.0',
			} )
		);
	} );

	it( 'opens WordPress.org review URL when "Leave review" is clicked in live mode', async () => {
		global.wcpayReviewPromptSettings.isLive = true;

		render( <ReviewPrompt /> );

		const writeReviewButton = screen.getByText( 'Leave review' );
		fireEvent.click( writeReviewButton );

		await waitFor( () => {
			expect( mockWindowOpen ).toHaveBeenCalledWith(
				'https://wordpress.org/support/plugin/woocommerce-payments/reviews/#new-post',
				'_blank',
				'noopener,noreferrer'
			);
		} );
	} );

	it( 'opens marketplace review URL when "Leave review" is clicked in test mode', async () => {
		global.wcpayReviewPromptSettings.isLive = false;

		render( <ReviewPrompt /> );

		const writeReviewButton = screen.getByText( 'Leave review' );
		fireEvent.click( writeReviewButton );

		await waitFor( () => {
			expect( mockWindowOpen ).toHaveBeenCalledWith(
				'https://woocommerce.com/products/woocommerce-payments/#reviews',
				'_blank',
				'noopener,noreferrer'
			);
		} );
	} );

	it( 'records correct telemetry events when "Leave review" is clicked', async () => {
		render( <ReviewPrompt /> );

		const writeReviewButton = screen.getByText( 'Leave review' );
		fireEvent.click( writeReviewButton );

		await waitFor( () => {
			// Should record action event
			expect( recordEvent ).toHaveBeenCalledWith(
				'payments_review_prompt_action',
				expect.objectContaining( {
					action: 'write_review',
					destination: 'wordpress_org',
					time_to_click_ms: expect.any( Number ),
				} )
			);

			// Should record destination selected event
			expect( recordEvent ).toHaveBeenCalledWith(
				'payments_review_destination_selected',
				expect.objectContaining( {
					action: 'write_review',
					destination: 'wordpress_org',
				} )
			);
		} );
	} );

	it( 'hides prompt after "Leave review" is clicked', async () => {
		const { container } = render( <ReviewPrompt /> );

		const writeReviewButton = screen.getByText( 'Leave review' );
		fireEvent.click( writeReviewButton );

		await waitFor( () => {
			expect( container.firstChild ).toBeNull();
		} );
	} );

	it( 'records correct event when "Maybe later" is clicked', () => {
		render( <ReviewPrompt /> );

		const maybeLaterButton = screen.getByText( 'Maybe later' );
		fireEvent.click( maybeLaterButton );

		expect( recordEvent ).toHaveBeenCalledWith(
			'payments_review_prompt_action',
			expect.objectContaining( {
				action: 'maybe_later',
				time_to_click_ms: expect.any( Number ),
			} )
		);
	} );

	it( 'hides prompt after "Maybe later" is clicked', async () => {
		const { container } = render( <ReviewPrompt /> );

		const maybeLaterButton = screen.getByText( 'Maybe later' );
		fireEvent.click( maybeLaterButton );

		await waitFor( () => {
			expect( container.firstChild ).toBeNull();
		} );
	} );

	it( 'records correct event when dismiss (X) is clicked', () => {
		render( <ReviewPrompt /> );

		const dismissButton = screen.getByLabelText( 'Dismiss' );
		fireEvent.click( dismissButton );

		expect( recordEvent ).toHaveBeenCalledWith(
			'payments_review_prompt_action',
			expect.objectContaining( {
				action: 'dismiss_x',
				time_to_click_ms: expect.any( Number ),
			} )
		);
	} );

	it( 'hides prompt after dismiss (X) is clicked', async () => {
		const { container } = render( <ReviewPrompt /> );

		const dismissButton = screen.getByLabelText( 'Dismiss' );
		fireEvent.click( dismissButton );

		await waitFor( () => {
			expect( container.firstChild ).toBeNull();
		} );
	} );

	it( 'tracks time_to_click_ms correctly', async () => {
		jest.useFakeTimers();
		const startTime = Date.now();
		jest.setSystemTime( startTime );

		render( <ReviewPrompt /> );

		// Advance time by 5 seconds
		jest.advanceTimersByTime( 5000 );

		const writeReviewButton = screen.getByText( 'Leave review' );
		fireEvent.click( writeReviewButton );

		await waitFor( () => {
			expect( recordEvent ).toHaveBeenCalledWith(
				'payments_review_prompt_action',
				expect.objectContaining( {
					time_to_click_ms: 5000,
				} )
			);
		} );

		jest.useRealTimers();
	} );

	it( 'uses correct destination based on connection state', async () => {
		// Test live mode
		global.wcpayReviewPromptSettings.isLive = true;
		const { unmount } = render( <ReviewPrompt /> );

		let writeReviewButton = screen.getByText( 'Leave review' );
		fireEvent.click( writeReviewButton );

		await waitFor( () => {
			expect( recordEvent ).toHaveBeenCalledWith(
				'payments_review_destination_selected',
				expect.objectContaining( {
					destination: 'wordpress_org',
				} )
			);
		} );

		// Unmount, reset, and test test mode with a fresh render
		unmount();
		jest.clearAllMocks();
		global.wcpayReviewPromptSettings.isLive = false;
		render( <ReviewPrompt /> );

		writeReviewButton = screen.getByText( 'Leave review' );
		fireEvent.click( writeReviewButton );

		await waitFor( () => {
			expect( recordEvent ).toHaveBeenCalledWith(
				'payments_review_destination_selected',
				expect.objectContaining( {
					destination: 'marketplace',
				} )
			);
		} );
	} );

	it( 'falls back to window.location when window.open fails', async () => {
		// Mock window.open to return null (popup blocked)
		mockWindowOpen.mockReturnValueOnce( null );

		global.wcpayReviewPromptSettings.isLive = true;

		render( <ReviewPrompt /> );

		const writeReviewButton = screen.getByText( 'Leave review' );
		fireEvent.click( writeReviewButton );

		await waitFor( () => {
			// Should have tried to open in new window
			expect( mockWindowOpen ).toHaveBeenCalledWith(
				'https://wordpress.org/support/plugin/woocommerce-payments/reviews/#new-post',
				'_blank',
				'noopener,noreferrer'
			);

			// Should fall back to navigating current window
			expect( window.location.href ).toBe(
				'https://wordpress.org/support/plugin/woocommerce-payments/reviews/#new-post'
			);
		} );
	} );
} );
