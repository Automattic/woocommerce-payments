/**
 * Internal dependencies
 */
import type { Recommendation, RecommendationContext } from './types';

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
 * Returns the catalog entries that apply to this dispute. AND across `when`
 * clauses; arrays inside a clause OR. Catalog is passed in (not imported)
 * so tests can supply fixtures and the live call site stays explicit.
 */
export const getRecommendations = (
	context: RecommendationContext,
	catalog: Recommendation[]
): Recommendation[] =>
	catalog.filter( ( entry ) => {
		const { when } = entry;

		if ( when.outcome !== context.outcome ) {
			return false;
		}
		if ( ! when.reasonIn.includes( context.reason ) ) {
			return false;
		}
		if (
			when.productTypeIn &&
			! when.productTypeIn.includes( context.productType )
		) {
			return false;
		}
		if (
			when.requireProvided &&
			! when.requireProvided.some( ( key ) =>
				isProvided( context.evidence, key )
			)
		) {
			return false;
		}
		if (
			when.requireExpectedMissing &&
			! when.requireExpectedMissing.some(
				( key ) => ! isProvided( context.evidence, key )
			)
		) {
			return false;
		}

		return true;
	} );
