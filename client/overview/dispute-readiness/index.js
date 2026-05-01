/** @format */

/**
 * External dependencies
 */
import React, { useEffect, useRef } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	ExternalLink,
	Spinner,
} from '@wordpress/components';
import { CollapsibleList, TaskItem } from '@woocommerce/experimental';

/**
 * Internal dependencies
 */
import { useDisputeReadiness, useDisputeReadinessActions } from 'data';
import { recordEvent } from 'wcpay/tracks';
import './style.scss';

const LEARN_MORE_URL =
	'https://woocommerce.com/document/woopayments/fraud-and-disputes/resolve-disputes/';

const DisputeReadinessCard = () => {
	const { disputeReadiness, isLoading } = useDisputeReadiness();
	const { dismissDisputeReadinessCard } = useDisputeReadinessActions();
	const viewedRef = useRef( false );
	const overview = disputeReadiness?.overview;

	useEffect( () => {
		if ( ! overview || overview.isDismissed || viewedRef.current ) {
			return;
		}

		recordEvent( 'wcpay_dispute_readiness_overview_viewed', {
			score: overview.score,
			total: overview.total,
			complete_signal_ids: overview.completeSignalIds,
			incomplete_signal_ids: overview.incompleteSignalIds,
			is_dismissed: overview.isDismissed,
		} );
		viewedRef.current = true;
	}, [ overview ] );

	if ( isLoading && ! overview ) {
		return (
			<Card>
				<CardHeader>
					{ __( 'Dispute readiness', 'woocommerce-payments' ) }
				</CardHeader>
				<CardBody className="wcpay-dispute-readiness-card is-loading">
					<Spinner />
				</CardBody>
			</Card>
		);
	}

	if ( ! overview || ! overview.enabled || overview.isDismissed ) {
		return null;
	}

	const handleDismiss = () => {
		recordEvent( 'wcpay_dispute_readiness_card_dismissed', {
			score: overview.score,
			total: overview.total,
			complete_signal_ids: overview.completeSignalIds,
			incomplete_signal_ids: overview.incompleteSignalIds,
			state: overview.state,
		} );
		dismissDisputeReadinessCard();
	};

	const handleCtaClick = ( signal ) => {
		recordEvent( 'wcpay_dispute_readiness_signal_cta_clicked', {
			signal_id: signal.id,
			surface: 'overview',
			score: overview.score,
			total: overview.total,
		} );
	};

	return (
		<Card>
			<CardHeader className="wcpay-dispute-readiness-card__header">
				<span>
					{ __( 'Dispute readiness', 'woocommerce-payments' ) }
				</span>
				<Button
					variant="tertiary"
					onClick={ handleDismiss }
					aria-label={ __(
						'Dismiss dispute readiness card',
						'woocommerce-payments'
					) }
				>
					{ __( 'Dismiss', 'woocommerce-payments' ) }
				</Button>
			</CardHeader>
			<CardBody className="wcpay-dispute-readiness-card">
				<p>
					{ __(
						'Prepare your store with information that can help if a customer disputes a payment.',
						'woocommerce-payments'
					) }
				</p>

				<div className="wcpay-dispute-readiness-card__progress">
					{ sprintf(
						/* translators: 1: number of completed signals, 2: total number of signals. */
						__( '%1$d of %2$d complete', 'woocommerce-payments' ),
						overview.score,
						overview.total
					) }
				</div>

				<CollapsibleList
					className="wcpay-dispute-readiness-card__signals"
					collapsed={ false }
					show={ overview.signals.length }
					collapseLabel={ __( 'Hide tasks', 'woocommerce-payments' ) }
					expandLabel={ __( 'Show tasks', 'woocommerce-payments' ) }
				>
					{ overview.signals.map( ( signal ) => {
						const isComplete = signal.status === 'complete';
						const action = isComplete
							? undefined
							: () => {
									if ( ! signal.actionUrl ) {
										return;
									}

									handleCtaClick( signal );
									window.location.href = signal.actionUrl;
							  };

						return (
							<TaskItem
								key={ signal.id }
								title={ signal.label }
								content={ signal.description }
								completed={ isComplete }
								actionLabel={
									! isComplete && signal.actionUrl
										? signal.actionLabel ||
										  __( 'Fix', 'woocommerce-payments' )
										: undefined
								}
								action={ action }
								onClick={ action }
								expandable={ false }
								expanded={ false }
								showActionButton={ true }
							/>
						);
					} ) }
				</CollapsibleList>

				<div className="wcpay-dispute-readiness-card__actions">
					<ExternalLink href={ LEARN_MORE_URL }>
						{ __( 'Learn more', 'woocommerce-payments' ) }
					</ExternalLink>
				</div>
			</CardBody>
		</Card>
	);
};

export default DisputeReadinessCard;
