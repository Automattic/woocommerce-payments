/** @format **/

/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';
import { useState, useEffect } from '@wordpress/element';
import { Button } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { Card, Link } from '@woocommerce/components';
import Page from 'components/page';
import CardFooter from 'components/card-footer';

/**
 * Internal dependencies.
 */
import { reasons } from '../strings';
import Paragraphs from 'components/paragraphs';
import '../style.scss';

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

export const DisputeDetails = ( { dispute, onAccept, showPlaceholder } ) => {
	if ( showPlaceholder ) {
		return <div>Loading…</div>;
	}

	const mapping = reasons[ dispute.reason ] || {};
	return (
		<Page isNarrow className="wcpay-dispute-details">
			<Card title={ __( 'Dispute Overview', 'woocommerce-payments' ) }>
				<Paragraphs>{ mapping.overview }</Paragraphs>
				<Actions id={ dispute.id } onAccept={ onAccept } />
			</Card>
			{/* translators: heading for dispute category information section */}
			<Card title={ sprintf( __( '%s Dispute', 'woocommerce-payments' ), mapping.display ) }>
				<Paragraphs>{ mapping.summary }</Paragraphs>
				{ mapping.required && <h3>{ __( 'Required to overturn dispute', 'woocommerce-payments' ) }</h3> }
				<Paragraphs>{ mapping.required }</Paragraphs>
				{ mapping.respond && <h3>{ __( 'How to respond', 'woocommerce-payments' ) }</h3> }
				<Paragraphs>{ mapping.respond }</Paragraphs>
				<Actions id={ dispute.id } onAccept={ onAccept } />
			</Card>
		</Page>
	);
};

// Temporary MVP data wrapper
export default ( { query } ) => {
	const path = `/wc/v3/payments/disputes/${ query.id }`;

	const [ dispute, setDispute ] = useState( null );
	const [ loading, setLoading ] = useState( true );

	const fetchDispute = async () => {
		setLoading( true );
		try {
			setDispute( await apiFetch( { path } ) );
		} finally {
			setLoading( false );
		}
	};
	useEffect( () => {
		fetchDispute();
	}, [] );

	const doAccept = async () => {
		setLoading( true );
		try {
			setDispute( await apiFetch( { path: `${ path }/close`, method: 'post' } ) );
		} finally {
			setLoading( false );
		}
	};

	return (
		<DisputeDetails
			showPlaceholder={ loading }
			dispute={ dispute }
			onAccept={ dispute && dispute.status.indexOf( 'needs_response' ) >= 0 && doAccept }
		/>
	);
};
