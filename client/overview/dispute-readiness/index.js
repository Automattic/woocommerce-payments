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
import { Icon, check } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import { useDisputeReadiness, useDisputeReadinessActions } from 'data';
import { recordEvent } from 'wcpay/tracks';
import './style.scss';

const LEARN_MORE_URL =
	'https://woocommerce.com/document/woopayments/fraud-and-disputes/resolve-disputes/';

const getSignalStatusLabel = ( signal ) => {
	if ( signal.status === 'complete' ) {
		return __( 'Complete', 'woocommerce-payments' );
	}

	return __( 'Incomplete', 'woocommerce-payments' );
};

const SignalStatusIcon = ( { signal } ) => {
	if ( signal.status === 'complete' ) {
		return (
			<span className="wcpay-dispute-readiness-card__signal-icon is-complete">
				<Icon icon={ check } size={ 20 } />
			</span>
		);
	}

	return (
		<span
			className="wcpay-dispute-readiness-card__signal-icon is-incomplete"
			aria-hidden="true"
		>
			•
		</span>
	);
};

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

				<ul className="wcpay-dispute-readiness-card__signals">
					{ overview.signals.map( ( signal ) => (
						<li
							key={ signal.id }
							className={ `wcpay-dispute-readiness-card__signal is-${ signal.status }` }
						>
							<SignalStatusIcon signal={ signal } />
							<div className="wcpay-dispute-readiness-card__signal-content">
								<strong>{ signal.label }</strong>
								<span>{ signal.description }</span>
							</div>
							<div className="wcpay-dispute-readiness-card__signal-action">
								<span className="wcpay-dispute-readiness-card__signal-status">
									{ getSignalStatusLabel( signal ) }
								</span>
								{ signal.status !== 'complete' &&
									signal.actionUrl && (
										<Button
											variant="link"
											href={ signal.actionUrl }
											onClick={ () =>
												handleCtaClick( signal )
											}
										>
											{ signal.actionLabel ||
												__(
													'Fix',
													'woocommerce-payments'
												) }
										</Button>
									) }
							</div>
						</li>
					) ) }
				</ul>

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
