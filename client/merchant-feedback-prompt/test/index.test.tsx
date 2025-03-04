/**
 * External dependencies
 */
import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import { select } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { MaybeShowMerchantFeedbackPrompt } from '../index';
import { recordEvent } from 'wcpay/tracks';

// Mock the WordPress data module
jest.mock( '@wordpress/data', () => ( {
	useSelect: jest.fn().mockImplementation( ( fn ) => fn( select ) ),
	select: jest.fn(),
} ) );

// Mock ReactDOM.createPortal
jest.mock( 'react-dom', () => ( {
	...jest.requireActual( 'react-dom' ),
	createPortal: ( element: React.ReactNode ) => element,
} ) );

// Mock window.scrollTo to fix the "Not implemented: window.scrollTo" error
window.scrollTo = jest.fn();

// Mock the recordEvent function
jest.mock( 'wcpay/tracks', () => ( {
	recordEvent: jest.fn(),
} ) );

// Mock the useUserPreferences hook
let preferences = {
	wc_payments_wporg_review_2025_prompt_dismissed: undefined,
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

// Mock the wcpaySettings object
declare const global: {
	wcpaySettings: {
		featureFlags: {
			isMerchantFeedbackPromptDevFlagEnabled: boolean;
		};
		accountStatus: {
			campaigns: {
				wporgReview2025: boolean;
			};
		};
	};
};

describe( 'MerchantFeedbackPrompt', () => {
	// Create a mock footer element for the portal
	let portalRoot: HTMLDivElement;

	beforeEach( () => {
		// Reset mocks
		jest.clearAllMocks();

		// Mock the core/notices select function to return empty notices by default
		( select as jest.Mock ).mockImplementation( () => ( {
			getNotices: jest.fn().mockReturnValue( [] ),
		} ) );

		// Reset the preferences to the initial state
		preferences = {
			wc_payments_wporg_review_2025_prompt_dismissed: undefined,
		};

		// Mock the dev feature flag to be enabled
		global.wcpaySettings = {
			featureFlags: {
				isMerchantFeedbackPromptDevFlagEnabled: true,
			},
			accountStatus: {
				campaigns: {
					wporgReview2025: true,
				},
			},
		};

		// Create a mock footer element for the portal
		portalRoot = document.createElement( 'div' );
		portalRoot.className = 'woocommerce-layout__footer';
		document.body.appendChild( portalRoot );
	} );

	afterEach( () => {
		// Clean up
		document.body.removeChild( portalRoot );
	} );

	it( 'does not render when the dev feature flag is disabled', () => {
		global.wcpaySettings = {
			featureFlags: {
				isMerchantFeedbackPromptDevFlagEnabled: false,
			},
			accountStatus: {
				campaigns: {
					wporgReview2025: true,
				},
			},
		};

		render( <MaybeShowMerchantFeedbackPrompt /> );

		// The prompt should not be rendered
		expect(
			screen.queryByText( 'Are you satisfied with WooPayments?' )
		).not.toBeInTheDocument();
	} );

	it( 'does not render when the account is not eligible for the campaign', () => {
		global.wcpaySettings = {
			featureFlags: {
				isMerchantFeedbackPromptDevFlagEnabled: true,
			},
			accountStatus: {
				campaigns: {
					wporgReview2025: false,
				},
			},
		};

		render( <MaybeShowMerchantFeedbackPrompt /> );

		// The prompt should not be rendered
		expect(
			screen.queryByText( 'Are you satisfied with WooPayments?' )
		).not.toBeInTheDocument();
	} );

	it( 'renders the feedback prompt when there are no core notices', () => {
		render( <MaybeShowMerchantFeedbackPrompt /> );

		// Check if the prompt text is rendered
		const promptElement = screen.getByText(
			'Are you satisfied with WooPayments?'
		);
		expect( promptElement ).toBeInTheDocument();

		// Check if Yes and No buttons are rendered
		expect( screen.getByText( 'Yes' ) ).toBeInTheDocument();
		expect( screen.getByText( 'No' ) ).toBeInTheDocument();

		// Check if dismiss button is rendered
		expect(
			screen.getByRole( 'button', {
				name: 'Dismiss',
			} )
		).toBeInTheDocument();
	} );

	it( 'does not render when there are core notices', () => {
		// Mock core notices to return some notices
		( select as jest.Mock ).mockImplementation( () => ( {
			getNotices: jest.fn().mockReturnValue( [ { id: 'test-notice' } ] ),
		} ) );

		render( <MaybeShowMerchantFeedbackPrompt /> );

		// The prompt should not be rendered
		expect(
			screen.queryByText( 'Are you satisfied with WooPayments?' )
		).not.toBeInTheDocument();

		// Should not record the view event
		expect( recordEvent ).not.toHaveBeenCalled();
	} );

	it( 'does not render after being dismissed', async () => {
		// First render
		const { rerender } = render( <MaybeShowMerchantFeedbackPrompt /> );

		// Verify prompt is initially rendered
		expect(
			screen.getByText( 'Are you satisfied with WooPayments?' )
		).toBeInTheDocument();

		// Click the dismiss button
		const dismissButton = screen.getByRole( 'button', {
			name: 'Dismiss',
		} );
		fireEvent.click( dismissButton );

		// Expect the event to be recorded
		expect( recordEvent ).toHaveBeenCalledWith(
			'wcpay_merchant_feedback_prompt_dismiss'
		);

		// Re-render the component to verify it's no longer shown
		rerender( <MaybeShowMerchantFeedbackPrompt /> );

		// The prompt should no longer be rendered
		expect(
			screen.queryByText( 'Are you satisfied with WooPayments?' )
		).not.toBeInTheDocument();
	} );

	it( 'records event when Yes button is clicked', () => {
		// First render
		const { rerender } = render( <MaybeShowMerchantFeedbackPrompt /> );

		// Click the Yes button
		const yesButton = screen.getByText( 'Yes', {
			ignore: '.a11y-speak-region',
		} );
		fireEvent.click( yesButton );

		// Expect the event to be recorded
		expect( recordEvent ).toHaveBeenCalledWith(
			'wcpay_merchant_feedback_prompt_yes_click'
		);

		// Re-render the component to verify it's no longer shown
		rerender( <MaybeShowMerchantFeedbackPrompt /> );

		// The prompt should no longer be rendered
		expect(
			screen.queryByText( 'Are you satisfied with WooPayments?' )
		).not.toBeInTheDocument();
	} );

	it( 'records event when No button is clicked', () => {
		// First render
		const { rerender } = render( <MaybeShowMerchantFeedbackPrompt /> );

		// Click the No button
		const noButton = screen.getByText( 'No', {
			ignore: '.a11y-speak-region',
		} );
		fireEvent.click( noButton );

		// Expect the event to be recorded
		expect( recordEvent ).toHaveBeenCalledWith(
			'wcpay_merchant_feedback_prompt_no_click'
		);

		// Re-render the component to verify it's no longer shown
		rerender( <MaybeShowMerchantFeedbackPrompt /> );

		// The prompt should no longer be rendered
		expect(
			screen.queryByText( 'Are you satisfied with WooPayments?' )
		).not.toBeInTheDocument();
	} );
} );
