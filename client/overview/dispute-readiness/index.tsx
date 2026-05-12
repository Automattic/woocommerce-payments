/** @format */

/**
 * External dependencies
 */
import React, { useEffect, useRef } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { Button, CardBody, Spinner } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { useDisputeReadiness, useDisputeReadinessActions } from 'data';
import { recordEvent } from 'wcpay/tracks';
import OverviewCard from 'wcpay/components/overview-card';
import { DisputeReadinessSignal } from '../../data/dispute-readiness/types';
import './style.scss';

const learnMoreUrl =
	'https://woocommerce.com/document/woopayments/fraud-and-disputes/preventing-disputes/';

const LoadingState = () => (
	<CardBody className="wcpay-dispute-readiness-card__body is-loading">
		<Spinner />
	</CardBody>
);

const DisputeReadinessCard = () => {
	const { disputeReadiness, isLoading } = useDisputeReadiness();
	const { dismissDisputeReadinessCard, refreshDisputeReadiness } =
		useDisputeReadinessActions();
	const viewedRef = useRef( false );
	const overview = disputeReadiness?.overview;

	useEffect( () => {
		refreshDisputeReadiness();
	}, [ refreshDisputeReadiness ] );

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
			<OverviewCard
				title={ __( 'Dispute Readiness', 'woocommerce-payments' ) }
				className="wcpay-dispute-readiness-card"
				isLoading
				LoadingState={ LoadingState }
			/>
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

	const handleCtaClick = ( signal: DisputeReadinessSignal ) => {
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
	const progressStyle = {
		'--wcpay-dispute-readiness-progress': `${ progress }%`,
	} as React.CSSProperties;

	return (
		<OverviewCard
			title={
				<>
					<span>
						{ __( 'Dispute Readiness', 'woocommerce-payments' ) }
					</span>
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
				</>
			}
			className="wcpay-dispute-readiness-card"
			headerClassName="wcpay-dispute-readiness-card__header"
			isLoading={ false }
			LoadingState={ () => null }
		>
			<CardBody className="wcpay-dispute-readiness-card__body">
				<p className="wcpay-dispute-readiness-card__description">
					{ sprintf(
						/* translators: %d: total number of dispute readiness steps. */
						__(
							// eslint-disable-next-line max-len
							'These %d steps help customers recognize charges, understand your policies, and contact you before opening a dispute.',
							'woocommerce-payments'
						),
						overview.total
					) }{ ' ' }
					<a href={ learnMoreUrl } target="_blank" rel="noreferrer">
						{ __( 'Learn more →', 'woocommerce-payments' ) }
					</a>
				</p>

				<div className="wcpay-dispute-readiness-card__content">
					<div
						className="wcpay-dispute-readiness-card__progress"
						style={ progressStyle }
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
						{ overview.signals.map(
							( signal: DisputeReadinessSignal ) => {
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
							}
						) }
					</ul>
				</div>
			</CardBody>
		</OverviewCard>
	);
};

export default DisputeReadinessCard;
