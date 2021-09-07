/** @format **/

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { useEffect } from '@wordpress/element';

import { some, isMatchWith } from 'lodash';

/**
 * Internal dependencies.
 */
import '../style.scss';
import { useDisputeEvidence, useDispute } from 'wcpay/data';
import useConfirmNavigation from 'utils/use-confirm-navigation';
import { DisputeEvidencePage } from './dispute-evidence-page';

// Temporary MVP data wrapper
export default ( { query } ) => {
	const { id: disputeId } = query;
	const { dispute } = useDispute( disputeId );

	const { isSavingEvidence, evidenceTransient } = useDisputeEvidence(
		disputeId
	);

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

	return <DisputeEvidencePage disputeId={ disputeId } />;
};
