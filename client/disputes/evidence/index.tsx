/** @format **/

/**
 * External dependencies
 */
import * as React from 'react';
import { __ } from '@wordpress/i18n';
import { useEffect, useMemo } from '@wordpress/element';

import { some, isMatchWith } from 'lodash';

/**
 * Internal dependencies.
 */
import '../style.scss';
import { useDisputeEvidence, useDispute } from 'data';
import evidenceFields from './fields';
import useConfirmNavigation from 'utils/use-confirm-navigation';
import wcpayTracks from 'tracks';
import { DisputeEvidencePage } from './dispute-evidence-page';

// eslint-disable-next-line @typescript-eslint/naming-convention
const PRODUCT_TYPE_META_KEY = '__product_type';

type Dispute = {
	evidence_details?: {
		has_evidence: boolean;
	};
	metadata: Record< string, string >;
	productType: string;
};

/**
 * Retrieves product type from the dispute.
 *
 * @param {Object?} dispute Dispute object
 * @return {string} dispute product type
 */
const getDisputeProductType = ( dispute?: Dispute ) => {
	if ( ! dispute ) {
		return '';
	}

	let productType = dispute.metadata[ PRODUCT_TYPE_META_KEY ] || '';

	// Fallback to `multiple` when evidence submitted but no product type meta.
	if (
		! productType &&
		dispute.evidence_details &&
		dispute.evidence_details.has_evidence
	) {
		productType = 'multiple';
	}

	return productType;
};

// Temporary MVP data wrapper
export default ( { query }: { query: { id: string } } ): JSX.Element => {
	const { id: disputeId } = query;
	const { dispute, isLoading, updateDispute } = useDispute( disputeId );

	const {
		isSavingEvidence,
		isUploadingEvidence,
		evidenceTransient,
		evidenceUploadErrors,
		saveEvidence,
		submitEvidence,
		updateEvidenceTransientForDispute,
		updateEvidenceUploadErrorsForDispute,
		uploadFileEvidenceForDispute,
	} = useDisputeEvidence( disputeId );

	const { setMessage: setNavigationMessage } = useConfirmNavigation();

	useEffect( () => {
		const isPristine =
			! dispute ||
			! evidenceTransient || // Empty evidence transient means no local updates.
			isMatchWith(
				dispute.evidence,
				evidenceTransient,
				( disputeValue, formValue ) => {
					// Treat null and '' as equal values.
					if ( null === disputeValue && ! formValue ) {
						return true;
					}
				}
			);

		if ( isPristine ) {
			setNavigationMessage( '' );
		} else if ( isSavingEvidence ) {
			// We don't want to show the confirmation message while saving evidence.
			setNavigationMessage( '' );
		} else if ( some( dispute.isUploading ) ) {
			// We don't want to show the confirmation message while submitting evidence.
			setNavigationMessage( '' );
		} else {
			setNavigationMessage(
				__(
					'There are unsaved changes on this page. Are you sure you want to leave and discard the unsaved changes?',
					'woocommerce-payments'
				)
			);
		}
	}, [ dispute, evidenceTransient, setNavigationMessage, isSavingEvidence ] );

	const updateEvidence = ( key: string, value: unknown ) => {
		updateEvidenceTransientForDispute( disputeId, {
			...evidenceTransient,
			[ key ]: value,
		} );
	};

	const doRemoveFile = ( key: string ) => {
		updateEvidence( key, '' );
		updateDispute( {
			...dispute,
			metadata: { ...dispute.metadata, [ key ]: '' },
			fileSize: { ...dispute.fileSize, [ key ]: 0 },
		} );
		updateEvidenceUploadErrorsForDispute( disputeId, {
			...evidenceUploadErrors,
			[ key ]: '',
		} );
	};

	const doUploadFile = async ( key: string, file?: Blob ) => {
		uploadFileEvidenceForDispute( disputeId, key, file );
	};

	const doSave = async ( submit: boolean ) => {
		if ( submit ) {
			submitEvidence( dispute.id, evidenceTransient );
		} else {
			saveEvidence( dispute.id, evidenceTransient );
		}
	};

	const productType = getDisputeProductType( dispute );
	const updateProductType = ( newProductType: string ) => {
		const properties = {
			selection: newProductType,
		};
		wcpayTracks.recordEvent( 'wcpay_dispute_product_selected', properties );
		updateDispute( {
			...dispute,
			metadata: {
				...dispute.metadata,
				[ PRODUCT_TYPE_META_KEY ]: newProductType,
			},
		} );
	};

	const disputeReason = dispute && dispute.reason;
	const fieldsToDisplay = useMemo(
		() => evidenceFields( disputeReason, productType ),
		[ disputeReason, productType ]
	);

	return (
		<DisputeEvidencePage
			isLoading={ isLoading }
			isSavingEvidence={ isSavingEvidence }
			dispute={ dispute }
			evidence={
				dispute
					? {
							...dispute.evidence,
							...evidenceTransient,
							metadata: dispute.metadata || {},
							isUploading: isUploadingEvidence,
							uploadingErrors: evidenceUploadErrors,
					  }
					: {}
			}
			onChange={ updateEvidence }
			onFileChange={ doUploadFile }
			onFileRemove={ doRemoveFile }
			onSave={ doSave }
			productType={ productType }
			onChangeProductType={ updateProductType }
			fields={ fieldsToDisplay }
		/>
	);
};
