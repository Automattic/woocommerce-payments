/** @format **/

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';
import { Button } from '@wordpress/components';
import { Link } from '@woocommerce/components';

/**
 * Internal dependencies.
 */
import CardFooter from 'components/card-footer';

const Actions = ( { id, needsResponse, isSubmitted, onAccept } ) => {
	if ( ! needsResponse && ! isSubmitted ) {
		return null;
	}

	const challengeUrl = addQueryArgs( 'admin.php', {
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
			<Link
				href={ challengeUrl }
				className="components-button is-button is-primary is-large"
				onClick={ () =>
					window.wcTracks.recordEvent(
						needsResponse
							? 'wcpay_dispute_challenge_clicked'
							: 'wcpay_view_submitted_evidence_clicked'
					)
				}
			>
				{ needsResponse
					? __( 'Challenge dispute', 'woocommerce-payments' )
					: __( 'View submitted evidence', 'woocommerce-payments' ) }
			</Link>
			{ needsResponse && (
				<Button
					isDefault
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
