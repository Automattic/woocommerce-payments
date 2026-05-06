/**
 * Internal dependencies
 */
import type { EvidenceFieldStatus } from './types';
import type { DisputeReason, ProductType } from 'wcpay/types/disputes';
import { evidenceMatrix } from './evidence-matrix';
import { DISPUTE_HIGH_IMPACT_FIELDS } from './constants/high-impact-fields';
import { DISPUTE_TOPICAL_FIELDS } from './constants/topical-fields';
import { FALLBACK_EVIDENCE_FIELD_LABELS } from './constants/fallback-field-labels';

/**
 * Find a label for `key` in the wizard matrix, scoped to the cells that
 * apply to the given product type. Composite-key reasons (`duplicate`,
 * `credit_not_processed`) store cells keyed `${productType}__${status}`;
 * we match any cell whose key equals `productType` or starts with
 * `${productType}__`.
 *
 * Collision handling: if matched cells disagree on the label for `key`
 * (the wizard intentionally uses status-specific labels in some composite
 * cells, e.g. `duplicate_charge_documentation` is "Refund receipt" under
 * `__is_duplicate` and "Any additional receipts" under `__is_not_duplicate`),
 * we return undefined so the caller (`resolveFieldLabel`) can fall through
 * to a neutral label from `FALLBACK_EVIDENCE_FIELD_LABELS`. Picking either
 * status-specific label would be misleading in the post-resolution view,
 * which has no wizard-time status to disambiguate.
 *
 * Single-match wins. Multi-match with all-equal labels also wins. Multi-
 * match with disagreement falls through.
 */
const findMatrixLabel = (
	reason: string,
	productType: string,
	key: string
): string | undefined => {
	const productTypeEntries = evidenceMatrix[ reason ];
	if ( ! productTypeEntries ) {
		return undefined;
	}

	const productTypePrefix = `${ productType }__`;
	const labels = new Set< string >();
	for ( const [ matrixKey, docs ] of Object.entries( productTypeEntries ) ) {
		if (
			matrixKey !== productType &&
			! matrixKey.startsWith( productTypePrefix )
		) {
			continue;
		}
		for ( const doc of docs ) {
			if ( doc.key === key ) {
				labels.add( doc.label );
			}
		}
	}

	return labels.size === 1 ? [ ...labels ][ 0 ] : undefined;
};

const resolveFieldLabel = (
	reason: string,
	productType: string,
	key: string
): string =>
	findMatrixLabel( reason, productType, key ) ??
	FALLBACK_EVIDENCE_FIELD_LABELS[ key ] ??
	key;

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

const isFieldProvided = (
	evidence: Record< string, unknown >,
	key: string
): boolean => hasMeaningfulValue( evidence[ key ] );

/**
 * Collect every matrix key that applies to the given product type for the
 * reason. Composite-key reasons (`duplicate`, `credit_not_processed`) store
 * cells keyed `${productType}__${status}`; we union every cell whose key
 * starts with `${productType}__` so the optional-missing pool covers all
 * status branches the resolved dispute might have come from.
 *
 * Mirrors the base-field merge that `getRecommendedDocumentFields` applies:
 * when at least one cell matches, `customer_communication` is implicitly
 * recommended (the wizard adds it as a base field for cells that don't
 * already include it). Without this, cells that omit `customer_communication`
 * explicitly (37 of 49 today) would silently drop it from the optional-
 * missing pool in the outcome view.
 */
const collectMatrixKeys = (
	reason: string,
	productType: string
): Set< string > => {
	const keys = new Set< string >();
	const productTypeEntries = evidenceMatrix[ reason ];
	if ( ! productTypeEntries ) {
		return keys;
	}

	const productTypePrefix = `${ productType }__`;
	let matched = false;
	for ( const [ matrixKey, docs ] of Object.entries( productTypeEntries ) ) {
		if (
			matrixKey !== productType &&
			! matrixKey.startsWith( productTypePrefix )
		) {
			continue;
		}
		matched = true;
		for ( const doc of docs ) {
			keys.add( doc.key );
		}
	}
	if ( matched ) {
		keys.add( 'customer_communication' );
	}
	return keys;
};

/**
 * Determine the tri-state status of evidence fields for a (reason, product
 * type) pair.
 *
 * The helper returns one entry per key in the union of three sources:
 *   - `DISPUTE_HIGH_IMPACT_FIELDS[reason][productType]` (red ✗ when missing)
 *   - `DISPUTE_TOPICAL_FIELDS[reason][productType]` (muted — when missing)
 *   - Every field in `evidenceMatrix[reason]` whose cell applies to
 *     `productType`, including composite `${productType}__${status}`
 *     cells (muted — when missing)
 *
 * States:
 *   - `provided`:         `evidence[key]` is a non-empty string (after
 *                         trimming) or an object containing at least one
 *                         non-empty leaf value
 *   - `expected_missing`: key is in the high-impact list for this cell and empty
 *   - `optional_missing`: key is topical or matrix-only, and empty
 *
 * Cells with an empty high-impact list produce no `expected_missing` rows.
 * Unrecognised reason or product type strings return an empty array.
 */
export const getExpectedFieldStatus = (
	reason: string,
	productType: string,
	evidence: Record< string, unknown >
): EvidenceFieldStatus[] => {
	// `emitted` provides cross-source deduplication so a key shared
	// between sources (e.g., a field that's both high-impact and topical,
	// or both high-impact and present in the wizard matrix) renders
	// exactly once, in source-priority order: high-impact, topical,
	// matrix. Within a single source: high-impact entries are emitted
	// without a self-dedupe check, so a duplicate in `DISPUTE_HIGH_IMPACT_FIELDS`
	// surfaces as a duplicate row rather than being silently masked
	// (we want data bugs in the seed to fail loud). Topical entries and
	// matrix entries cannot duplicate within their source: topical loops
	// against `emitted` (so an in-source duplicate is masked), and
	// `matrixKeys` is a `Set` by construction.
	const highImpactKeys =
		DISPUTE_HIGH_IMPACT_FIELDS[ reason as DisputeReason ]?.[
			productType as ProductType
		] ?? [];

	const topicalKeys =
		DISPUTE_TOPICAL_FIELDS[ reason as DisputeReason ]?.[
			productType as ProductType
		] ?? [];

	const matrixKeys = collectMatrixKeys( reason, productType );

	const result: EvidenceFieldStatus[] = [];
	const emitted = new Set< string >();

	for ( const key of highImpactKeys ) {
		result.push( {
			key,
			label: resolveFieldLabel( reason, productType, key ),
			state: isFieldProvided( evidence, key )
				? 'provided'
				: 'expected_missing',
		} );
		emitted.add( key );
	}

	for ( const key of topicalKeys ) {
		if ( emitted.has( key ) ) {
			continue;
		}
		result.push( {
			key,
			label: resolveFieldLabel( reason, productType, key ),
			state: isFieldProvided( evidence, key )
				? 'provided'
				: 'optional_missing',
		} );
		emitted.add( key );
	}

	for ( const key of matrixKeys ) {
		if ( emitted.has( key ) ) {
			continue;
		}
		result.push( {
			key,
			label: resolveFieldLabel( reason, productType, key ),
			state: isFieldProvided( evidence, key )
				? 'provided'
				: 'optional_missing',
		} );
		emitted.add( key );
	}

	return result;
};
