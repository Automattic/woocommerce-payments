/**
 * External dependencies
 */
import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';

/**
 * Internal dependencies
 */
import ConfirmationScreen from '../confirmation-screen';

// Mock window.location.href assignments - JSDOM's window.location is read-only by default
// Our component's button click handlers need to set window.location.href for navigation
delete ( window as any ).location;
( window as any ).location = { href: '' };

// Mock window.scrollTo - Jest environment doesn't have this method implemented
Object.defineProperty( window, 'scrollTo', {
	value: jest.fn(),
} );

// Note: Using real ExternalLink component to test actual behavior

describe( 'ConfirmationScreen', () => {
	const baseProps = {
		disputeId: 'dp_test_123',
		bankName: 'Test Bank',
	};

	beforeEach( () => {
		jest.clearAllMocks();
		( window as any ).location.href = '';
	} );

	describe( 'Basic rendering', () => {
		it( 'renders all main elements', () => {
			render( <ConfirmationScreen { ...baseProps } /> );

			// Main title and subtitle
			expect(
				screen.getByText( 'Thanks for sharing your response!' )
			).toBeInTheDocument();
			expect(
				screen.getByText(
					"Your evidence has been sent to the cardholder's bank for review."
				)
			).toBeInTheDocument();

			// What's next section
			expect(
				screen.getByRole( 'heading', { level: 3 } )
			).toBeInTheDocument();

			// Action buttons
			expect(
				screen.getByRole( 'button', { name: 'Return to disputes' } )
			).toBeInTheDocument();
			expect(
				screen.getByRole( 'button', { name: 'View submitted dispute' } )
			).toBeInTheDocument();
		} );

		it( 'renders the success illustration with correct alt text', () => {
			render( <ConfirmationScreen { ...baseProps } /> );

			const illustration = screen.getByAltText(
				'Evidence submitted successfully'
			);
			expect( illustration ).toBeInTheDocument();
			expect( illustration ).toHaveAttribute(
				'src',
				'assets/images/dispute-evidence-submitted.svg'
			);
			expect( illustration ).toHaveClass(
				'wcpay-dispute-evidence-confirmation__illustration-image'
			);
		} );

		it( 'renders the inline notice with proper styling', () => {
			render( <ConfirmationScreen { ...baseProps } /> );

			// Target the specific notice container directly to avoid duplicate text in accessibility announcements
			const noticeContainer = document.querySelector(
				'.wcpay-dispute-evidence-confirmation__notice'
			);
			expect( noticeContainer ).toBeInTheDocument();
			expect( noticeContainer ).toHaveClass( 'wcpay-inline-notice' );
		} );
	} );

	describe( 'Bank name handling', () => {
		it( 'displays bank-specific message when bankName is provided', () => {
			render(
				<ConfirmationScreen { ...baseProps } bankName="Chase Bank" />
			);

			const notice = document.querySelector(
				'.wcpay-dispute-evidence-confirmation__notice'
			);
			expect( notice ).toHaveTextContent(
				'The outcome of this dispute will be determined by Chase Bank'
			);
		} );

		it( 'displays generic message when bankName is null', () => {
			render( <ConfirmationScreen { ...baseProps } bankName={ null } /> );

			const notice = document.querySelector(
				'.wcpay-dispute-evidence-confirmation__notice'
			);
			expect( notice ).toHaveTextContent(
				"The outcome of this dispute will be determined by the cardholder's bank"
			);
		} );

		it( 'displays generic message when bankName is empty string', () => {
			render( <ConfirmationScreen { ...baseProps } bankName="" /> );

			const notice = document.querySelector(
				'.wcpay-dispute-evidence-confirmation__notice'
			);
			expect( notice ).toHaveTextContent(
				"The outcome of this dispute will be determined by the cardholder's bank"
			);
		} );
	} );

	describe( 'Next steps content', () => {
		it( 'renders all next steps list items', () => {
			render( <ConfirmationScreen { ...baseProps } /> );

			// Check for key phrases in each list item - text spans across multiple elements
			expect(
				screen.getByText( /will review your response/ )
			).toBeInTheDocument();
			expect(
				screen.getByText( /this usually takes a few weeks/ )
			).toBeInTheDocument();
			expect(
				screen.getByText(
					/You'll be informed of any updates via email/
				)
			).toBeInTheDocument();
			expect(
				screen.getByText( /Want to know more about how disputes work/ )
			).toBeInTheDocument();
		} );

		it( 'renders internal link to disputes page', () => {
			render( <ConfirmationScreen { ...baseProps } /> );

			const disputesLink = screen.getByRole( 'link', {
				name: 'Disputes area',
			} );
			expect( disputesLink ).toBeInTheDocument();
			expect( disputesLink ).toHaveAttribute(
				'href',
				'admin.php?page=wc-admin&path=%2Fpayments%2Fdisputes'
			);
		} );

		it( 'renders external link to documentation', () => {
			render( <ConfirmationScreen { ...baseProps } /> );

			const learnMoreLink = screen.getByRole( 'link', {
				name: 'Check out our resources (opens in a new tab)',
			} );
			expect( learnMoreLink ).toBeInTheDocument();
			expect( learnMoreLink ).toHaveAttribute(
				'href',
				'https://woocommerce.com/document/payments/disputes/'
			);
			expect( learnMoreLink ).toHaveAttribute( 'target', '_blank' );
			expect( learnMoreLink ).toHaveAttribute(
				'rel',
				'external noreferrer noopener'
			);
		} );
	} );

	describe( 'Button interactions', () => {
		it( 'navigates to disputes list when "Return to transactions" is clicked', () => {
			render( <ConfirmationScreen { ...baseProps } /> );

			const returnButton = screen.getByRole( 'button', {
				name: 'Return to disputes',
			} );
			fireEvent.click( returnButton );

			expect( ( window as any ).location.href ).toBe(
				'admin.php?page=wc-admin&path=%2Fpayments%2Fdisputes'
			);
		} );

		it( 'navigates to submitted dispute when "View submitted dispute" is clicked', () => {
			render( <ConfirmationScreen { ...baseProps } /> );

			const viewButton = screen.getByRole( 'button', {
				name: 'View submitted dispute',
			} );
			fireEvent.click( viewButton );

			expect( ( window as any ).location.href ).toBe(
				'admin.php?page=wc-admin&path=%2Fpayments%2Fdisputes%2Fchallenge&id=dp_test_123'
			);
		} );

		it( 'uses correct disputeId in view submitted dispute link', () => {
			render(
				<ConfirmationScreen
					{ ...baseProps }
					disputeId="dp_different_id"
				/>
			);

			const viewButton = screen.getByRole( 'button', {
				name: 'View submitted dispute',
			} );
			fireEvent.click( viewButton );

			expect( ( window as any ).location.href ).toBe(
				'admin.php?page=wc-admin&path=%2Fpayments%2Fdisputes%2Fchallenge&id=dp_different_id'
			);
		} );
	} );
} );
