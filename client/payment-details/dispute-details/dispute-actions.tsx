/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { Button, Flex } from '@wordpress/components';
import { getHistory } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import { getAdminUrl } from 'wcpay/utils';
import type { Dispute } from 'wcpay/types/disputes';

interface Props {
	/**
	 * The dispute ID.
	 */
	dispute: Dispute;
}
const DisputeActions: React.FC< Props > = ( { dispute } ) => {
	const hasStagedEvidence = dispute.evidence_details?.has_evidence;
	return (
		<Flex justify="start">
			<Button
				variant="primary"
				onClick={ () => {
					// TODO: Tracks event
					// wcpayTracks.recordEvent(
					// 	'wcpay_dispute_challenge_clicked',
					// 	{}
					// );
					const challengeUrl = getAdminUrl( {
						page: 'wc-admin',
						path: '/payments/disputes/challenge',
						id: dispute.id,
					} );
					getHistory().push( challengeUrl );
				} }
			>
				{ hasStagedEvidence
					? __( 'Continue with challenge', 'woocommerce-payments' )
					: __( 'Challenge dispute', 'woocommerce-payments' ) }
			</Button>

			<Button
				variant="tertiary"
				onClick={ () => {
					// Open modal
				} }
			>
				{ __( 'Accept dispute', 'woocommerce-payments' ) }
			</Button>
		</Flex>
	);
};

export default DisputeActions;
