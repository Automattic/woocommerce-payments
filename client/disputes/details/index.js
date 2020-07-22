/** @format **/

/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { Card } from '@woocommerce/components';

/**
 * Internal dependencies.
 */
import { useDispute } from 'data';
import { reasons } from '../strings';
import Actions from './actions';
import Info from '../info';
import Paragraphs from 'components/paragraphs';
import Page from 'components/page';
import Loadable, { LoadableBlock } from 'components/loadable';
import '../style.scss';

const DisputeDetails = ( { query: { id: disputeId } } ) => {
	const { dispute = {}, isLoading, doAccept } = useDispute( disputeId );

	const disputeIsAvailable = ! isLoading && dispute.id;

	const actions = disputeIsAvailable && (
		<Actions
			id={ dispute.id }
			needsResponse={
				'needs_response' === dispute.status ||
				'warning_needs_response' === dispute.status
			}
			isSubmitted={
				dispute.evidence_details &&
				dispute.evidence_details.submission_count > 0
			}
			onAccept={ doAccept }
		/>
	);

	const mapping = reasons[ dispute.reason ] || {};

	if ( ! isLoading && ! disputeIsAvailable ) {
		return (
			<Page isNarrow className="wcpay-dispute-details">
				<Card>
					<div>
						{ __( 'Dispute not loaded', 'woocommerce-payments' ) }
					</div>
				</Card>
			</Page>
		);
	}

	return (
		<Page isNarrow className="wcpay-dispute-details">
			<Card
				title={
					<Loadable
						isLoading={ isLoading }
						value={ __(
							'Dispute overview',
							'woocommerce-payments'
						) }
					/>
				}
			>
				<Info dispute={ dispute } isLoading={ isLoading } />
				<LoadableBlock isLoading={ isLoading } numLines={ 4 }>
					<Paragraphs>{ mapping.overview }</Paragraphs>
				</LoadableBlock>
				<LoadableBlock isLoading={ isLoading } numLines={ 6 }>
					{ actions }
				</LoadableBlock>
			</Card>
			<Card
				title={
					<Loadable
						isLoading={ isLoading }
						value={
							mapping.display
								? /* translators: heading for dispute category information section */
								  sprintf(
										__(
											'Dispute: %s',
											'woocommerce-payments'
										),
										mapping.display
								  )
								: __( 'Dispute type', 'woocommerce-payments' )
						}
					/>
				}
			>
				<LoadableBlock isLoading={ isLoading } numLines={ 4 }>
					<Paragraphs>{ mapping.summary }</Paragraphs>
				</LoadableBlock>

				<LoadableBlock isLoading={ isLoading } numLines={ 6 }>
					{ mapping.required && (
						<h3>
							{ ' ' }
							{ __(
								'Required to overturn dispute',
								'woocommerce-payments'
							) }{ ' ' }
						</h3>
					) }
					<Paragraphs>{ mapping.required }</Paragraphs>
				</LoadableBlock>

				<LoadableBlock isLoading={ isLoading } numLines={ 6 }>
					{ mapping.respond && (
						<h3>
							{ __( 'How to respond', 'woocommerce-payments' ) }
						</h3>
					) }
					<Paragraphs>{ mapping.respond }</Paragraphs>
					{ actions }
				</LoadableBlock>
			</Card>
		</Page>
	);
};

export default DisputeDetails;
