/** @format */
/**
 * External dependencies
 */
import { render, screen, fireEvent } from '@testing-library/react';
import React from 'react';

/**
 * Internal dependencies
 */
import {
	NonCompliantDisputeSteps,
	NotDefendableInquirySteps,
} from '../dispute-steps';
import type { Dispute } from 'wcpay/types/disputes';
import type { Charge, ChargeBillingDetails } from 'wcpay/types/charges';

// Mock date formatting utility
jest.mock( 'wcpay/utils/date-time', () => ( {
	formatDateTimeFromTimestamp: jest.fn( () => 'Aug 5, 2026 11:59 PM' ),
} ) );

// Mock multi-currency
jest.mock( 'multi-currency/interface/functions', () => ( {
	formatExplicitCurrency: jest.fn( () => '$50.00 USD' ),
} ) );

declare const global: {
	wcpaySettings: {
		storeName: string;
	};
};

const getBaseInquiryDispute = (): Dispute => ( {
	id: 'dp_klarna_1',
	amount: 5000,
	charge: { id: 'ch_mock' } as Charge,
	order: null,
	balance_transactions: [
		{
			amount: -5000,
			currency: 'usd',
			fee: 0,
			reporting_category: 'dispute',
		},
	],
	created: 1693453017,
	currency: 'usd',
	evidence: {},
	evidence_details: {
		due_by: 1694303999,
		has_evidence: false,
		past_due: false,
		submission_count: 0,
	},
	issuer_evidence: null,
	metadata: {},
	payment_intent: 'pi_1',
	reason: 'credit_not_processed',
	status: 'warning_needs_response',
} );

const getBaseCustomer = (): ChargeBillingDetails => ( {
	name: 'Test Customer',
	email: 'test@example.com',
	phone: null,
	address: {
		city: 'Test City',
		country: 'US',
		line1: '123 Test St',
		line2: '',
		postal_code: '12345',
		state: 'CA',
	},
} );

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
				selector: '.dispute-step-item__name',
			} )
		).toBeInTheDocument();

		// Check step description - use container query to avoid text matching issues
		const descriptions = container.querySelectorAll(
			'.dispute-step-item__description'
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
				selector: '.dispute-step-item__name',
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
		const icons = container.querySelectorAll( '.dispute-step-item__icon' );
		expect( icons ).toHaveLength( 2 );
	} );

	test( 'renders two dispute steps', () => {
		const { container } = render( <NonCompliantDisputeSteps /> );

		const steps = container.querySelectorAll( '.dispute-step-item' );
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

describe( 'NotDefendableInquirySteps', () => {
	beforeEach( () => {
		global.wcpaySettings = {
			storeName: 'Test Store',
		};
	} );

	test( 'renders 3 steps with return language for credit_not_processed', () => {
		const dispute = getBaseInquiryDispute();
		const customer = getBaseCustomer();

		const { container } = render(
			<NotDefendableInquirySteps
				dispute={ dispute }
				customer={ customer }
				chargeCreated={ 1693453017 }
				bankName="Klarna"
			/>
		);

		// Expand the accordion
		fireEvent.click( screen.getByText( /Steps you can take/i ) );

		const steps = container.querySelectorAll( '.dispute-step-item' );
		expect( steps ).toHaveLength( 3 );

		// Step 1: return-specific description
		expect(
			screen.getByText(
				/Reach out to the customer to check if they're returning the item/i
			)
		).toBeInTheDocument();

		// Step 3: return-specific step
		expect(
			screen.getByText( /Respond when the inquiry becomes a dispute/i, {
				selector: '.dispute-step-item__name',
			} )
		).toBeInTheDocument();

		expect(
			screen.getByText(
				/the inquiry may escalate to a dispute after 21 days/i
			)
		).toBeInTheDocument();
	} );

	test( 'renders 2 steps with generic language for non-return reasons', () => {
		const dispute: Dispute = {
			...getBaseInquiryDispute(),
			reason: 'fraudulent',
		};
		const customer = getBaseCustomer();

		const { container } = render(
			<NotDefendableInquirySteps
				dispute={ dispute }
				customer={ customer }
				chargeCreated={ 1693453017 }
				bankName="Klarna"
			/>
		);

		// Expand the accordion
		fireEvent.click( screen.getByText( /Steps you can take/i ) );

		const steps = container.querySelectorAll( '.dispute-step-item' );
		expect( steps ).toHaveLength( 2 );

		// Step 1: generic description
		expect(
			screen.getByText(
				/Identify the issue and work towards a resolution where possible/i
			)
		).toBeInTheDocument();

		// Step 2: generic description
		expect(
			screen.getByText(
				/If appropriate, issue a refund to resolve the inquiry before the deadline/i
			)
		).toBeInTheDocument();

		// Step 3 should NOT be present
		expect(
			screen.queryByText( /Respond when the inquiry becomes a dispute/i )
		).not.toBeInTheDocument();
	} );

	test( 'renders inquiry notice instead of dispute notice', () => {
		const dispute = getBaseInquiryDispute();
		const customer = getBaseCustomer();

		const { container } = render(
			<NotDefendableInquirySteps
				dispute={ dispute }
				customer={ customer }
				chargeCreated={ 1693453017 }
				bankName="Klarna"
			/>
		);

		// Expand the accordion to access the notice
		fireEvent.click( screen.getByText( /Steps you can take/i ) );

		const notice = container.querySelector(
			'.dispute-steps__notice-content'
		);
		expect( notice ).toBeInTheDocument();

		// Should say "inquiry" not "dispute"
		expect( notice?.textContent ).toMatch(
			/The outcome of this inquiry will be determined by Klarna/i
		);
		expect( notice?.textContent ).not.toMatch(
			/The outcome of this dispute/i
		);
	} );

	test( 'renders Learn more link only for return case', () => {
		const dispute = getBaseInquiryDispute();
		const customer = getBaseCustomer();

		const { container } = render(
			<NotDefendableInquirySteps
				dispute={ dispute }
				customer={ customer }
				chargeCreated={ 1693453017 }
				bankName="Klarna"
			/>
		);

		// Expand the accordion
		fireEvent.click( screen.getByText( /Steps you can take/i ) );

		const learnMoreLink = container.querySelector(
			'a[href*="klarna-inquiries-returns"]'
		);
		expect( learnMoreLink ).toBeInTheDocument();
	} );

	test( 'does not render Learn more link for non-return reasons', () => {
		const dispute: Dispute = {
			...getBaseInquiryDispute(),
			reason: 'fraudulent',
		};
		const customer = getBaseCustomer();

		const { container } = render(
			<NotDefendableInquirySteps
				dispute={ dispute }
				customer={ customer }
				chargeCreated={ 1693453017 }
				bankName="Klarna"
			/>
		);

		const learnMoreLink = container.querySelector(
			'a[href*="klarna-inquiries-returns"]'
		);
		expect( learnMoreLink ).not.toBeInTheDocument();
	} );
} );
