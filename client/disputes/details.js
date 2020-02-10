/** @format **/

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Section, Card, Link } from '@woocommerce/components';

const Actions = ( { id } ) => {
	const challengeUrl = addQueryArgs(
		'admin.php',
		{
			page: 'wc-admin',
			path: '/payments/disputes/challenge',
			id,
		}
	);

	return (
		<p>
			<Link href={ challengeUrl } className="components-button is-button is-primary is-large">
				{ __( 'Challenge Dispute', 'woocommerce-payments' ) }
			</Link>
		</p>
	);
};

export const DisputeDetails = props => {
	const { dispute, showPlaceholder } = props;

	if ( showPlaceholder ) {
		return <div>Loading…</div>;
	}

	return (
		<Section>
			<Card title={ __( 'Dispute Overview', 'woocommerce-payments' ) }>
				<Actions id={ dispute.id } />
			</Card>
		</Section>
	);
};

// Temporary MVP data wrapper
export default ( { query } ) => {
	const path = `/wc/v3/payments/disputes/${ query.id }`;

	const [ dispute, setDispute ] = useState( null );
	const [ loading, setLoading ] = useState( true );

	const fetchDispute = async () => {
		setLoading( true );
		setDispute( await apiFetch( { path } ) );
		setLoading( false );
	};
	useEffect( () => {
		fetchDispute();
	}, [] );

	return (
		<DisputeDetails
			showPlaceholder={ loading }
			dispute={ dispute }
		/>
	);
};
