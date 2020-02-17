/** @format **/

/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Card } from '@woocommerce/components';

/**
 * Internal dependencies.
 */
import { reasons } from '../strings';
import Actions from './actions';
import Paragraphs from 'components/paragraphs';
import Page from 'components/page';
import '../style.scss';

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
