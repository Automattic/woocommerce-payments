/** @format **/

/**
 * Internal dependencies
 */
import {
	recordOutcomeViewOnce,
	recordSectionViewedOnce,
	recordOutcomeAction,
	getDisputeOutcomeTracksProperties,
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

	it( 'records the event with base dispute properties, product_type and has_recommendations', () => {
		recordOutcomeViewOnce( buildDispute(), 'physical_product', true );

		expect( mockRecordEvent ).toHaveBeenCalledTimes( 1 );
		expect( mockRecordEvent ).toHaveBeenCalledWith(
			'wcpay_dispute_outcome_viewed',
			{
				dispute_id: 'dp_test',
				dispute_status: 'lost',
				dispute_reason: 'product_unacceptable',
				product_type: 'physical_product',
				has_recommendations: true,
			}
		);
	} );

	it( 'does not re-record for the same dispute id (module-scoped dedup)', () => {
		// The payment-details loading lifecycle remounts components several
		// times per view. Module-scoped de-dup keyed on `dispute.id` keeps
		// this to a single event per page session.
		const dispute = buildDispute();
		recordOutcomeViewOnce( dispute, 'physical_product', true );
		recordOutcomeViewOnce( dispute, 'physical_product', true );
		recordOutcomeViewOnce( dispute, 'physical_product', true );

		expect( mockRecordEvent ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'records again when dispute.id changes (SPA in-place swap)', () => {
		recordOutcomeViewOnce(
			buildDispute( { id: 'dp_first' } ),
			'physical_product',
			true
		);
		recordOutcomeViewOnce(
			buildDispute( {
				id: 'dp_second',
				status: 'won',
				reason: 'fraudulent',
			} ),
			'digital_product_or_service',
			false
		);

		expect( mockRecordEvent ).toHaveBeenCalledTimes( 2 );
		expect( mockRecordEvent ).toHaveBeenNthCalledWith(
			1,
			'wcpay_dispute_outcome_viewed',
			expect.objectContaining( {
				dispute_id: 'dp_first',
				dispute_status: 'lost',
				product_type: 'physical_product',
				has_recommendations: true,
			} )
		);
		expect( mockRecordEvent ).toHaveBeenNthCalledWith(
			2,
			'wcpay_dispute_outcome_viewed',
			expect.objectContaining( {
				dispute_id: 'dp_second',
				dispute_status: 'won',
				product_type: 'digital_product_or_service',
				has_recommendations: false,
			} )
		);
	} );

	it( 'does not fire when dispute.id is missing', () => {
		// During loading the wrapper can mount before the id resolves;
		// a view must never be recorded without a dispute_id.
		recordOutcomeViewOnce(
			buildDispute( { id: '' } ),
			'physical_product',
			false
		);

		expect( mockRecordEvent ).not.toHaveBeenCalled();
	} );

	it( 'fires for warning_closed inquiries too', () => {
		recordOutcomeViewOnce(
			buildDispute( { status: 'warning_closed' } ),
			'physical_product',
			false
		);

		expect( mockRecordEvent ).toHaveBeenCalledTimes( 1 );
		expect( mockRecordEvent ).toHaveBeenCalledWith(
			'wcpay_dispute_outcome_viewed',
			expect.objectContaining( {
				dispute_status: 'warning_closed',
				has_recommendations: false,
			} )
		);
	} );

	it( 'omits product_type when undefined but keeps has_recommendations', () => {
		// Drop product_type entirely so analytics never see a '' bucket;
		// has_recommendations is a real boolean and always present.
		recordOutcomeViewOnce( buildDispute(), undefined, false );

		expect( mockRecordEvent ).toHaveBeenCalledTimes( 1 );
		const [ eventName, payload ] = mockRecordEvent.mock.calls[ 0 ];
		expect( eventName ).toBe( 'wcpay_dispute_outcome_viewed' );
		expect( payload ).not.toHaveProperty( 'product_type' );
		expect( payload ).toEqual( {
			dispute_id: 'dp_test',
			dispute_status: 'lost',
			dispute_reason: 'product_unacceptable',
			has_recommendations: false,
		} );
	} );
} );

describe( 'recordSectionViewedOnce', () => {
	beforeEach( () => {
		mockRecordEvent.mockClear();
		_resetOutcomeViewTrackingForTests();
	} );

	it( 'records the section-viewed event with base props, section, counts and ids', () => {
		recordSectionViewedOnce(
			buildDispute(),
			'physical_product',
			'what_could_help',
			[ 'c1-shipping-tracking-critical', 'c3-communication-add' ],
			2
		);

		expect( mockRecordEvent ).toHaveBeenCalledTimes( 1 );
		expect( mockRecordEvent ).toHaveBeenCalledWith(
			'wcpay_dispute_outcome_recommendations_section_viewed',
			{
				dispute_id: 'dp_test',
				dispute_status: 'lost',
				dispute_reason: 'product_unacceptable',
				product_type: 'physical_product',
				section: 'what_could_help',
				recommendation_count: 2,
				visible_count: 2,
				recommendation_ids: [
					'c1-shipping-tracking-critical',
					'c3-communication-add',
				],
			}
		);
	} );

	it( 'dedups per dispute id and section across remounts', () => {
		const dispute = buildDispute();
		recordSectionViewedOnce(
			dispute,
			'physical_product',
			'what_could_help',
			[ 'c1' ],
			1
		);
		recordSectionViewedOnce(
			dispute,
			'physical_product',
			'what_could_help',
			[ 'c1' ],
			1
		);

		expect( mockRecordEvent ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'fires separately for the two sections of the same dispute', () => {
		const dispute = buildDispute();
		recordSectionViewedOnce(
			dispute,
			'physical_product',
			'whats_working_well',
			[ 'c1-pos' ],
			1
		);
		recordSectionViewedOnce(
			dispute,
			'physical_product',
			'what_could_help',
			[ 'c1-crit' ],
			1
		);

		expect( mockRecordEvent ).toHaveBeenCalledTimes( 2 );
	} );

	it( 'does not fire when dispute.id is missing', () => {
		recordSectionViewedOnce(
			buildDispute( { id: '' } ),
			'physical_product',
			'what_could_help',
			[ 'c1' ],
			1
		);

		expect( mockRecordEvent ).not.toHaveBeenCalled();
	} );
} );

describe( 'recordOutcomeAction', () => {
	beforeEach( () => {
		mockRecordEvent.mockClear();
		_resetOutcomeViewTrackingForTests();
	} );

	it( 'records action_clicked with base props, action, section and link_href', () => {
		recordOutcomeAction( buildDispute(), 'physical_product', {
			action: 'learn_more_clicked',
			section: 'what_could_help',
			linkHref: 'https://example.test/doc',
		} );

		expect( mockRecordEvent ).toHaveBeenCalledTimes( 1 );
		expect( mockRecordEvent ).toHaveBeenCalledWith(
			'wcpay_dispute_outcome_action_clicked',
			{
				dispute_id: 'dp_test',
				dispute_status: 'lost',
				dispute_reason: 'product_unacceptable',
				product_type: 'physical_product',
				action: 'learn_more_clicked',
				section: 'what_could_help',
				link_href: 'https://example.test/doc',
			}
		);
	} );

	it( 'omits section and link_href when not provided', () => {
		recordOutcomeAction( buildDispute(), 'physical_product', {
			action: 'show_more_expanded',
		} );

		const [ , payload ] = mockRecordEvent.mock.calls[ 0 ];
		expect( payload ).not.toHaveProperty( 'section' );
		expect( payload ).not.toHaveProperty( 'link_href' );
		expect( payload ).toEqual(
			expect.objectContaining( { action: 'show_more_expanded' } )
		);
	} );

	it( 'does not dedup: each action records (repeat clicks are real signal)', () => {
		const dispute = buildDispute();
		recordOutcomeAction( dispute, 'physical_product', {
			action: 'learn_more_clicked',
			section: 'what_could_help',
		} );
		recordOutcomeAction( dispute, 'physical_product', {
			action: 'learn_more_clicked',
			section: 'what_could_help',
		} );

		expect( mockRecordEvent ).toHaveBeenCalledTimes( 2 );
	} );

	it( 'does not fire when dispute.id is missing', () => {
		recordOutcomeAction( buildDispute( { id: '' } ), 'physical_product', {
			action: 'show_more_expanded',
		} );

		expect( mockRecordEvent ).not.toHaveBeenCalled();
	} );
} );

describe( 'getDisputeOutcomeTracksProperties', () => {
	// Shared base bag for every Outcome View event, so the section-view and
	// action events emit the same dispute identifiers as the viewed event.
	it( 'returns the base dispute properties including product_type', () => {
		expect(
			getDisputeOutcomeTracksProperties(
				buildDispute(),
				'physical_product'
			)
		).toEqual( {
			dispute_id: 'dp_test',
			dispute_status: 'lost',
			dispute_reason: 'product_unacceptable',
			product_type: 'physical_product',
		} );
	} );

	it( 'omits product_type when undefined', () => {
		const props = getDisputeOutcomeTracksProperties(
			buildDispute(),
			undefined
		);

		expect( props ).not.toHaveProperty( 'product_type' );
		expect( props ).toEqual( {
			dispute_id: 'dp_test',
			dispute_status: 'lost',
			dispute_reason: 'product_unacceptable',
		} );
	} );
} );
