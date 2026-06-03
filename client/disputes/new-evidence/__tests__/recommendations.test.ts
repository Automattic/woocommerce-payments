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
	urgency: 'tip',
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
			},
		} );

		expect(
			getRecommendations(
				baseContext( {
					outcome: 'keep_doing',
					productType: 'digital_product_or_service',
				} ),
				[ anyProductType ]
			).map( ( r ) => r.id )
		).toEqual( [ 'any' ] );
	} );

	describe( 'requireProvided count predicate', () => {
		const entry = buildEntry( {
			id: 'shipping',
			when: {
				outcome: 'keep_doing',
				reasonIn: [ 'product_not_received' ],
				requireProvided: {
					keys: [ 'shipping_tracking_number', 'shipping_carrier' ],
					min: 2,
				},
			},
		} );

		it( 'fires when count meets the min', () => {
			const result = getRecommendations(
				baseContext( {
					evidence: {
						shipping_tracking_number: '1Z999',
						shipping_carrier: 'UPS',
					},
				} ),
				[ entry ]
			);
			expect( result.map( ( r ) => r.id ) ).toEqual( [ 'shipping' ] );
		} );

		it( 'does not fire when count falls below min', () => {
			const result = getRecommendations(
				baseContext( {
					evidence: { shipping_tracking_number: '1Z999' },
				} ),
				[ entry ]
			);
			expect( result ).toEqual( [] );
		} );

		it( 'defaults min to 1 when omitted (OR over the key set)', () => {
			const anyOf = buildEntry( {
				id: 'any-of',
				when: {
					outcome: 'keep_doing',
					reasonIn: [ 'product_not_received' ],
					requireProvided: {
						keys: [
							'shipping_tracking_number',
							'shipping_carrier',
						],
					},
				},
			} );

			const result = getRecommendations(
				baseContext( {
					evidence: { shipping_carrier: 'UPS' },
				} ),
				[ anyOf ]
			);
			expect( result.map( ( r ) => r.id ) ).toEqual( [ 'any-of' ] );
		} );

		it( 'defaults min to 0 when only max is set (max-only predicate)', () => {
			// Mirrors c15: `max: 0` with `min` omitted means "zero provided",
			// not "min defaults to 1" (which would be unsatisfiable).
			const noneProvided = buildEntry( {
				id: 'none-provided',
				when: {
					outcome: 'could_help',
					reasonIn: [ 'product_not_received' ],
					requireProvided: {
						keys: [
							'shipping_tracking_number',
							'shipping_carrier',
						],
						max: 0,
					},
				},
			} );

			// 0 provided → fires.
			expect(
				getRecommendations(
					baseContext( { outcome: 'could_help', evidence: {} } ),
					[ noneProvided ]
				).map( ( r ) => r.id )
			).toEqual( [ 'none-provided' ] );

			// 1 provided → exceeds max → does not fire.
			expect(
				getRecommendations(
					baseContext( {
						outcome: 'could_help',
						evidence: { shipping_carrier: 'UPS' },
					} ),
					[ noneProvided ]
				)
			).toEqual( [] );
		} );

		it( 'respects max for exactly-N predicates', () => {
			const exactlyOne = buildEntry( {
				id: 'exactly-one',
				when: {
					outcome: 'keep_doing',
					reasonIn: [ 'product_not_received' ],
					requireProvided: {
						keys: [
							'shipping_tracking_number',
							'shipping_carrier',
						],
						min: 1,
						max: 1,
					},
				},
			} );

			// 1 provided → fires
			expect(
				getRecommendations(
					baseContext( {
						evidence: { shipping_tracking_number: '1Z999' },
					} ),
					[ exactlyOne ]
				).map( ( r ) => r.id )
			).toEqual( [ 'exactly-one' ] );

			// 2 provided → does not fire
			expect(
				getRecommendations(
					baseContext( {
						evidence: {
							shipping_tracking_number: '1Z999',
							shipping_carrier: 'UPS',
						},
					} ),
					[ exactlyOne ]
				)
			).toEqual( [] );
		} );
	} );

	describe( 'requireMissing count predicate', () => {
		it( 'fires when at least one listed field is missing (default min=1)', () => {
			const entry = buildEntry( {
				id: 'missing-tracking',
				when: {
					outcome: 'could_help',
					reasonIn: [ 'product_not_received' ],
					requireMissing: { keys: [ 'shipping_tracking_number' ] },
				},
			} );

			const result = getRecommendations(
				baseContext( {
					outcome: 'could_help',
					evidence: { shipping_address: '123 Main St' },
				} ),
				[ entry ]
			);

			expect( result.map( ( r ) => r.id ) ).toEqual( [
				'missing-tracking',
			] );
		} );

		it( 'fires when all listed fields are missing (min = keys.length)', () => {
			const entry = buildEntry( {
				id: 'all-missing',
				when: {
					outcome: 'could_help',
					reasonIn: [ 'subscription_canceled' ],
					requireMissing: {
						keys: [
							'cancellation_policy',
							'cancellation_rebuttal',
						],
						min: 2,
					},
				},
			} );

			const allMissing = getRecommendations(
				baseContext( {
					outcome: 'could_help',
					reason: 'subscription_canceled',
				} ),
				[ entry ]
			);
			expect( allMissing.map( ( r ) => r.id ) ).toEqual( [
				'all-missing',
			] );

			// Only one missing → does not fire (min not met).
			const oneMissing = getRecommendations(
				baseContext( {
					outcome: 'could_help',
					reason: 'subscription_canceled',
					evidence: { cancellation_policy: 'http://example.com' },
				} ),
				[ entry ]
			);
			expect( oneMissing ).toEqual( [] );
		} );
	} );

	describe( 'empty-string and whitespace-only values count as missing', () => {
		// hasMeaningfulValue trims, so a cleared field ('' or '   ') reads as
		// absent for both predicates.
		const provided = buildEntry( {
			id: 'provided',
			when: {
				outcome: 'keep_doing',
				reasonIn: [ 'product_not_received' ],
				requireProvided: { keys: [ 'shipping_tracking_number' ] },
			},
		} );
		const missing = buildEntry( {
			id: 'missing',
			when: {
				outcome: 'could_help',
				reasonIn: [ 'product_not_received' ],
				requireMissing: { keys: [ 'shipping_tracking_number' ] },
			},
		} );

		it.each( [ '', '   ' ] )(
			'treats %j as not provided (requireProvided does not fire)',
			( value ) => {
				expect(
					getRecommendations(
						baseContext( {
							outcome: 'keep_doing',
							evidence: { shipping_tracking_number: value },
						} ),
						[ provided ]
					)
				).toEqual( [] );
			}
		);

		it.each( [ '', '   ' ] )(
			'treats %j as missing (requireMissing fires)',
			( value ) => {
				expect(
					getRecommendations(
						baseContext( {
							outcome: 'could_help',
							evidence: { shipping_tracking_number: value },
						} ),
						[ missing ]
					).map( ( r ) => r.id )
				).toEqual( [ 'missing' ] );
			}
		);
	} );

	it( 'ANDs all when-clauses together (failing one clause drops the entry)', () => {
		const strictEntry = buildEntry( {
			id: 'strict',
			when: {
				outcome: 'keep_doing',
				reasonIn: [ 'product_not_received' ],
				productTypeIn: [ 'physical_product' ],
				requireProvided: { keys: [ 'shipping_tracking_number' ] },
			},
		} );

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

	it( 'preserves catalog order in the returned matches', () => {
		const entries = [ 'first', 'second', 'third' ].map( ( id ) =>
			buildEntry( {
				id,
				when: {
					outcome: 'could_help',
					reasonIn: [ 'product_not_received' ],
				},
			} )
		);

		const result = getRecommendations(
			baseContext( { outcome: 'could_help' } ),
			entries
		);

		expect( result.map( ( r ) => r.id ) ).toEqual( [
			'first',
			'second',
			'third',
		] );
	} );

	it( 'excludes retired entries even when every clause matches', () => {
		// The retired guard is the runtime half of the append-only id
		// contract: a tombstoned id stays in the catalog but never renders.
		const live = buildEntry( {
			id: 'live',
			when: {
				outcome: 'could_help',
				reasonIn: [ 'product_not_received' ],
			},
		} );
		const tombstoned = buildEntry( {
			id: 'tombstoned',
			retired: true,
			when: {
				outcome: 'could_help',
				reasonIn: [ 'product_not_received' ],
			},
		} );

		const result = getRecommendations(
			baseContext( { outcome: 'could_help' } ),
			[ live, tombstoned ]
		);

		expect( result.map( ( r ) => r.id ) ).toEqual( [ 'live' ] );
	} );

	describe( 'suppression', () => {
		it( 'suppresses other critical entries when an entry with suppressOtherCriticals fires', () => {
			const catchAll = buildEntry( {
				id: 'catch-all',
				urgency: 'critical',
				suppressOtherCriticals: true,
				when: {
					outcome: 'could_help',
					reasonIn: [ 'product_not_received' ],
				},
			} );
			const otherCritical = buildEntry( {
				id: 'other-critical',
				urgency: 'critical',
				when: {
					outcome: 'could_help',
					reasonIn: [ 'product_not_received' ],
				},
			} );
			const aTip = buildEntry( {
				id: 'a-tip',
				urgency: 'tip',
				when: {
					outcome: 'could_help',
					reasonIn: [ 'product_not_received' ],
				},
			} );

			const result = getRecommendations(
				baseContext( { outcome: 'could_help' } ),
				[ catchAll, otherCritical, aTip ]
			);

			expect( result.map( ( r ) => r.id ).sort() ).toEqual( [
				'a-tip',
				'catch-all',
			] );
		} );

		it( 'keeps a non-critical suppressor while still dropping other criticals', () => {
			// The suppressor survives regardless of its urgency (the
			// `=== suppressor` check short-circuits). c15 is critical, but the
			// type allows any urgency, so pin the tip path too.
			const tipSuppressor = buildEntry( {
				id: 'tip-suppressor',
				urgency: 'tip',
				suppressOtherCriticals: true,
				when: {
					outcome: 'could_help',
					reasonIn: [ 'product_not_received' ],
				},
			} );
			const otherCritical = buildEntry( {
				id: 'other-critical',
				urgency: 'critical',
				when: {
					outcome: 'could_help',
					reasonIn: [ 'product_not_received' ],
				},
			} );

			const result = getRecommendations(
				baseContext( { outcome: 'could_help' } ),
				[ tipSuppressor, otherCritical ]
			);

			expect( result.map( ( r ) => r.id ) ).toEqual( [
				'tip-suppressor',
			] );
		} );

		it( 'leaves other criticals alone when the suppressing entry does not match', () => {
			// Suppressor fires only when reason is 'general'; this dispute is PNR.
			const catchAll = buildEntry( {
				id: 'catch-all',
				urgency: 'critical',
				suppressOtherCriticals: true,
				when: { outcome: 'could_help', reasonIn: [ 'general' ] },
			} );
			const otherCritical = buildEntry( {
				id: 'other-critical',
				urgency: 'critical',
				when: {
					outcome: 'could_help',
					reasonIn: [ 'product_not_received' ],
				},
			} );

			const result = getRecommendations(
				baseContext( { outcome: 'could_help' } ),
				[ catchAll, otherCritical ]
			);

			expect( result.map( ( r ) => r.id ) ).toEqual( [
				'other-critical',
			] );
		} );

		it( 'preserves positives and tips when suppression is active', () => {
			const catchAll = buildEntry( {
				id: 'catch-all',
				urgency: 'critical',
				suppressOtherCriticals: true,
				when: {
					outcome: 'could_help',
					reasonIn: [ 'product_not_received' ],
				},
			} );
			const aTip = buildEntry( {
				id: 'a-tip',
				urgency: 'tip',
				when: {
					outcome: 'could_help',
					reasonIn: [ 'product_not_received' ],
				},
			} );

			const result = getRecommendations(
				baseContext( { outcome: 'could_help' } ),
				[ catchAll, aTip ]
			);

			expect( result.map( ( r ) => r.id ).sort() ).toEqual( [
				'a-tip',
				'catch-all',
			] );
		} );
	} );
} );
