/** @format **/

/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { addQueryArgs } from '@wordpress/url';
import { getHistory } from '@woocommerce/navigation';
import apiFetch from '@wordpress/api-fetch';
import { Card } from '@woocommerce/components';

/**
 * Internal dependencies.
 */
import { reasons } from '../strings';
import Actions from './actions';
import Info from '../info';
import Paragraphs from 'components/paragraphs';
import Page from 'components/page';
import Loadable, { LoadableBlock } from 'components/loadable';
import '../style.scss';

export const DisputeDetails = ( { isLoading, dispute = {}, onAccept } ) => {
	const disputeIsAvailable = ! isLoading && dispute.id;

	const actions = disputeIsAvailable && <Actions
			id={ dispute.id }
			needsResponse={ 'needs_response' === dispute.status || 'warning_needs_response' === dispute.status }
			isSubmitted={ dispute.evidence_details && dispute.evidence_details.submission_count > 0 }
			onAccept={ onAccept }
		/>;

	const mapping = reasons[ dispute.reason ] || {};

	if ( ! isLoading && ! disputeIsAvailable ) {
		return (
			<Page isNarrow className="wcpay-dispute-details">
				<Card>
					<div>{ __( 'Dispute not loaded', 'woocommerce-payments' ) }</div>
				</Card>
			</Page>
		);
	}

	return (
		<Page isNarrow className="wcpay-dispute-details">
			<Card title={ <Loadable isLoading={ isLoading } value={ __( 'Dispute Overview', 'woocommerce-payments' ) } /> }>
				<Info dispute={ dispute } isLoading={ isLoading } />
				<LoadableBlock isLoading={ isLoading } numLines={ 4 }>
					<Paragraphs>{ mapping.overview }</Paragraphs>
				</LoadableBlock>
				<LoadableBlock isLoading={ isLoading } numLines={ 6 }>
					{ actions }
				</LoadableBlock>
			</Card>
			<Card
				title={ <Loadable
					isLoading={ isLoading }
					value={ mapping.display
						/* translators: heading for dispute category information section */
						? sprintf( __( '%s Dispute', 'woocommerce-payments' ), mapping.display )
						: __( 'Dispute type', 'woocommerce-payments' )
					}
				/> }
			>
				<LoadableBlock isLoading={ isLoading } numLines={ 4 }>
					<Paragraphs>{ mapping.summary }</Paragraphs>
				</LoadableBlock>

				<LoadableBlock isLoading={ isLoading } numLines={ 6 }>
					{ mapping.required && ( <h3> { __( 'Required to overturn dispute', 'woocommerce-payments' ) } </h3> ) }
					<Paragraphs>{ mapping.required }</Paragraphs>
				</LoadableBlock>

				<LoadableBlock isLoading={ isLoading } numLines={ 6 }>
					{ mapping.respond && ( <h3>{ __( 'How to respond', 'woocommerce-payments' ) }</h3> ) }
					<Paragraphs>{ mapping.respond }</Paragraphs>
					{ actions }
				</LoadableBlock>
			</Card>
		</Page>
	);
};

// Temporary MVP data wrapper
export default ( { query } ) => {
	const path = `/wc/v3/payments/disputes/${ query.id }`;

	const [ dispute, setDispute ] = useState();
	const [ loading, setLoading ] = useState( true );
	const { createSuccessNotice, createErrorNotice } = useDispatch( 'core/notices' );

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

	const handleAcceptSuccess = () => {
		const message = dispute.order
			? sprintf( __( 'You have accepted the dispute for order #%s.', 'woocommerce-payments' ), dispute.order.number )
			: __( 'You have accepted the dispute.', 'woocommerce-payments' );
		createSuccessNotice( message );
		getHistory().push( addQueryArgs( 'admin.php', {
			page: 'wc-admin',
			path: '/payments/disputes',
		} ) );
	};

	const doAccept = async () => {
		setLoading( true );
		try {
			setDispute( await apiFetch( { path: `${ path }/close`, method: 'post' } ) );
			handleAcceptSuccess();
		} catch ( err ) {
			createErrorNotice( __( 'There has been an error accepting the dispute. Please try again later.', 'woocommerce-payments' ) );
		} finally {
			setLoading( false );
		}
	};

	return (
		<DisputeDetails
			isLoading={ loading }
			dispute={ dispute }
			onAccept={ doAccept }
		/>
	);
};
