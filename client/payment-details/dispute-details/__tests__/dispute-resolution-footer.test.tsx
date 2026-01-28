/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import DisputeResolutionFooter from '../dispute-resolution-footer';
import type { Dispute } from 'wcpay/types/disputes';

// Mock date formatting utility
jest.mock( 'wcpay/utils/date-time', () => ( {
	formatDateTimeFromTimestamp: jest.fn(
		() => '11:59 PM on September 9, 2023'
	),
} ) );

// Mock Tracks
jest.mock( 'tracks', () => ( {
	recordEvent: jest.fn(),
} ) );

// Mock getAdminUrl
jest.mock( 'wcpay/utils', () => ( {
	getAdminUrl: jest.fn( ( params ) => {
		const query = new URLSearchParams( params ).toString();
		return `admin.php?${ query }`;
	} ),
} ) );

// Mock dispute utils
jest.mock( 'wcpay/disputes/utils', () => ( {
	getDisputeFeeFormatted: jest.fn( () => '$15.00' ),
	isVisaComplianceDispute: jest.fn( ( dispute ) => {
		if ( ! dispute ) {
			return false;
		}
		return (
			dispute.reason === 'noncompliant' ||
			( dispute?.enhanced_eligibility_types || [] ).includes(
				'visa_compliance'
			)
		);
	} ),
} ) );

const getBaseDispute = ( overrides = {} ): Dispute => ( {
	id: 'dp_test123',
	amount: 5000,
	charge: 'ch_mock',
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
	metadata: {
		__evidence_submitted_at: '1693453017',
		__dispute_closed_at: '1693453017',
	},
	payment_intent: 'pi_1',
	reason: 'fraudulent',
	status: 'needs_response',
	enhanced_eligibility_types: [],
	...overrides,
} );

describe( 'DisputeResolutionFooter - Under Review Status', () => {
	it( 'renders under review footer with bank name for regular disputes', () => {
		const dispute = getBaseDispute( { status: 'under_review' } );

		render(
			<DisputeResolutionFooter
				dispute={ dispute }
				bankName="Chase Bank"
			/>
		);

		expect(
			screen.getByText(
				/The customer's bank, Chase Bank, is currently reviewing/i
			)
		).toBeInTheDocument();
		expect(
			screen.getByText( /11:59 PM on September 9, 2023/i )
		).toBeInTheDocument();
	} );

	it( 'renders under review footer without bank name for regular disputes', () => {
		const dispute = getBaseDispute( { status: 'under_review' } );

		render(
			<DisputeResolutionFooter dispute={ dispute } bankName={ null } />
		);

		expect(
			screen.getByText( /The customer's bank is currently reviewing/i )
		).toBeInTheDocument();
	} );

	it( 'renders Visa-specific under review message for noncompliant reason', () => {
		const dispute = getBaseDispute( {
			status: 'under_review',
			reason: 'noncompliant',
		} );

		render(
			<DisputeResolutionFooter
				dispute={ dispute }
				bankName="Chase Bank"
			/>
		);

		expect(
			screen.getByText( /Visa is currently reviewing/i )
		).toBeInTheDocument();
		expect( screen.queryByText( /Chase Bank/i ) ).not.toBeInTheDocument();
	} );

	it( 'renders Visa-specific under review message for visa_compliance enhanced_eligibility_types', () => {
		const dispute = getBaseDispute( {
			status: 'under_review',
			reason: 'fraudulent',
			enhanced_eligibility_types: [ 'visa_compliance' ],
		} );

		render(
			<DisputeResolutionFooter
				dispute={ dispute }
				bankName="Chase Bank"
			/>
		);

		expect(
			screen.getByText( /Visa is currently reviewing/i )
		).toBeInTheDocument();
		expect( screen.queryByText( /Chase Bank/i ) ).not.toBeInTheDocument();
	} );

	it( 'renders link to view submitted evidence', () => {
		const dispute = getBaseDispute( { status: 'under_review' } );

		render(
			<DisputeResolutionFooter dispute={ dispute } bankName={ null } />
		);

		const link = screen.getByRole( 'button', {
			name: /View submitted evidence/i,
		} );
		expect( link ).toBeInTheDocument();
	} );
} );

describe( 'DisputeResolutionFooter - Won Status', () => {
	it( 'renders won footer with bank name for regular disputes', () => {
		const dispute = getBaseDispute( { status: 'won' } );

		render(
			<DisputeResolutionFooter
				dispute={ dispute }
				bankName="Chase Bank"
			/>
		);

		expect(
			screen.getByText(
				/Good news — you've won this dispute! The customer's bank, Chase Bank, reached this decision/i
			)
		).toBeInTheDocument();
	} );

	it( 'renders won footer without bank name for regular disputes', () => {
		const dispute = getBaseDispute( { status: 'won' } );

		render(
			<DisputeResolutionFooter dispute={ dispute } bankName={ null } />
		);

		expect(
			screen.getByText(
				/Good news — you've won this dispute! The customer's bank reached this decision/i
			)
		).toBeInTheDocument();
	} );

	it( 'renders Visa-specific won message for noncompliant reason', () => {
		const dispute = getBaseDispute( {
			status: 'won',
			reason: 'noncompliant',
		} );

		render(
			<DisputeResolutionFooter
				dispute={ dispute }
				bankName="Chase Bank"
			/>
		);

		expect(
			screen.getByText(
				/Good news — you've won this dispute! Visa reached this decision/i
			)
		).toBeInTheDocument();
		expect( screen.queryByText( /Chase Bank/i ) ).not.toBeInTheDocument();
	} );

	it( 'renders Visa-specific won message for visa_compliance enhanced_eligibility_types', () => {
		const dispute = getBaseDispute( {
			status: 'won',
			reason: 'product_not_received',
			enhanced_eligibility_types: [ 'visa_compliance' ],
		} );

		render(
			<DisputeResolutionFooter
				dispute={ dispute }
				bankName="Wells Fargo"
			/>
		);

		expect(
			screen.getByText(
				/Good news — you've won this dispute! Visa reached this decision/i
			)
		).toBeInTheDocument();
		expect( screen.queryByText( /Wells Fargo/i ) ).not.toBeInTheDocument();
	} );

	it( 'renders link to view dispute details', () => {
		const dispute = getBaseDispute( { status: 'won' } );

		render(
			<DisputeResolutionFooter dispute={ dispute } bankName={ null } />
		);

		const link = screen.getByRole( 'button', {
			name: /View dispute details/i,
		} );
		expect( link ).toBeInTheDocument();
	} );
} );

describe( 'DisputeResolutionFooter - Lost Status', () => {
	it( 'renders lost footer with bank name for submitted regular disputes', () => {
		const dispute = getBaseDispute( {
			status: 'lost',
			metadata: {
				__evidence_submitted_at: '1693453017',
				__dispute_closed_at: '1693453017',
			},
		} );

		render(
			<DisputeResolutionFooter
				dispute={ dispute }
				bankName="Chase Bank"
			/>
		);

		expect(
			screen.getByText(
				/Unfortunately, you've lost this dispute\. The customer's bank, Chase Bank, reached this decision/i
			)
		).toBeInTheDocument();
	} );

	it( 'renders lost footer without bank name for submitted regular disputes', () => {
		const dispute = getBaseDispute( {
			status: 'lost',
			metadata: {
				__evidence_submitted_at: '1693453017',
				__dispute_closed_at: '1693453017',
			},
		} );

		render(
			<DisputeResolutionFooter dispute={ dispute } bankName={ null } />
		);

		expect(
			screen.getByText(
				/Unfortunately, you've lost this dispute\. The customer's bank reached this decision/i
			)
		).toBeInTheDocument();
	} );

	it( 'renders Visa-specific lost message for noncompliant reason', () => {
		const dispute = getBaseDispute( {
			status: 'lost',
			reason: 'noncompliant',
			metadata: {
				__evidence_submitted_at: '1693453017',
				__dispute_closed_at: '1693453017',
			},
		} );

		render(
			<DisputeResolutionFooter
				dispute={ dispute }
				bankName="Chase Bank"
			/>
		);

		expect(
			screen.getByText(
				/Unfortunately, you've lost this dispute\. Visa reached this decision/i
			)
		).toBeInTheDocument();
		expect( screen.queryByText( /Chase Bank/i ) ).not.toBeInTheDocument();
	} );

	it( 'renders Visa-specific lost message for visa_compliance enhanced_eligibility_types', () => {
		const dispute = getBaseDispute( {
			status: 'lost',
			reason: 'duplicate',
			enhanced_eligibility_types: [ 'visa_compliance' ],
			metadata: {
				__evidence_submitted_at: '1693453017',
				__dispute_closed_at: '1693453017',
			},
		} );

		render(
			<DisputeResolutionFooter
				dispute={ dispute }
				bankName="Bank of America"
			/>
		);

		expect(
			screen.getByText(
				/Unfortunately, you've lost this dispute\. Visa reached this decision/i
			)
		).toBeInTheDocument();
		expect(
			screen.queryByText( /Bank of America/i )
		).not.toBeInTheDocument();
	} );

	it( 'renders accepted dispute message', () => {
		const dispute = getBaseDispute( {
			status: 'lost',
			metadata: {
				__dispute_closed_at: '1693453017',
				__closed_by_merchant: '1',
			},
		} );

		render(
			<DisputeResolutionFooter dispute={ dispute } bankName={ null } />
		);

		expect(
			screen.getByText( /You accepted this dispute/i )
		).toBeInTheDocument();
	} );

	it( 'renders non-response message for lost disputes without submission', () => {
		const dispute = getBaseDispute( {
			status: 'lost',
			metadata: {
				__dispute_closed_at: '1693453017',
			},
		} );

		render(
			<DisputeResolutionFooter dispute={ dispute } bankName={ null } />
		);

		expect(
			screen.getByText( /This dispute was lost.*due to non-response/i )
		).toBeInTheDocument();
	} );

	it( 'includes dispute fee information', () => {
		const dispute = getBaseDispute( {
			status: 'lost',
			metadata: {
				__evidence_submitted_at: '1693453017',
				__dispute_closed_at: '1693453017',
			},
		} );

		render(
			<DisputeResolutionFooter dispute={ dispute } bankName={ null } />
		);

		expect( screen.getByText( /\$15\.00 fee/i ) ).toBeInTheDocument();
	} );

	it( 'renders link to view dispute details for submitted disputes', () => {
		const dispute = getBaseDispute( {
			status: 'lost',
			metadata: {
				__evidence_submitted_at: '1693453017',
				__dispute_closed_at: '1693453017',
			},
		} );

		render(
			<DisputeResolutionFooter dispute={ dispute } bankName={ null } />
		);

		const link = screen.getByRole( 'button', {
			name: /View dispute details/i,
		} );
		expect( link ).toBeInTheDocument();
	} );

	it( 'does not render link for non-submitted disputes', () => {
		const dispute = getBaseDispute( {
			status: 'lost',
			metadata: {
				__dispute_closed_at: '1693453017',
			},
		} );

		render(
			<DisputeResolutionFooter dispute={ dispute } bankName={ null } />
		);

		const link = screen.queryByRole( 'button', {
			name: /View dispute details/i,
		} );
		expect( link ).not.toBeInTheDocument();
	} );
} );

describe( 'DisputeResolutionFooter - Inquiry Statuses', () => {
	it( 'renders inquiry under review footer with bank name', () => {
		const dispute = getBaseDispute( {
			status: 'warning_under_review',
		} );

		render(
			<DisputeResolutionFooter
				dispute={ dispute }
				bankName="Chase Bank"
			/>
		);

		expect(
			screen.getByText( /You submitted evidence for this inquiry on/i )
		).toBeInTheDocument();
		expect( screen.getByText( /Chase Bank/i ) ).toBeInTheDocument();
		expect(
			screen.getByText( /is reviewing the case/i )
		).toBeInTheDocument();
	} );

	it( 'renders inquiry under review footer without bank name', () => {
		const dispute = getBaseDispute( {
			status: 'warning_under_review',
		} );

		render(
			<DisputeResolutionFooter dispute={ dispute } bankName={ null } />
		);

		expect(
			screen.getByText( /You submitted evidence for this inquiry on/i )
		).toBeInTheDocument();
		expect( screen.getByText( /cardholder/i ) ).toBeInTheDocument();
		expect(
			screen.getByText( /is reviewing the case/i )
		).toBeInTheDocument();
	} );

	it( 'renders inquiry closed footer', () => {
		const dispute = getBaseDispute( {
			status: 'warning_closed',
			metadata: {
				__evidence_submitted_at: '1693453017',
				__dispute_closed_at: '1693453017',
			},
		} );

		render(
			<DisputeResolutionFooter dispute={ dispute } bankName={ null } />
		);

		expect(
			screen.getByText( /This inquiry was closed on/i )
		).toBeInTheDocument();
	} );
} );

describe( 'DisputeResolutionFooter - Edge Cases', () => {
	it( 'returns null for unhandled status', () => {
		const dispute = getBaseDispute( {
			status: 'needs_response',
		} );

		const { container } = render(
			<DisputeResolutionFooter dispute={ dispute } bankName={ null } />
		);

		expect( container.firstChild ).toBeNull();
	} );

	it( 'handles multiple enhanced_eligibility_types', () => {
		const dispute = getBaseDispute( {
			status: 'won',
			reason: 'fraudulent',
			enhanced_eligibility_types: [
				'early_fraud_warning',
				'visa_compliance',
			],
		} );

		render(
			<DisputeResolutionFooter
				dispute={ dispute }
				bankName="Chase Bank"
			/>
		);

		// Should still show Visa text when visa_compliance is present
		expect(
			screen.getByText( /Visa reached this decision/i )
		).toBeInTheDocument();
	} );

	it( 'does not treat non-visa disputes as Visa compliance', () => {
		const dispute = getBaseDispute( {
			status: 'under_review',
			reason: 'fraudulent',
			enhanced_eligibility_types: [],
		} );

		render(
			<DisputeResolutionFooter
				dispute={ dispute }
				bankName="Chase Bank"
			/>
		);

		expect(
			screen.queryByText( /Visa is currently reviewing/i )
		).not.toBeInTheDocument();
		expect(
			screen.getByText( /The customer's bank, Chase Bank/i )
		).toBeInTheDocument();
	} );
} );
