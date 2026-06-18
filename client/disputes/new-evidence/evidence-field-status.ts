/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import type { EvidenceFieldStatus } from './types';
import type { DisputeReason, ProductType } from 'wcpay/types/disputes';
import { evidenceMatrix } from './evidence-matrix';
import { DISPUTE_HIGH_IMPACT_FIELDS } from './constants/high-impact-fields';
import { DISPUTE_TOPICAL_FIELDS } from './constants/topical-fields';
import { FALLBACK_EVIDENCE_FIELD_LABELS } from './constants/fallback-field-labels';
import { hasMeaningfulValue } from './utils';

// Stripe evidence key the WooPayments wizard writes the auto-generated
// cover letter into; surfaced universally in the Outcome View even though
// it isn't tracked as high-impact or topical (Catherine, RiskOps).
const coverLetterFieldKey = 'uncategorized_text';

/**
 * Find a label for `key` across wizard matrix cells that apply to
 * `productType` (including composite `${productType}__${status}` cells).
 * Returns undefined when matched cells disagree on the label: status-specific
 * labels would be misleading in the post-resolution view, which has no
 * wizard-time status to disambiguate. The caller then falls back to
 * `FALLBACK_EVIDENCE_FIELD_LABELS`.
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

const isFieldProvided = (
	evidence: Record< string, unknown >,
	key: string
): boolean => hasMeaningfulValue( evidence[ key ] );

/**
 * Union of matrix keys for cells that apply to `productType`, across every
 * status branch of composite cells.
 *
 * Mirrors the wizard's base-field merge: `customer_communication` is added
 * when any cell matches, since `getRecommendedDocumentFields` auto-merges it
 * into cells that omit it explicitly (37 of 49 today).
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
 * Tri-state status of evidence fields for a (reason, productType) pair.
 *
 * Unions three sources, each with its own missing-state mapping:
 *   - `DISPUTE_HIGH_IMPACT_FIELDS` -> `expected_missing` when empty
 *   - `DISPUTE_TOPICAL_FIELDS`     -> `optional_missing` when empty
 *   - wizard `evidenceMatrix`      -> `optional_missing` when empty
 *
 * Always appends a synthetic cover-letter row (`uncategorized_text`)
 * regardless of (reason, productType), since the wizard auto-generates
 * the cover letter and an empty value is an actionable gap on every
 * dispute. Unrecognised reason/productType therefore returns just that
 * single row.
 */
export const getExpectedFieldStatus = (
	reason: string,
	productType: string,
	evidence: Record< string, unknown >
): EvidenceFieldStatus[] => {
	// Cross-source dedupe in priority order: high-impact > topical > matrix.
	// High-impact loops without a self-dedupe check so seed-data duplicates
	// surface as duplicate rows (fail loud). Topical and matrix sources can't
	// self-duplicate (topical dedupes against `emitted`; `matrixKeys` is a Set).
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

	// Cover letter — surfaced on every dispute regardless of
	// (reason × productType). The wizard auto-generates it into
	// `uncategorized_text`, so an empty value implies the merchant
	// deliberately cleared it (an actionable gap, not an oversight).
	// Placed here so it groups with the high-impact / expected_missing
	// rows and sits ahead of any topical / matrix optional rows.
	if ( ! emitted.has( coverLetterFieldKey ) ) {
		result.push( {
			key: coverLetterFieldKey,
			label: __( 'Cover letter', 'woocommerce-payments' ),
			state: isFieldProvided( evidence, coverLetterFieldKey )
				? 'provided'
				: 'expected_missing',
		} );
		emitted.add( coverLetterFieldKey );
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
