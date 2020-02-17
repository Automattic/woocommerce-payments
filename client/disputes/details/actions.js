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

const Actions = ( { id, onAccept } ) => {
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
				{ __( 'Challenge Dispute', 'woocommerce-payments' ) }
			</Link>
			{ onAccept && (
				<Button isDefault isLarge onClick={ onAccept }>
					{ __( 'Accept Dispute', 'woocommerce-payments' ) }
				</Button>
			) }
		</CardFooter>
	);
};

export default Actions;
