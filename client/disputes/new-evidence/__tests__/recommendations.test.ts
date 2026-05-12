/**
 * Internal dependencies
 */
import { getRecommendations } from '../recommendations';
import type { Recommendation, RecommendationContext } from '../types';

const baseContext = (
	overrides: Partial< RecommendationContext > = {}
): RecommendationContext => ( {
	reason: 'product_not_received',
	productType: 'physical_product',
	outcome: 'keep_doing',
	evidence: {},
	...overrides,
} );

const buildEntry = (
	overrides: Partial< Recommendation > & {
		when: Recommendation[ 'when' ];
	}
): Recommendation => ( {
	id: 'r',
	title: 'Title',
	body: 'Body',
	urgency: 'neutral',
	...overrides,
} );

describe( 'getRecommendations', () => {
	it( 'returns only entries whose outcome matches', () => {
		const couldHelp = buildEntry( {
			id: 'a',
			when: {
				outcome: 'could_help',
				reasonIn: [ 'product_not_received' ],
			},
		} );
		const keepDoing = buildEntry( {
			id: 'b',
			when: {
				outcome: 'keep_doing',
				reasonIn: [ 'product_not_received' ],
			},
		} );

		expect(
			getRecommendations( baseContext( { outcome: 'keep_doing' } ), [
				couldHelp,
				keepDoing,
			] ).map( ( r ) => r.id )
		).toEqual( [ 'b' ] );
	} );

	it( 'filters entries by reasonIn (OR across listed reasons)', () => {
		const fraudulent = buildEntry( {
			id: 'f',
			when: { outcome: 'could_help', reasonIn: [ 'fraudulent' ] },
		} );
		const pnrOrDup = buildEntry( {
			id: 'p',
			when: {
				outcome: 'could_help',
				reasonIn: [ 'product_not_received', 'duplicate' ],
			},
		} );

		expect(
			getRecommendations(
				baseContext( {
					outcome: 'could_help',
					reason: 'product_not_received',
				} ),
				[ fraudulent, pnrOrDup ]
			).map( ( r ) => r.id )
		).toEqual( [ 'p' ] );
	} );

	it( 'filters entries by productTypeIn when present', () => {
		const physicalOnly = buildEntry( {
			id: 'physical',
			when: {
				outcome: 'could_help',
				reasonIn: [ 'product_not_received' ],
				productTypeIn: [ 'physical_product' ],
			},
		} );
		const digitalOnly = buildEntry( {
			id: 'digital',
			when: {
				outcome: 'could_help',
				reasonIn: [ 'product_not_received' ],
				productTypeIn: [ 'digital_product_or_service' ],
			},
		} );

		expect(
			getRecommendations(
				baseContext( {
					outcome: 'could_help',
					productType: 'physical_product',
				} ),
				[ physicalOnly, digitalOnly ]
			).map( ( r ) => r.id )
		).toEqual( [ 'physical' ] );
	} );

	it( 'treats absent productTypeIn as matching any product type', () => {
		const anyProductType = buildEntry( {
			id: 'any',
			when: {
				outcome: 'keep_doing',
				reasonIn: [ 'product_not_received' ],
				// no productTypeIn
			},
		} );

		const physical = getRecommendations(
			baseContext( {
				outcome: 'keep_doing',
				productType: 'physical_product',
			} ),
			[ anyProductType ]
		);
		const digital = getRecommendations(
			baseContext( {
				outcome: 'keep_doing',
				productType: 'digital_product_or_service',
			} ),
			[ anyProductType ]
		);

		expect( physical.map( ( r ) => r.id ) ).toEqual( [ 'any' ] );
		expect( digital.map( ( r ) => r.id ) ).toEqual( [ 'any' ] );
	} );

	it( 'fires when at least one requireProvided field has a value', () => {
		const entry = buildEntry( {
			id: 'shipping',
			when: {
				outcome: 'keep_doing',
				reasonIn: [ 'product_not_received' ],
				requireProvided: [
					'shipping_tracking_number',
					'shipping_carrier',
				],
			},
		} );

		const result = getRecommendations(
			baseContext( {
				outcome: 'keep_doing',
				evidence: { shipping_tracking_number: '1Z999' },
			} ),
			[ entry ]
		);

		expect( result.map( ( r ) => r.id ) ).toEqual( [ 'shipping' ] );
	} );

	it( 'skips entries where every requireProvided field is missing or empty', () => {
		const entry = buildEntry( {
			id: 'shipping',
			when: {
				outcome: 'keep_doing',
				reasonIn: [ 'product_not_received' ],
				requireProvided: [
					'shipping_tracking_number',
					'shipping_carrier',
				],
			},
		} );

		const result = getRecommendations(
			baseContext( {
				outcome: 'keep_doing',
				evidence: {
					shipping_tracking_number: '   ',
					shipping_carrier: '',
				},
			} ),
			[ entry ]
		);

		expect( result ).toEqual( [] );
	} );

	it( 'fires when at least one requireExpectedMissing field has no value', () => {
		const entry = buildEntry( {
			id: 'missing-tracking',
			when: {
				outcome: 'could_help',
				reasonIn: [ 'product_not_received' ],
				requireExpectedMissing: [ 'shipping_tracking_number' ],
			},
		} );

		const result = getRecommendations(
			baseContext( {
				outcome: 'could_help',
				evidence: { shipping_address: '123 Main St' },
			} ),
			[ entry ]
		);

		expect( result.map( ( r ) => r.id ) ).toEqual( [ 'missing-tracking' ] );
	} );

	it( 'skips entries where every requireExpectedMissing field is actually provided', () => {
		const entry = buildEntry( {
			id: 'missing-tracking',
			when: {
				outcome: 'could_help',
				reasonIn: [ 'product_not_received' ],
				requireExpectedMissing: [ 'shipping_tracking_number' ],
			},
		} );

		const result = getRecommendations(
			baseContext( {
				outcome: 'could_help',
				evidence: { shipping_tracking_number: '1Z999' },
			} ),
			[ entry ]
		);

		expect( result ).toEqual( [] );
	} );

	it( 'ANDs all when-clauses together (failing one clause drops the entry)', () => {
		const strictEntry = buildEntry( {
			id: 'strict',
			when: {
				outcome: 'keep_doing',
				reasonIn: [ 'product_not_received' ],
				productTypeIn: [ 'physical_product' ],
				requireProvided: [ 'shipping_tracking_number' ],
			},
		} );

		// All four clauses pass.
		const allMatch = getRecommendations(
			baseContext( {
				outcome: 'keep_doing',
				reason: 'product_not_received',
				productType: 'physical_product',
				evidence: { shipping_tracking_number: '1Z999' },
			} ),
			[ strictEntry ]
		);
		expect( allMatch.map( ( r ) => r.id ) ).toEqual( [ 'strict' ] );

		// productType clause fails; entry drops.
		const productTypeMiss = getRecommendations(
			baseContext( {
				outcome: 'keep_doing',
				reason: 'product_not_received',
				productType: 'digital_product_or_service',
				evidence: { shipping_tracking_number: '1Z999' },
			} ),
			[ strictEntry ]
		);
		expect( productTypeMiss ).toEqual( [] );
	} );

	it( 'returns [] for an unknown reason', () => {
		const entry = buildEntry( {
			id: 'pnr',
			when: {
				outcome: 'could_help',
				reasonIn: [ 'product_not_received' ],
			},
		} );

		const result = getRecommendations(
			baseContext( {
				outcome: 'could_help',
				reason: 'totally-unknown-reason',
			} ),
			[ entry ]
		);

		expect( result ).toEqual( [] );
	} );

	it( 'returns [] for an empty catalog', () => {
		expect( getRecommendations( baseContext(), [] ) ).toEqual( [] );
	} );

	it( 'passes urgency, link, and ids through verbatim', () => {
		const entry = buildEntry( {
			id: 'with-link',
			title: 'Configure shipping',
			body: 'do it',
			urgency: 'critical',
			when: {
				outcome: 'could_help',
				reasonIn: [ 'product_not_received' ],
			},
			link: { label: 'Set up tracking', href: '/wp-admin/...' },
		} );

		const [ result ] = getRecommendations(
			baseContext( { outcome: 'could_help' } ),
			[ entry ]
		);

		expect( result ).toEqual( entry );
	} );

	it( 'preserves catalog order in the returned matches', () => {
		const first = buildEntry( {
			id: 'first',
			when: {
				outcome: 'could_help',
				reasonIn: [ 'product_not_received' ],
			},
		} );
		const second = buildEntry( {
			id: 'second',
			when: {
				outcome: 'could_help',
				reasonIn: [ 'product_not_received' ],
			},
		} );
		const third = buildEntry( {
			id: 'third',
			when: {
				outcome: 'could_help',
				reasonIn: [ 'product_not_received' ],
			},
		} );

		const result = getRecommendations(
			baseContext( { outcome: 'could_help' } ),
			[ first, second, third ]
		);

		expect( result.map( ( r ) => r.id ) ).toEqual( [
			'first',
			'second',
			'third',
		] );
	} );
} );
