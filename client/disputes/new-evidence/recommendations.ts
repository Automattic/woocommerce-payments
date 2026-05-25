/**
 * Internal dependencies
 */
import type {
	FieldCountPredicate,
	Recommendation,
	RecommendationContext,
} from './types';

/**
 * True when a value is non-null, non-empty, and (for nested objects) has at
 * least one meaningful leaf. Mirrors `hasMeaningfulValue` in
 * evidence-field-status.ts; kept local to avoid coupling.
 */
const hasMeaningfulValue = ( value: unknown ): boolean => {
	if ( value === undefined || value === null ) {
		return false;
	}
	if ( typeof value === 'string' ) {
		return value.trim().length > 0;
	}
	if ( typeof value === 'object' ) {
		return Object.values( value as Record< string, unknown > ).some(
			hasMeaningfulValue
		);
	}
	return Boolean( value );
};

const isProvided = (
	evidence: Record< string, unknown >,
	key: string
): boolean => hasMeaningfulValue( evidence[ key ] );

/**
 * Counts how many keys satisfy `condition` and tests against the count
 * predicate's `min`/`max` (inclusive). Defaults:
 *   - `max` unset → `keys.length` (no upper bound)
 *   - `min` unset:
 *       - if `max` is also unset → 1 (OR semantics — at least one satisfies)
 *       - if `max` is set       → 0 (the caller specified an upper bound only,
 *                                    so the lower bound should not constrain)
 */
const matchesCount = (
	predicate: FieldCountPredicate,
	condition: ( key: string ) => boolean
): boolean => {
	const count = predicate.keys.filter( condition ).length;
	const maxExplicit = predicate.max !== undefined;
	const min = predicate.min ?? ( maxExplicit ? 0 : 1 );
	const max = predicate.max ?? predicate.keys.length;
	return count >= min && count <= max;
};

/**
 * Returns the catalog entries that apply to this dispute. AND across `when`
 * clauses. Catalog is passed in (not imported) so tests can supply fixtures.
 *
 * Applies Cluster 15-style suppression after matching: when any matching
 * entry carries `suppressOtherCriticals: true`, all other `critical`
 * entries are dropped from the result.
 */
export const getRecommendations = (
	context: RecommendationContext,
	catalog: Recommendation[]
): Recommendation[] => {
	const matched = catalog.filter( ( entry ) => {
		// Tombstoned entries stay in the catalog for id stability but never render.
		if ( entry.retired ) {
			return false;
		}

		const { when } = entry;

		if ( when.outcome !== context.outcome ) {
			return false;
		}
		// `reasonIn` / `productTypeIn` are strongly typed in the catalog;
		// the context carries raw server values (typed `string`), so widen
		// the catalog arrays for the membership check.
		if (
			! ( when.reasonIn as readonly string[] ).includes( context.reason )
		) {
			return false;
		}
		if (
			when.productTypeIn &&
			! ( when.productTypeIn as readonly string[] ).includes(
				context.productType
			)
		) {
			return false;
		}
		if (
			when.requireProvided &&
			! matchesCount( when.requireProvided, ( key ) =>
				isProvided( context.evidence, key )
			)
		) {
			return false;
		}
		if (
			when.requireMissing &&
			! matchesCount(
				when.requireMissing,
				( key ) => ! isProvided( context.evidence, key )
			)
		) {
			return false;
		}

		return true;
	} );

	// Suppression: when any matching entry says so, drop other critical
	// entries. The suppressing entry itself stays.
	const suppressor = matched.find(
		( entry ) => entry.suppressOtherCriticals
	);
	if ( suppressor ) {
		return matched.filter(
			( entry ) => entry === suppressor || entry.urgency !== 'critical'
		);
	}

	return matched;
};
