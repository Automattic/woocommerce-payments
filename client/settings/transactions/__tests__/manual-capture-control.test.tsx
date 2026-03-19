/**
 * External dependencies
 */
import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import { userEvent as user } from 'jest-utils/user-event-timers';

/**
 * Internal dependencies
 */
import ManualCaptureControl from '../manual-capture-control';
import {
	useManualCapture,
	useCardPresentEligible,
	useStripeBilling,
} from 'wcpay/data';

jest.mock( 'wcpay/data', () => ( {
	useManualCapture: jest.fn(),
	useCardPresentEligible: jest.fn(),
	useStripeBilling: jest.fn(),
} ) );

describe( 'ManualCaptureControl', () => {
	const mockSetIsManualCaptureEnabled = jest.fn();

	beforeEach( () => {
		jest.clearAllMocks();
		( useManualCapture as jest.Mock ).mockReturnValue( [
			false,
			mockSetIsManualCaptureEnabled,
		] );
		( useCardPresentEligible as jest.Mock ).mockReturnValue( [ false ] );
		( useStripeBilling as jest.Mock ).mockReturnValue( [
			false,
			jest.fn(),
		] );
	} );

	it( 'renders the checkbox control', () => {
		render( <ManualCaptureControl /> );
		expect(
			screen.getByLabelText( 'Enable manual capture' )
		).toBeInTheDocument();
	} );

	it( 'shows simplified help text with Learn more link', () => {
		render( <ManualCaptureControl /> );
		expect(
			screen.getByText(
				/Issue an authorization on checkout and capture later/i
			)
		).toBeInTheDocument();
		const learnMoreLinks = screen.getAllByRole( 'link', {
			name: /Learn more/i,
		} );
		expect( learnMoreLinks[ 0 ] ).toHaveAttribute(
			'href',
			'https://woocommerce.com/document/woopayments/settings-guide/authorize-and-capture/'
		);
	} );

	it( 'opens modal when enabling manual capture', async () => {
		render( <ManualCaptureControl /> );

		const checkbox = screen.getByLabelText( 'Enable manual capture' );
		await user.click( checkbox );

		expect( screen.getByRole( 'dialog' ) ).toBeInTheDocument();
		expect(
			screen.getByText(
				( content, element ) =>
					element?.tagName === 'P' &&
					!! element?.textContent?.includes(
						'must be captured on the order details screen within 7 days of authorization'
					)
			)
		).toBeInTheDocument();
		const notices = screen.getAllByText(
			/Manual capture is available for card payments only.*will be disabled/i
		);
		expect( notices.length ).toBeGreaterThan( 0 );
	} );

	it( 'enables manual capture when confirmed', async () => {
		render( <ManualCaptureControl /> );

		const checkbox = screen.getByLabelText( 'Enable manual capture' );
		await user.click( checkbox );

		const confirmButton = screen.getByRole( 'button', {
			name: 'Enable manual capture',
		} );
		await user.click( confirmButton );

		await waitFor( () => {
			expect( mockSetIsManualCaptureEnabled ).toHaveBeenCalledWith(
				true
			);
		} );
	} );

	it( 'cancels modal without making changes', async () => {
		render( <ManualCaptureControl /> );

		const checkbox = screen.getByLabelText( 'Enable manual capture' );
		await user.click( checkbox );

		const cancelButton = screen.getByRole( 'button', { name: 'Cancel' } );
		await user.click( cancelButton );

		expect( mockSetIsManualCaptureEnabled ).not.toHaveBeenCalled();
	} );

	it( 'shows "Learn more" link in modal', async () => {
		render( <ManualCaptureControl /> );

		const checkbox = screen.getByLabelText( 'Enable manual capture' );
		await user.click( checkbox );

		const learnMoreLink = screen.getByText(
			'Learn more about manual capture'
		);
		expect( learnMoreLink ).toBeInTheDocument();
		expect( learnMoreLink.closest( 'a' ) ).toHaveAttribute(
			'href',
			'https://woocommerce.com/document/woopayments/settings-guide/authorize-and-capture/'
		);
	} );

	it( 'directly disables manual capture without showing modal', async () => {
		( useManualCapture as jest.Mock ).mockReturnValue( [
			true,
			mockSetIsManualCaptureEnabled,
		] );

		render( <ManualCaptureControl /> );

		const checkbox = screen.getByLabelText( 'Enable manual capture' );
		await user.click( checkbox );

		// Should not show modal, directly disable
		expect( mockSetIsManualCaptureEnabled ).toHaveBeenCalledWith( false );
		expect(
			screen.queryByText(
				/Payments must be captured within 7 days or the authorization will expire/i
			)
		).not.toBeInTheDocument();
	} );

	it( 'shows Stripe Billing conflict notice when Stripe Billing is enabled', () => {
		( useStripeBilling as jest.Mock ).mockReturnValue( [
			true,
			jest.fn(),
		] );

		render( <ManualCaptureControl /> );

		const notices = screen.getAllByText(
			'Manual capture is not available when Stripe Billing is active.'
		);
		expect( notices.length ).toBeGreaterThan( 0 );
		expect( notices[ 0 ] ).toBeInTheDocument();
	} );

	it( 'disables checkbox when Stripe Billing is enabled', () => {
		( useStripeBilling as jest.Mock ).mockReturnValue( [
			true,
			jest.fn(),
		] );

		render( <ManualCaptureControl /> );

		const checkbox = screen.getByLabelText( 'Enable manual capture' );
		expect( checkbox ).toBeDisabled();
	} );
} );
