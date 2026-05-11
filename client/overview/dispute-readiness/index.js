/** @format */

/**
 * External dependencies
 */
import React, { useEffect, useRef } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { Button, Card, CardBody, Spinner } from '@wordpress/components';

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
			<Card className="wcpay-dispute-readiness-card">
				<CardBody className="wcpay-dispute-readiness-card__body is-loading">
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

	const progress = overview.total
		? Math.round( ( overview.score / overview.total ) * 100 )
		: 0;

	return (
		<Card className="wcpay-dispute-readiness-card">
			<CardBody className="wcpay-dispute-readiness-card__body">
				<Button
					className="wcpay-dispute-readiness-card__dismiss"
					variant="tertiary"
					onClick={ handleDismiss }
					aria-label={ __(
						'Dismiss dispute readiness card',
						'woocommerce-payments'
					) }
				>
					×
				</Button>

				<h2>{ __( 'Dispute Readiness', 'woocommerce-payments' ) }</h2>
				<p className="wcpay-dispute-readiness-card__description">
					{ sprintf(
						/* translators: %d: total number of dispute readiness steps. */
						__(
							// eslint-disable-next-line max-len
							"Sometimes a customer's bank questions a charge. These %d steps can help you respond confidently when it happens.",
							'woocommerce-payments'
						),
						overview.total
					) }{ ' ' }
					<a href={ LEARN_MORE_URL }>
						{ __( 'Learn more →', 'woocommerce-payments' ) }
					</a>
				</p>

				<div className="wcpay-dispute-readiness-card__content">
					<div
						className="wcpay-dispute-readiness-card__progress"
						style={ {
							'--wcpay-dispute-readiness-progress': `${ progress }%`,
						} }
						aria-label={ sprintf(
							/* translators: 1: number of completed signals, 2: total number of signals. */
							__(
								'%1$d of %2$d complete',
								'woocommerce-payments'
							),
							overview.score,
							overview.total
						) }
					>
						<span className="wcpay-dispute-readiness-card__progress-score">
							{ overview.score }
						</span>
						<span className="wcpay-dispute-readiness-card__progress-total">
							{ sprintf(
								/* translators: %d: total number of signals. */
								__( 'of %d', 'woocommerce-payments' ),
								overview.total
							) }
						</span>
					</div>

					<ul className="wcpay-dispute-readiness-card__signals">
						{ overview.signals.map( ( signal ) => {
							const isComplete = signal.status === 'complete';

							return (
								<li
									key={ signal.id }
									className={
										isComplete
											? 'is-complete'
											: 'is-incomplete'
									}
								>
									<span
										className="wcpay-dispute-readiness-card__signal-icon"
										aria-hidden="true"
									>
										{ isComplete ? '✓' : '×' }
									</span>
									<span className="wcpay-dispute-readiness-card__signal-label">
										{ signal.label }
									</span>
									{ ! isComplete && signal.actionUrl && (
										<a
											className="wcpay-dispute-readiness-card__signal-action"
											href={ signal.actionUrl }
											onClick={ () =>
												handleCtaClick( signal )
											}
										>
											{ signal.actionLabel ||
												__(
													'Fix',
													'woocommerce-payments'
												) }{ ' ' }
											→
										</a>
									) }
								</li>
							);
						} ) }
					</ul>
				</div>
			</CardBody>
		</Card>
	);
};

export default DisputeReadinessCard;
