/** @format */

/**
 * External dependencies
 */
import React, { useEffect, useRef, useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	DropdownMenu,
	ExternalLink,
	MenuGroup,
	MenuItem,
	Modal,
	Spinner,
} from '@wordpress/components';
import { HorizontalRule } from '@wordpress/primitives';
import { moreVertical } from '@wordpress/icons';
import { CollapsibleList, TaskItem } from '@woocommerce/experimental';

/**
 * Internal dependencies
 */
import {
	useDisputeReadiness,
	useDisputeReadinessActions,
} from 'wcpay/data/dispute-readiness';
import { recordEvent } from 'wcpay/tracks';
import { DisputeReadinessSignal } from 'wcpay/data/dispute-readiness/types';
import './style.scss';

const learnMoreUrl =
	'https://woocommerce.com/document/woopayments/fraud-and-disputes/preventing-disputes/';

const LoadingState = () => (
	<Card className="wcpay-dispute-readiness-card">
		<CardHeader className="wcpay-dispute-readiness-card__header">
			{ __( 'Dispute readiness', 'woocommerce-payments' ) }
		</CardHeader>
		<CardBody className="wcpay-dispute-readiness-card__body is-loading">
			<Spinner />
		</CardBody>
	</Card>
);

const DisputeReadinessCard = () => {
	const { disputeReadiness, isLoading } = useDisputeReadiness();
	const {
		dismissDisputeReadinessCard,
		confirmStatementDescriptor,
		refreshDisputeReadiness,
	} = useDisputeReadinessActions();
	const viewedRef = useRef( false );
	const [ statementDescriptorSignal, setStatementDescriptorSignal ] =
		useState< DisputeReadinessSignal | null >( null );
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
		return <LoadingState />;
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

	const handleSignalActionClick = (
		event: React.MouseEvent | React.KeyboardEvent | undefined,
		signal: DisputeReadinessSignal
	) => {
		handleCtaClick( signal );

		if ( signal.reviewPrompt ) {
			event?.preventDefault();
			setStatementDescriptorSignal( signal );
			return;
		}

		if ( signal.actionUrl ) {
			window.location.href = signal.actionUrl;
		}
	};

	const handleStatementDescriptorConfirm = () => {
		recordEvent( 'wcpay_dispute_readiness_statement_descriptor_confirmed', {
			surface: 'overview',
			score: overview.score,
			total: overview.total,
		} );
		confirmStatementDescriptor();
		setStatementDescriptorSignal( null );
	};

	return (
		<>
			<Card className="wcpay-dispute-readiness-card">
				<CardHeader className="wcpay-dispute-readiness-card__header">
					<div className="wcpay-dispute-readiness-card__header-text">
						<div className="wcpay-dispute-readiness-card__title">
							{ __(
								'Dispute readiness',
								'woocommerce-payments'
							) }
						</div>
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
							<ExternalLink href={ learnMoreUrl }>
								{ __( 'Learn more', 'woocommerce-payments' ) }
							</ExternalLink>
						</p>
					</div>
					<DropdownMenu
						icon={ moreVertical }
						label={ __(
							'Dispute readiness actions',
							'woocommerce-payments'
						) }
						popoverProps={ {
							position: 'bottom left',
						} }
						className="wcpay-dispute-readiness-card__actions"
					>
						{ ( { onClose } ) => (
							<MenuGroup>
								<MenuItem
									onClick={ () => {
										handleDismiss();
										onClose();
									} }
								>
									{ __( 'Dismiss', 'woocommerce-payments' ) }
								</MenuItem>
							</MenuGroup>
						) }
					</DropdownMenu>
				</CardHeader>
				<CollapsibleList
					className="wcpay-dispute-readiness-card__task-list"
					collapsed={ false }
					show={ overview.signals.length }
					collapseLabel={ __( 'Hide tasks', 'woocommerce-payments' ) }
					expandLabel={ __( 'Show tasks', 'woocommerce-payments' ) }
				>
					{ overview.signals.map(
						( signal: DisputeReadinessSignal ) => {
							const isComplete = signal.status === 'complete';
							const hasAction =
								! isComplete && !! signal.actionUrl;

							return (
								<TaskItem
									key={ signal.id }
									data-key={ `dispute-readiness-${ signal.id }` }
									data-status={ signal.status }
									title={ signal.label }
									completed={ isComplete }
									content={ signal.description || '' }
									expanded
									showActionButton={ hasAction }
									level={ 3 }
									action={ ( event ) =>
										handleSignalActionClick( event, signal )
									}
									actionLabel={
										signal.actionLabel ||
										__( 'Fix it', 'woocommerce-payments' )
									}
								/>
							);
						}
					) }
				</CollapsibleList>
			</Card>
			{ statementDescriptorSignal?.reviewPrompt && (
				<Modal
					title={ __(
						'Review statement descriptor',
						'woocommerce-payments'
					) }
					className="wcpay-dispute-readiness-card__statement-descriptor-modal"
					onRequestClose={ () =>
						setStatementDescriptorSignal( null )
					}
				>
					<div className="wcpay-dispute-readiness-card__statement-descriptor-content">
						<p>{ statementDescriptorSignal.reviewPrompt.text }</p>
						<div className="wcpay-dispute-readiness-card__statement-descriptor-current">
							<div className="wcpay-dispute-readiness-card__statement-descriptor-current-label">
								{ __(
									'Current statement descriptor',
									'woocommerce-payments'
								) }
							</div>
							<div className="wcpay-dispute-readiness-card__statement-descriptor-current-value">
								{
									statementDescriptorSignal.reviewPrompt
										.currentDescriptor
								}
							</div>
						</div>
					</div>
					<HorizontalRule className="wcpay-dispute-readiness-card__statement-descriptor-separator" />
					<div className="wcpay-dispute-readiness-card__statement-descriptor-actions">
						<Button
							variant="secondary"
							onClick={ handleStatementDescriptorConfirm }
							__next40pxDefaultSize
						>
							{
								statementDescriptorSignal.reviewPrompt
									.confirmLabel
							}
						</Button>
						<Button
							variant="primary"
							href={ statementDescriptorSignal.actionUrl }
							__next40pxDefaultSize
						>
							{
								statementDescriptorSignal.reviewPrompt
									.updateLabel
							}
						</Button>
					</div>
				</Modal>
			) }
		</>
	);
};

export default DisputeReadinessCard;
