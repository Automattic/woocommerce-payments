/** @format **/

/**
 * Internal dependencies
 */
import {
	recordOutcomeViewOnce,
	_resetOutcomeViewTrackingForTests,
} from '../tracks';
import type { ChargeDispute } from 'wcpay/types/charges';

const mockRecordEvent = jest.fn();
jest.mock( 'wcpay/tracks', () => ( {
	recordEvent: ( ...args: unknown[] ) => mockRecordEvent( ...args ),
} ) );

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
		evidence: {},
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

describe( 'recordOutcomeViewOnce', () => {
	beforeEach( () => {
		mockRecordEvent.mockClear();
		_resetOutcomeViewTrackingForTests();
	} );

	it( 'records the event with base dispute properties and product_type', () => {
		recordOutcomeViewOnce( buildDispute(), 'physical_product' );

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

	it( 'does not re-record for the same dispute id (module-scoped dedup)', () => {
		// The payment-details loading lifecycle remounts components several
		// times per view. Module-scoped de-dup keyed on `dispute.id` keeps
		// this to a single event per page session.
		const dispute = buildDispute();
		recordOutcomeViewOnce( dispute, 'physical_product' );
		recordOutcomeViewOnce( dispute, 'physical_product' );
		recordOutcomeViewOnce( dispute, 'physical_product' );

		expect( mockRecordEvent ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'records again when dispute.id changes (SPA in-place swap)', () => {
		recordOutcomeViewOnce(
			buildDispute( { id: 'dp_first' } ),
			'physical_product'
		);
		recordOutcomeViewOnce(
			buildDispute( {
				id: 'dp_second',
				status: 'won',
				reason: 'fraudulent',
			} ),
			'digital_product_or_service'
		);

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

	it( 'does not fire when dispute.id is missing', () => {
		// During loading the wrapper can mount before the id resolves;
		// a view must never be recorded without a dispute_id.
		recordOutcomeViewOnce( buildDispute( { id: '' } ), 'physical_product' );

		expect( mockRecordEvent ).not.toHaveBeenCalled();
	} );

	it( 'fires for warning_closed inquiries too', () => {
		recordOutcomeViewOnce(
			buildDispute( { status: 'warning_closed' } ),
			'physical_product'
		);

		expect( mockRecordEvent ).toHaveBeenCalledTimes( 1 );
		expect( mockRecordEvent ).toHaveBeenCalledWith(
			'wcpay_dispute_outcome_viewed',
			expect.objectContaining( { dispute_status: 'warning_closed' } )
		);
	} );

	it( 'omits product_type when undefined', () => {
		// Drop the key entirely so analytics never see a '' bucket.
		recordOutcomeViewOnce( buildDispute(), undefined );

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
