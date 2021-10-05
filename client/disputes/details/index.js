/** @format **/

/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { Card, CardBody, CardFooter, CardHeader } from '@wordpress/components';

/**
 * Internal dependencies.
 */
import { useDispute } from 'wcpay/data';
import { reasons } from '../strings';
import Actions from './actions';
import Info from '../info';
import Paragraphs from 'components/paragraphs';
import Page from 'components/page';
import DisputeStatusChip from 'components/dispute-status-chip';
import Loadable, { LoadableBlock } from 'components/loadable';
import { TestModeNotice, topics } from 'components/test-mode-notice';
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
				0 < dispute.evidence_details.submission_count
			}
			onAccept={ doAccept }
		/>
	);

	const mapping = reasons[ dispute.reason ] || {};

	const testModeNotice = <TestModeNotice topic={ topics.disputeDetails } />;

	if ( ! isLoading && ! disputeIsAvailable ) {
		return (
			<Page isNarrow className="wcpay-dispute-details">
				{ testModeNotice }
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
			{ testModeNotice }
			<Card size="large">
				<CardHeader className="header-dispute-overview">
					<LoadableBlock isLoading={ isLoading } numLines={ 1 }>
						{ __( 'Dispute overview', 'woocommerce-payments' ) }
						<DisputeStatusChip status={ dispute.status } />
					</LoadableBlock>
				</CardHeader>
				<CardBody>
					<Info dispute={ dispute } isLoading={ isLoading } />
					<LoadableBlock isLoading={ isLoading } numLines={ 4 }>
						<Paragraphs>{ mapping.overview }</Paragraphs>
					</LoadableBlock>
				</CardBody>
				<CardFooter>
					<LoadableBlock isLoading={ isLoading } numLines={ 6 }>
						{ actions }
					</LoadableBlock>
				</CardFooter>
			</Card>
			<Card size="large">
				<CardHeader>
					<Loadable
						isLoading={ isLoading }
						value={
							mapping.display
								? sprintf(
										/* translators: heading for dispute category information section */
										__(
											'Dispute: %s',
											'woocommerce-payments'
										),
										mapping.display
								  )
								: __( 'Dispute type', 'woocommerce-payments' )
						}
					/>
				</CardHeader>
				<CardBody>
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
								{ __(
									'How to respond',
									'woocommerce-payments'
								) }
							</h3>
						) }
						<Paragraphs>{ mapping.respond }</Paragraphs>
					</LoadableBlock>
				</CardBody>
				<CardFooter>
					<LoadableBlock isLoading={ isLoading } numLines={ 6 }>
						{ actions }
					</LoadableBlock>
				</CardFooter>
			</Card>
		</Page>
	);
};

export default DisputeDetails;
