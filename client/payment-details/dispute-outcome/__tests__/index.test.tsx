/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import DisputeOutcomeView from '../index';
import { getExpectedFieldStatus } from 'wcpay/disputes/new-evidence/evidence-field-status';
import { recordEvent } from 'wcpay/tracks';
import { _resetOutcomeViewTrackingForTests } from '../tracks';
import type { ChargeDispute } from 'wcpay/types/charges';

jest.mock( 'wcpay/tracks', () => ( {
	recordEvent: jest.fn(),
} ) );

const mockRecordEvent = recordEvent as jest.MockedFunction<
	typeof recordEvent
>;

beforeEach( () => {
	mockRecordEvent.mockClear();
	// De-dup memory is module-scoped, so clear it between cases.
	_resetOutcomeViewTrackingForTests();
} );

const buildDispute = (
	overrides: Partial< ChargeDispute > = {}
): ChargeDispute =>
	( {
		id: 'dp_test',
		amount: 2000,
		currency: 'usd',
		charge: 'ch_test',
		order: null,
		balance_transactions: [],
		created: 1693453017,
		evidence: {
			shipping_documentation: 'tracked',
			shipping_address: '123 Main St',
		},
		evidence_details: {
			due_by: 0,
			has_evidence: false,
			past_due: false,
			submission_count: 0,
		},
		issuer_evidence: null,
		metadata: {},
		payment_intent: 'pi_test',
		reason: 'product_unacceptable',
		status: 'lost',
		...overrides,
	} as ChargeDispute );

describe( 'DisputeOutcomeView', () => {
	it( 'renders the "Evidence Submitted" section heading', () => {
		// Set a real product type so the helper produces matrix-derived
		// rows alongside the universal cover letter row, mirroring the
		// usual production shape.
		render(
			<DisputeOutcomeView
				dispute={ buildDispute( {
					metadata: { __product_type: 'physical_product' },
				} ) }
			/>
		);

		expect(
			screen.getByRole( 'heading', { name: 'Evidence Submitted' } )
		).toBeInTheDocument();
	} );

	it( 'renders a list item per field returned by the helper', () => {
		const dispute = buildDispute( {
			metadata: { __product_type: 'physical_product' },
		} );
		const expected = getExpectedFieldStatus(
			dispute.reason,
			'physical_product',
			dispute.evidence
		);

		render( <DisputeOutcomeView dispute={ dispute } /> );

		expect( screen.getAllByRole( 'listitem' ) ).toHaveLength(
			expected.length
		);
		// Physical-product-only field; presence proves the resolved type reached the helper.
		expect(
			screen.getByText( /Shipping documentation/i )
		).toBeInTheDocument();
	} );

	it( 'prefers metadata.__product_type over order.suggested_product_type', () => {
		const dispute = buildDispute( {
			metadata: { __product_type: 'physical_product' },
			order: {
				id: 1,
				number: '1',
				url: '',
				customer_url: null,
				customer_email: null,
				customer_name: null,
				ip_address: '127.0.0.1',
				suggested_product_type: 'digital_product_or_service',
			},
		} );

		render( <DisputeOutcomeView dispute={ dispute } /> );

		// Physical-product-only field; would be absent if the digital fallback had won.
		expect(
			screen.getByText( /Shipping documentation/i )
		).toBeInTheDocument();
	} );

	it( 'falls back to order.suggested_product_type when metadata is empty', () => {
		const dispute = buildDispute( {
			metadata: {},
			order: {
				id: 1,
				number: '1',
				url: '',
				customer_url: null,
				customer_email: null,
				customer_name: null,
				ip_address: '127.0.0.1',
				suggested_product_type: 'physical_product',
			},
		} );

		render( <DisputeOutcomeView dispute={ dispute } /> );

		expect(
			screen.getByText( /Shipping documentation/i )
		).toBeInTheDocument();
	} );

	it( 'renders only the cover letter row when no product type is available', () => {
		// With neither `metadata.__product_type` nor
		// `order.suggested_product_type`, the helper still emits the
		// universal cover letter row (Catherine's request: it's
		// merchant-actionable on every dispute), so the section renders
		// with that single row only.
		render(
			<DisputeOutcomeView
				dispute={ buildDispute( { metadata: {}, order: null } ) }
			/>
		);

		expect(
			screen.getByRole( 'heading', { name: 'Evidence Submitted' } )
		).toBeInTheDocument();
		const items = screen.getAllByRole( 'listitem' );
		expect( items ).toHaveLength( 1 );
		expect( items[ 0 ] ).toHaveTextContent( /Cover letter/ );
	} );

	describe( 'tracks wcpay_dispute_outcome_viewed', () => {
		it( 'records once on mount with base dispute properties', () => {
			render(
				<DisputeOutcomeView
					dispute={ buildDispute( {
						metadata: { __product_type: 'physical_product' },
					} ) }
				/>
			);

			expect( mockRecordEvent ).toHaveBeenCalledTimes( 1 );
			expect( mockRecordEvent ).toHaveBeenCalledWith(
				'wcpay_dispute_outcome_viewed',
				{
					dispute_id: 'dp_test',
					dispute_status: 'lost',
					dispute_reason: 'product_unacceptable',
					product_type: 'physical_product',
				}
			);
		} );

		it( 'does not re-fire when the component re-renders', () => {
			const dispute = buildDispute( {
				metadata: { __product_type: 'physical_product' },
			} );
			const { rerender } = render(
				<DisputeOutcomeView dispute={ dispute } />
			);

			rerender( <DisputeOutcomeView dispute={ dispute } /> );
			rerender( <DisputeOutcomeView dispute={ dispute } /> );

			expect( mockRecordEvent ).toHaveBeenCalledTimes( 1 );
		} );

		it( 'does not re-fire when a new dispute object has the same id', () => {
			// The effect depends on the `dispute` object reference, so a new
			// object with the same id (as the loading lifecycle produces)
			// re-invokes it. The module-scoped Set keyed by id, not React's
			// reference check, is what keeps this to a single event.
			const first = buildDispute( {
				metadata: { __product_type: 'physical_product' },
			} );
			const second = buildDispute( {
				metadata: { __product_type: 'physical_product' },
			} );

			const { rerender } = render(
				<DisputeOutcomeView dispute={ first } />
			);
			rerender( <DisputeOutcomeView dispute={ second } /> );

			expect( mockRecordEvent ).toHaveBeenCalledTimes( 1 );
		} );

		it( 'does not re-fire when the component remounts for the same dispute', () => {
			// The payment-details loading lifecycle remounts this component
			// several times per view. Module-scoped de-dup must survive a full
			// unmount/remount so the view is recorded once, not once per mount.
			const dispute = buildDispute( {
				metadata: { __product_type: 'physical_product' },
			} );

			const first = render( <DisputeOutcomeView dispute={ dispute } /> );
			first.unmount();
			const second = render( <DisputeOutcomeView dispute={ dispute } /> );
			second.unmount();
			render( <DisputeOutcomeView dispute={ dispute } /> );

			expect( mockRecordEvent ).toHaveBeenCalledTimes( 1 );
		} );

		it( 'does not fire when dispute.id is missing', () => {
			// During loading the component can mount before the id resolves;
			// a view must never be recorded without a dispute_id.
			render(
				<DisputeOutcomeView dispute={ buildDispute( { id: '' } ) } />
			);

			expect( mockRecordEvent ).not.toHaveBeenCalled();
		} );

		it( 'fires again when dispute.id changes between renders (SPA in-place swap)', () => {
			// A different dispute.id on an in-place prop swap must re-fire.
			const first = buildDispute( {
				id: 'dp_first',
				metadata: { __product_type: 'physical_product' },
			} );
			const second = buildDispute( {
				id: 'dp_second',
				metadata: { __product_type: 'digital_product_or_service' },
				status: 'won',
				reason: 'fraudulent',
			} );

			const { rerender } = render(
				<DisputeOutcomeView dispute={ first } />
			);
			rerender( <DisputeOutcomeView dispute={ second } /> );

			expect( mockRecordEvent ).toHaveBeenCalledTimes( 2 );
			expect( mockRecordEvent ).toHaveBeenNthCalledWith(
				1,
				'wcpay_dispute_outcome_viewed',
				expect.objectContaining( {
					dispute_id: 'dp_first',
					dispute_status: 'lost',
					product_type: 'physical_product',
				} )
			);
			expect( mockRecordEvent ).toHaveBeenNthCalledWith(
				2,
				'wcpay_dispute_outcome_viewed',
				expect.objectContaining( {
					dispute_id: 'dp_second',
					dispute_status: 'won',
					product_type: 'digital_product_or_service',
				} )
			);
		} );

		it( 'fires for warning_closed inquiries too', () => {
			render(
				<DisputeOutcomeView
					dispute={ buildDispute( { status: 'warning_closed' } ) }
				/>
			);

			expect( mockRecordEvent ).toHaveBeenCalledTimes( 1 );
			expect( mockRecordEvent ).toHaveBeenCalledWith(
				'wcpay_dispute_outcome_viewed',
				expect.objectContaining( { dispute_status: 'warning_closed' } )
			);
		} );

		it( 'omits product_type when no product type is available', () => {
			// No product type resolves, so the helper drops the key entirely.
			render(
				<DisputeOutcomeView
					dispute={ buildDispute( { metadata: {}, order: null } ) }
				/>
			);

			expect( mockRecordEvent ).toHaveBeenCalledTimes( 1 );
			const [ eventName, payload ] = mockRecordEvent.mock.calls[ 0 ];
			expect( eventName ).toBe( 'wcpay_dispute_outcome_viewed' );
			expect( payload ).not.toHaveProperty( 'product_type' );
			expect( payload ).toEqual( {
				dispute_id: 'dp_test',
				dispute_status: 'lost',
				dispute_reason: 'product_unacceptable',
			} );
		} );
	} );

	describe( 'optional-missing collapse by status', () => {
		// `fraudulent × physical_product` with rich evidence yields a row
		// shape that includes optional_missing entries (receipt,
		// refund_policy) alongside provided rows. Mirrors the
		// `wonFraudulentPhysical` fixture without rebuilding it.
		const collapsibleDispute = (
			status: 'won' | 'lost' | 'warning_closed'
		) =>
			buildDispute( {
				status,
				reason: 'fraudulent',
				metadata: { __product_type: 'physical_product' },
				evidence: {
					customer_communication: 'present',
					shipping_documentation: 'present',
					shipping_address: 'present',
					shipping_tracking_number: 'present',
					shipping_date: '2026-04-15',
					customer_signature: 'present',
				},
			} );

		// Locate the disclosure via its visible summary copy + walk up
		// to <details>, rather than relying on the implicit role
		// mapping; mirrors what a user can see on the page.
		const findDisclosure = () => {
			const summary = screen.queryByText( /optional evidence field/i );
			return summary?.closest( 'details' ) ?? null;
		};

		it( 'collapses optional_missing rows when status is won', () => {
			render(
				<DisputeOutcomeView dispute={ collapsibleDispute( 'won' ) } />
			);

			expect( findDisclosure() ).not.toBeNull();
		} );

		it( 'collapses optional_missing rows when status is warning_closed', () => {
			render(
				<DisputeOutcomeView
					dispute={ collapsibleDispute( 'warning_closed' ) }
				/>
			);

			expect( findDisclosure() ).not.toBeNull();
		} );

		it( 'leaves optional_missing rows inline when status is lost', () => {
			render(
				<DisputeOutcomeView dispute={ collapsibleDispute( 'lost' ) } />
			);

			expect( findDisclosure() ).toBeNull();
		} );
	} );
} );
