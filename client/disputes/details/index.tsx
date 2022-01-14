/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { Card, CardBody, CardFooter, CardHeader } from '@wordpress/components';

/**
 * Internal dependencies.
 */
import { useDispute } from 'data/index';
import { reasons } from '../strings';
import Actions from './actions';
import Info from '../info';
import Paragraphs from 'components/paragraphs';
import Page from 'components/page';
import ErrorBoundary from 'components/error-boundary';
import DisputeStatusChip from 'components/dispute-status-chip';
import Loadable, { LoadableBlock } from 'components/loadable';
import { TestModeNotice, topics } from 'components/test-mode-notice';
import '../style.scss';
import { Dispute } from 'wcpay/types/disputes';

const DisputeDetails = ( {
	query: { id: disputeId },
}: {
	query: { id: string };
} ): JSX.Element => {
	const { dispute, isLoading, doAccept } = useDispute( disputeId );
	const disputeObject = dispute || ( {} as Dispute );
	const disputeIsAvailable = ! isLoading && dispute && disputeObject.id;

	const actions = disputeIsAvailable && (
		<Actions
			id={ disputeObject.id }
			needsResponse={
				'needs_response' === disputeObject.status ||
				'warning_needs_response' === disputeObject.status
			}
			isSubmitted={
				disputeObject.evidence_details &&
				0 < disputeObject.evidence_details.submission_count
			}
			onAccept={ doAccept }
		/>
	);

	const mapping = reasons[ disputeObject.reason ] || {};
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
			<ErrorBoundary>
				<Card size="large">
					<CardHeader className="header-dispute-overview">
						<LoadableBlock isLoading={ isLoading } numLines={ 1 }>
							{ __( 'Dispute overview', 'woocommerce-payments' ) }
							<DisputeStatusChip
								status={ disputeObject.status }
							/>
						</LoadableBlock>
					</CardHeader>
					<CardBody>
						<Info
							dispute={ disputeObject }
							isLoading={ isLoading }
						/>
						<LoadableBlock isLoading={ isLoading } numLines={ 4 }>
							<Paragraphs>{ mapping.overview }</Paragraphs>
						</LoadableBlock>
					</CardBody>
					<CardFooter>
						<LoadableBlock isLoading={ isLoading } numLines={ 6 }>
							{ actions || [] }
						</LoadableBlock>
					</CardFooter>
				</Card>
			</ErrorBoundary>
			<ErrorBoundary>
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
									: __(
											'Dispute type',
											'woocommerce-payments'
									  )
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
							{ actions || [] }
						</LoadableBlock>
					</CardFooter>
				</Card>
			</ErrorBoundary>
		</Page>
	);
};

export default DisputeDetails;
