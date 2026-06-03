/**
 * Internal dependencies
 */
import type {
	FieldCountPredicate,
	Recommendation,
	RecommendationContext,
} from './types';
import { hasMeaningfulValue } from './utils';

const isProvided = (
	evidence: Record< string, unknown >,
	key: string
): boolean => hasMeaningfulValue( evidence[ key ] );

/**
 * True when the count of `keys` satisfying `condition` falls in `[min, max]`
 * (inclusive). Defaults: `max` → keys.length; `min` → 1, or 0 when only `max`
 * is set (so a `max`-only predicate like c15's `max: 0` stays satisfiable).
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
 * Returns the catalog entries that apply to this dispute (AND across `when`
 * clauses). Catalog is injected so tests can pass fixtures. After matching, a
 * `suppressOtherCriticals` entry drops all other `critical` entries (c15).
 */
export const getRecommendations = (
	context: RecommendationContext,
	catalog: Recommendation[]
): Recommendation[] => {
	const matched = catalog.filter( ( entry ) => {
		// Retired entries stay for id stability but never render.
		if ( entry.retired ) {
			return false;
		}

		const { when } = entry;

		if ( when.outcome !== context.outcome ) {
			return false;
		}
		// Compare by value: the context carries raw server strings, so this
		// keeps the catalog's union types without a widening cast.
		if ( ! when.reasonIn.some( ( reason ) => reason === context.reason ) ) {
			return false;
		}
		if (
			when.productTypeIn &&
			! when.productTypeIn.some(
				( productType ) => productType === context.productType
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

	// Drop other criticals when a matching entry asks; the suppressor stays.
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
