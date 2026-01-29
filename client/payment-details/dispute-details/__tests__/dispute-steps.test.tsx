/** @format */
/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';
import React from 'react';

/**
 * Internal dependencies
 */
import { NonCompliantDisputeSteps } from '../dispute-steps';

describe( 'NonCompliantDisputeSteps', () => {
	test( 'renders the Visa compliance dispute steps', () => {
		const { container } = render( <NonCompliantDisputeSteps /> );

		// Check for accordion title
		expect(
			screen.getByText( /Steps you can take/i, {
				selector: '.wcpay-accordion__title-content',
			} )
		).toBeInTheDocument();

		// Check for subtitle
		expect(
			screen.getByText(
				/We recommend reviewing your options before responding by the deadline/i
			)
		).toBeInTheDocument();

		expect( container ).toMatchSnapshot();
	} );

	test( 'renders "Accepting the dispute" step', () => {
		const { container } = render( <NonCompliantDisputeSteps /> );

		// Check step title
		expect(
			screen.getByText( /Accepting the dispute/i, {
				selector: '.dispute-steps__item-name',
			} )
		).toBeInTheDocument();

		// Check step description - use container query to avoid text matching issues
		const descriptions = container.querySelectorAll(
			'.dispute-steps__item-description'
		);
		const acceptDescription = Array.from( descriptions ).find( ( el ) =>
			el.textContent?.includes( 'forfeit the funds' )
		);
		expect( acceptDescription?.textContent ).toMatch(
			/Accepting the dispute means you’ll forfeit the funds, pay the standard dispute fee, and avoid the \$500 USD Visa network fee./i
		);

		// Check for Learn more link
		const learnMoreLinks = screen.getAllByRole( 'link', {
			name: /Learn more/i,
		} );
		expect( learnMoreLinks[ 0 ] ).toHaveAttribute(
			'href',
			'https://woocommerce.com/document/woopayments/fraud-and-disputes/managing-disputes/#visa-compliance-disputes'
		);
	} );

	test( 'renders "Challenge the dispute" step', () => {
		render( <NonCompliantDisputeSteps /> );

		// Check step title
		expect(
			screen.getByText( /Challenge the dispute/i, {
				selector: '.dispute-steps__item-name',
			} )
		).toBeInTheDocument();

		// Check step description mentions $500 fee
		expect(
			screen.getByText(
				'Challenging the dispute will incur a $500 USD Visa network fee, which is charged when you submit evidence. This fee will be refunded if you win the dispute.'
			)
		).toBeInTheDocument();
	} );

	test( 'renders Learn more links for both steps', () => {
		render( <NonCompliantDisputeSteps /> );

		const learnMoreLinks = screen.getAllByRole( 'link', {
			name: /Learn more/i,
		} );

		// Should have 2 Learn more links
		expect( learnMoreLinks ).toHaveLength( 2 );

		// Both should point to the same documentation
		learnMoreLinks.forEach( ( link ) => {
			expect( link ).toHaveAttribute(
				'href',
				'https://woocommerce.com/document/woopayments/fraud-and-disputes/managing-disputes/#visa-compliance-disputes'
			);
			expect( link ).toHaveAttribute( 'target', '_blank' );
			expect( link ).toHaveAttribute( 'rel', 'noopener noreferrer' );
		} );
	} );

	test( 'renders Visa-specific notice at the bottom', () => {
		const { container } = render( <NonCompliantDisputeSteps /> );

		// Check for the notice container
		const notice = container.querySelector(
			'.dispute-steps__notice-content'
		);
		expect( notice ).toBeInTheDocument();

		// Check for the notice text - use container.textContent to avoid multiple element issues
		expect( notice?.textContent ).toMatch(
			/The outcome of this dispute will be determined by Visa/i
		);

		// Check for disclaimer
		expect( notice?.textContent ).toMatch(
			/WooPayments has no influence over the decision and is not liable for any chargebacks/i
		);
	} );

	test( 'renders correct icons for each step', () => {
		const { container } = render( <NonCompliantDisputeSteps /> );

		// Check that icons are rendered
		const icons = container.querySelectorAll( '.dispute-steps__item-icon' );
		expect( icons ).toHaveLength( 2 );
	} );

	test( 'renders two dispute steps', () => {
		const { container } = render( <NonCompliantDisputeSteps /> );

		const steps = container.querySelectorAll( '.dispute-steps__item' );
		expect( steps ).toHaveLength( 2 );
	} );

	test( 'renders info notice with correct status', () => {
		const { container } = render( <NonCompliantDisputeSteps /> );

		const notice = container.querySelector(
			'.dispute-steps__notice-content'
		);
		expect( notice ).toBeInTheDocument();
	} );

	test( 'does not render email customer action', () => {
		render( <NonCompliantDisputeSteps /> );

		// Should not have "Email customer" button (unlike regular DisputeSteps)
		expect(
			screen.queryByRole( 'button', { name: /Email customer/i } )
		).not.toBeInTheDocument();
	} );

	test( 'does not render withdrawal step', () => {
		render( <NonCompliantDisputeSteps /> );

		// Should not have "Ask for the dispute to be withdrawn" step (unlike regular DisputeSteps)
		expect(
			screen.queryByText( /Ask for the dispute to be withdrawn/i )
		).not.toBeInTheDocument();
	} );

	test( 'renders with correct structure', () => {
		const { container } = render( <NonCompliantDisputeSteps /> );

		// Check for main container
		expect(
			container.querySelector( '.dispute-steps' )
		).toBeInTheDocument();

		// Check for accordion
		expect(
			container.querySelector( '.wcpay-accordion' )
		).toBeInTheDocument();

		// Check for steps container
		expect(
			container.querySelector( '.dispute-steps__items' )
		).toBeInTheDocument();

		// Check for notice container
		expect(
			container.querySelector( '.dispute-steps__notice' )
		).toBeInTheDocument();
	} );
} );
