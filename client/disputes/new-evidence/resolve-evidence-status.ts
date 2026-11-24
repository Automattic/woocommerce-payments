/** @format **/

/**
 * Internal dependencies
 */
import { getRecommendedDocumentFields } from './recommended-document-fields';
import { DOCUMENT_FIELD_KEYS } from './document-field-keys';
import type { DisputeReason } from 'wcpay/types/disputes';

/** The first value of each list is the form's default. */
export const refundStatuses = [
	'refund_has_been_issued',
	'refund_was_not_owed',
] as const;
export const duplicateStatuses = [
	'is_duplicate',
	'is_not_duplicate',
] as const;

interface DisputeLike {
	reason?: DisputeReason;
	metadata?: {
		/* eslint-disable @typescript-eslint/naming-convention -- Stripe metadata keys; leading underscores are part of the wire format. */
		__refund_status?: string;
		__duplicate_status?: string;
		/* eslint-enable @typescript-eslint/naming-convention */
	} | null;
	evidence?: Record< string, unknown > | null;
	enhanced_eligibility_types?: string[];
}

const resolveStatus = (
	saved: string | undefined,
	statuses: readonly string[],
	getLayoutKeys: ( status: string ) => string[],
	savedFileKeys: string[]
): string => {
	if ( saved && statuses.includes( saved ) ) {
		return saved;
	}
	// Disputes saved before the choice was stored in metadata: pick the layout
	// that shows the most saved documents, so as few as possible are hidden.
	// A tie keeps the earlier status, so the default wins unless another layout
	// shows more.
	const countVisible = ( status: string ) => {
		const layoutKeys = getLayoutKeys( status );
		return savedFileKeys.filter( ( key ) => layoutKeys.includes( key ) )
			.length;
	};
	return statuses.reduce( ( best, status ) =>
		countVisible( status ) > countVisible( best ) ? status : best
	);
};

/**
 * Returns the refund and duplicate status choices the evidence form should
 * show for a dispute.
 *
 * Source priority:
 *   1. `metadata.__refund_status` / `metadata.__duplicate_status` — written by
 *      the form on save.
 *   2. The status whose document layout shows the most documents saved on
 *      the dispute.
 *   3. The form's default, when no other layout shows more.
 */
export const resolveEvidenceStatuses = (
	dispute: DisputeLike,
	productType: string
): { refundStatus: string; duplicateStatus: string } => {
	const savedFileKeys = Object.values( DOCUMENT_FIELD_KEYS ).filter(
		( key ) => !! dispute.evidence?.[ key ]
	);
	const getLayoutKeys = (
		refundStatus?: string,
		duplicateStatus?: string
	): string[] =>
		getRecommendedDocumentFields(
			dispute.reason as DisputeReason,
			refundStatus,
			duplicateStatus,
			productType,
			dispute.enhanced_eligibility_types
		).map( ( field ) => field.key );

	return {
		refundStatus:
			dispute.reason === 'credit_not_processed'
				? resolveStatus(
						dispute.metadata?.__refund_status,
						refundStatuses,
						( status ) => getLayoutKeys( status, undefined ),
						savedFileKeys
					)
				: refundStatuses[ 0 ],
		duplicateStatus:
			dispute.reason === 'duplicate'
				? resolveStatus(
						dispute.metadata?.__duplicate_status,
						duplicateStatuses,
						( status ) => getLayoutKeys( undefined, status ),
						savedFileKeys
					)
				: duplicateStatuses[ 0 ],
	};
};
