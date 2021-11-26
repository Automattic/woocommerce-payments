/** @format **/

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';

/**
 * Internal dependencies
 */
import wcpayTracks from 'tracks';
import { getAdminUrl } from 'wcpay/utils';

const Actions = ( { id, needsResponse, isSubmitted, onAccept } ) => {
	if ( ! needsResponse && ! isSubmitted ) {
		return null;
	}

	const challengeUrl = getAdminUrl( {
		page: 'wc-admin',
		path: '/payments/disputes/challenge',
		id,
	} );

	const acceptMessage = __(
		"Are you sure you'd like to accept this dispute? This action can not be undone.",
		'woocommerce-payments'
	);

	return (
		<div>
			<Button
				isPrimary
				href={ challengeUrl }
				onClick={ () =>
					wcpayTracks.recordEvent(
						needsResponse
							? 'wcpay_dispute_challenge_clicked'
							: 'wcpay_view_submitted_evidence_clicked'
					)
				}
			>
				{ needsResponse
					? __( 'Challenge dispute', 'woocommerce-payments' )
					: __( 'View submitted evidence', 'woocommerce-payments' ) }
			</Button>
			{ needsResponse && (
				<Button
					isSecondary
					onClick={ () =>
						window.confirm( acceptMessage ) && onAccept()
					}
				>
					{ __( 'Accept dispute', 'woocommerce-payments' ) }
				</Button>
			) }
		</div>
	);
};

export default Actions;
