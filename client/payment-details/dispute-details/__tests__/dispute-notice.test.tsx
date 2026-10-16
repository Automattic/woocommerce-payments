/** @format */
/**
 * External dependencies
 */
import { render } from '@testing-library/react';
import React from 'react';

/**
 * Internal dependencies
 */
import DisputeNotice from '../dispute-notice';
import type { Dispute } from 'wcpay/types/disputes';
import type { Charge } from 'wcpay/types/charges';

// Mock date formatting utility
jest.mock( 'wcpay/utils/date-time', () => ( {
	formatDateTimeFromTimestamp: jest.fn(
		() => '11:59 PM on September 9, 2023'
	),
} ) );

const getBaseDispute = (): Dispute => ( {
	id: 'dp_1',
	amount: 5000,
	charge: { id: 'ch_mock' } as Charge,
	order: null,
	balance_transactions: [
		{
			amount: -5000,
			currency: 'usd',
			fee: 1500,
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
	reason: 'fraudulent',
	status: 'needs_response',
} );

describe( 'DisputeNotice - Visa Compliance', () => {
	test( 'renders Visa compliance notice with bank name', () => {
		const dispute: Dispute = {
			...getBaseDispute(),
			reason: 'noncompliant',
		};

		const { container } = render(
			<DisputeNotice
				dispute={ dispute }
				isUrgent={ true }
				paymentMethod="card"
				bankName="Chase Bank"
			/>
		);

		const notice = container.querySelector( '.dispute-notice' );
		expect( notice ).toBeInTheDocument();

		// Check for Visa-specific text
		expect( notice?.textContent ).toMatch(
			/Your customer.s bank, Chase Bank, claims this payment violates Visa.s rules/i
		);

		// Check for the $500 fee mention
		expect( notice?.textContent ).toMatch(
			/Challenging adds an additional \$500 USD/i
		);

		// Check for the refund condition
		expect( notice?.textContent ).toMatch(
			/that is only returned to you if you win/i
		);

		expect( container ).toMatchSnapshot();
	} );

	test( 'renders Visa compliance notice without bank name', () => {
		const dispute: Dispute = {
			...getBaseDispute(),
			reason: 'noncompliant',
		};

		const { container } = render(
			<DisputeNotice
				dispute={ dispute }
				isUrgent={ true }
				paymentMethod="card"
				bankName={ null }
			/>
		);

		const notice = container.querySelector( '.dispute-notice' );
		expect( notice ).toBeInTheDocument();

		// Should show generic "Your customer's bank" text
		expect( notice?.textContent ).toMatch(
			/Your customer.s bank claims this payment violates Visa.s rules/i
		);

		// Should not mention specific bank name
		expect( notice?.textContent ).not.toMatch( /Chase Bank/i );

		// Check for the $500 fee mention
		expect( notice?.textContent ).toMatch(
			/Challenging adds an additional \$500 USD/i
		);

		expect( container ).toMatchSnapshot();
	} );

	test( 'renders urgent error status for Visa compliance disputes', () => {
		const dispute: Dispute = {
			...getBaseDispute(),
			reason: 'noncompliant',
		};

		const { container } = render(
			<DisputeNotice
				dispute={ dispute }
				isUrgent={ true }
				paymentMethod="card"
				bankName="Chase Bank"
			/>
		);

		// Check that the notice has error status (urgent)
		const notice = container.querySelector( '.dispute-notice' );
		expect( notice ).toBeInTheDocument();

		expect( container ).toMatchSnapshot();
	} );

	test( 'renders deadline information correctly', () => {
		const dispute: Dispute = {
			...getBaseDispute(),
			reason: 'noncompliant',
		};

		const { container } = render(
			<DisputeNotice
				dispute={ dispute }
				isUrgent={ true }
				paymentMethod="card"
				bankName="Chase Bank"
			/>
		);

		const notice = container.querySelector( '.dispute-notice' );
		expect( notice ).toBeInTheDocument();

		// Check that the deadline is mentioned
		expect( notice?.textContent ).toMatch(
			/11:59 PM on September 9, 2023/i
		);
	} );

	test( 'does not render Visa compliance text for other dispute reasons', () => {
		const dispute: Dispute = {
			...getBaseDispute(),
			reason: 'fraudulent', // Different reason
		};

		const { container } = render(
			<DisputeNotice
				dispute={ dispute }
				isUrgent={ true }
				paymentMethod="card"
				bankName="Chase Bank"
			/>
		);

		const notice = container.querySelector( '.dispute-notice' );
		expect( notice ).toBeInTheDocument();

		// Should not show Visa-specific text
		expect( notice?.textContent ).not.toMatch( /violates Visa.s rules/i );

		expect( notice?.textContent ).not.toMatch( /additional \$500 USD/i );

		// Should show generic dispute text instead
		expect( notice?.textContent ).toMatch(
			/The cardholder claims this is an unauthorized transaction/i
		);
	} );

	test( 'includes information about accepting the dispute', () => {
		const dispute: Dispute = {
			...getBaseDispute(),
			reason: 'noncompliant',
		};

		const { container } = render(
			<DisputeNotice
				dispute={ dispute }
				isUrgent={ true }
				paymentMethod="card"
				bankName="Chase Bank"
			/>
		);

		const notice = container.querySelector( '.dispute-notice' );
		expect( notice ).toBeInTheDocument();

		// Check for text about accepting
		expect( notice?.textContent ).toMatch( /or accept it/i );

		expect( notice?.textContent ).toMatch(
			/you will forfeit the funds and pay the dispute fee/i
		);
	} );

	test( 'renders with warning status when not urgent', () => {
		const dispute: Dispute = {
			...getBaseDispute(),
			reason: 'noncompliant',
		};

		const { container } = render(
			<DisputeNotice
				dispute={ dispute }
				isUrgent={ false }
				paymentMethod="card"
				bankName="Chase Bank"
			/>
		);

		// Notice should still render with warning status
		const notice = container.querySelector( '.dispute-notice' );
		expect( notice ).toBeInTheDocument();

		expect( container ).toMatchSnapshot();
	} );
} );

describe( 'DisputeNotice - Klarna Inquiries', () => {
	test( 'renders return-specific banner for credit_not_processed Klarna inquiry', () => {
		const dispute: Dispute = {
			...getBaseDispute(),
			reason: 'credit_not_processed',
			status: 'warning_needs_response',
		};

		const { container } = render(
			<DisputeNotice
				dispute={ dispute }
				isUrgent={ true }
				paymentMethod="klarna"
				bankName={ null }
			/>
		);

		const notice = container.querySelector( '.dispute-notice' );
		expect( notice ).toBeInTheDocument();

		// Should show return-specific text
		expect( notice?.textContent ).toMatch( /reporting a return/i );
		expect( notice?.textContent ).toMatch(
			/standard part of Klarna's returns process/i
		);
		expect( notice?.textContent ).toMatch( /may escalate to a dispute/i );

		// Should include Learn more link
		const link = notice?.querySelector( 'a' );
		expect( link ).toBeInTheDocument();
		expect( link ).toHaveAttribute(
			'href',
			'https://woocommerce.com/document/woopayments/payment-methods/buy-now-pay-later/#klarna-inquiries-returns'
		);
	} );

	test( 'renders per-reason banner for fraudulent Klarna inquiry', () => {
		const dispute: Dispute = {
			...getBaseDispute(),
			reason: 'fraudulent',
			status: 'warning_needs_response',
		};

		const { container } = render(
			<DisputeNotice
				dispute={ dispute }
				isUrgent={ true }
				paymentMethod="klarna"
				bankName={ null }
			/>
		);

		const notice = container.querySelector( '.dispute-notice' );
		expect( notice ).toBeInTheDocument();

		// Should show per-reason clause
		expect( notice?.textContent ).toMatch(
			/filed an inquiry through Klarna, claiming this transaction was unauthorized/i
		);
		expect( notice?.textContent ).toMatch(
			/working it out with the customer directly or issuing a refund/i
		);

		// Should NOT show return-specific text
		expect( notice?.textContent ).not.toMatch( /reporting a return/i );
	} );

	test( 'renders fallback banner for unknown Klarna inquiry reason', () => {
		const dispute: Dispute = {
			...getBaseDispute(),
			reason: 'general',
			status: 'warning_needs_response',
		};

		const { container } = render(
			<DisputeNotice
				dispute={ dispute }
				isUrgent={ true }
				paymentMethod="klarna"
				bankName={ null }
			/>
		);

		const notice = container.querySelector( '.dispute-notice' );
		expect( notice ).toBeInTheDocument();

		// Should show fallback clause
		expect( notice?.textContent ).toMatch(
			/filed an inquiry through Klarna, regarding this transaction/i
		);
	} );

	test( 'includes deadline in Klarna inquiry banners', () => {
		const dispute: Dispute = {
			...getBaseDispute(),
			reason: 'product_not_received',
			status: 'warning_needs_response',
		};

		const { container } = render(
			<DisputeNotice
				dispute={ dispute }
				isUrgent={ true }
				paymentMethod="klarna"
				bankName={ null }
			/>
		);

		const notice = container.querySelector( '.dispute-notice' );
		expect( notice?.textContent ).toMatch(
			/11:59 PM on September 9, 2023/i
		);
	} );
} );
