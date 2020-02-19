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

	const challengeUrl = addQueryArgs(
		'admin.php',
		{
			page: 'wc-admin',
			path: '/payments/disputes/challenge',
			id,
		}
	);

	return (
		<CardFooter>
			<Link href={ challengeUrl } className="components-button is-button is-primary is-large">
				{ needsResponse ?
					__( 'Challenge Dispute', 'woocommerce-payments' ) :
					__( 'View Submitted Evidence', 'woocommerce-payments' )
				}
			</Link>
			{ needsResponse && (
				<Button isDefault isLarge onClick={ onAccept }>
					{ __( 'Accept Dispute', 'woocommerce-payments' ) }
				</Button>
			) }
		</CardFooter>
	);
};

export default Actions;
