/** @format **/

/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Section, Card, Link } from '@woocommerce/components';

/**
 * Internal dependencies.
 */
import { reasons } from './strings';
import Paragraphs from '../components/paragraphs';

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

export const DisputeDetails = ( { dispute, showPlaceholder } ) => {
	if ( showPlaceholder ) {
		return <div>Loading…</div>;
	}

	const mapping = reasons[ dispute.reason ] || {};
	return (
		<Section>
			<Card title={ __( 'Dispute Overview', 'woocommerce-payments' ) }>
				<Paragraphs>{ mapping.overview }</Paragraphs>
				<hr className="full-width" />
				<Actions id={ dispute.id } />
			</Card>
			{/* translators: heading for dispute category information section */}
			<Card title={ sprintf( __( '%s Dispute', 'woocommerce-payments' ), mapping.display ) }>
				<Paragraphs>{ mapping.summary }</Paragraphs>
				{ mapping.required && <h3>{ __( 'Required to overturn dispute', 'woocommerce-payments' ) }</h3> }
				<Paragraphs>{ mapping.required }</Paragraphs>
				{ mapping.respond && <h3>{ __( 'How to respond', 'woocommerce-payments' ) }</h3> }
				<Paragraphs>{ mapping.respond }</Paragraphs>
				<hr className="full-width" />
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
