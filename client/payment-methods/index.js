/** @format */

/**
 * External dependencies
 */
import React, { useContext, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { Card, CardHeader, DropdownMenu } from '@wordpress/components';
import { moreVertical } from '@wordpress/icons';
import classNames from 'classnames';

/**
 * Internal dependencies
 */
import './style.scss';
import {
	useEnabledPaymentMethodIds,
	useGetAvailablePaymentMethodIds,
	useGetPaymentMethodStatuses,
	useSelectedPaymentMethod,
	useUnselectedPaymentMethod,
	useAccountDomesticCurrency,
} from 'wcpay/data';

import WcPayUpeContext from '../settings/wcpay-upe-toggle/context';
import PAYMENT_METHOD_IDS from './constants';

// Survey modal imports.
import WcPaySurveyContextProvider from '../settings/survey-modal/provider';
import SurveyModal from '../settings/survey-modal';
import DisableUPEModal from '../settings/disable-upe-modal';
import PaymentMethodsList from 'components/payment-methods-list';
import PaymentMethod from 'components/payment-methods-list/payment-method';
import methodsConfiguration from '../payment-methods-map';
import CardBody from '../settings/card-body';
import { upeCapabilityStatuses } from 'wcpay/additional-methods-setup/constants';
import ConfirmPaymentMethodActivationModal from './activation-modal';
import ConfirmPaymentMethodDeleteModal from './delete-modal';
import { getPaymentMethodDescription } from 'wcpay/utils/payment-methods';
import CapabilityRequestNotice from './capability-request';
import InlineNotice from 'wcpay/components/inline-notice';

const PaymentMethodsDropdownMenu = ( { setOpenModal } ) => {
	const { isUpeEnabled, upeType } = useContext( WcPayUpeContext );
	const isDisablePossible = isUpeEnabled && upeType !== 'deferred_intent';
	const label = isDisablePossible
		? __( 'Add feedback or disable', 'woocommerce-payments' )
		: __( 'Add feedback', 'woocommerce-payments' );

	const buttons = [
		{
			title: __( 'Provide feedback', 'woocommerce-payments' ),
			onClick: () => setOpenModal( 'survey' ),
		},
	];

	if ( isDisablePossible ) {
		buttons.push( {
			title: 'Disable',
			onClick: () => setOpenModal( 'disable' ),
		} );
	}

	return (
		<DropdownMenu
			icon={ moreVertical }
			label={ label }
			controls={ buttons }
		/>
	);
};

const PaymentMethods = () => {
	const [ enabledMethodIds ] = useEnabledPaymentMethodIds();

	const paymentMethodStatuses = useGetPaymentMethodStatuses();

	const availablePaymentMethodIds = useGetAvailablePaymentMethodIds();

	// We filter link payment method since this will be displayed in other section (express checkout).
	// We further split the available methods into pay later and non-pay later methods to sort them in the required order later.
	const availableNonPayLaterMethods = availablePaymentMethodIds.filter(
		( id ) =>
			PAYMENT_METHOD_IDS.LINK !== id &&
			PAYMENT_METHOD_IDS.CARD !== id &&
			! methodsConfiguration[ id ].allows_pay_later
	);

	const availablePayLaterMethods = availablePaymentMethodIds.filter(
		( id ) =>
			PAYMENT_METHOD_IDS.LINK !== id &&
			methodsConfiguration[ id ].allows_pay_later
	);

	const orderedAvailablePaymentMethodIds = [
		PAYMENT_METHOD_IDS.CARD,
		...availablePayLaterMethods,
		...availableNonPayLaterMethods,
	];

	const availableMethods = orderedAvailablePaymentMethodIds.map(
		( methodId ) => methodsConfiguration[ methodId ]
	);

	const isCreditCardEnabled = enabledMethodIds.includes( 'card' );

	const [ activationModalParams, handleActivationModalOpen ] = useState(
		null
	);
	const [ deleteModalParams, handleDeleteModalOpen ] = useState( null );

	const [ , updateSelectedPaymentMethod ] = useSelectedPaymentMethod();

	const [ stripeAccountDomesticCurrency ] = useAccountDomesticCurrency();

	const completeActivation = ( itemId ) => {
		updateSelectedPaymentMethod( itemId );
		handleActivationModalOpen( null );
	};

	const [ , updateUnselectedPaymentMethod ] = useUnselectedPaymentMethod();

	const completeDeleteAction = ( itemId ) => {
		updateUnselectedPaymentMethod( itemId );
		handleDeleteModalOpen( null );
	};

	const getStatusAndRequirements = ( itemId ) => {
		const stripeKey = methodsConfiguration[ itemId ].stripe_key;
		const stripeStatusContainer = paymentMethodStatuses[ stripeKey ] ?? [];
		if ( ! stripeStatusContainer ) {
			return {
				status: upeCapabilityStatuses.UNREQUESTED,
				requirements: [],
			};
		}
		return {
			status: stripeStatusContainer.status,
			requirements: stripeStatusContainer.requirements,
		};
	};

	const handleCheckClick = ( itemId ) => {
		const statusAndRequirements = getStatusAndRequirements( itemId );
		if (
			statusAndRequirements.status === 'unrequested' &&
			statusAndRequirements.requirements.length > 0
		) {
			handleActivationModalOpen( {
				id: itemId,
				requirements: statusAndRequirements.requirements,
			} );
		} else {
			completeActivation( itemId );
		}
	};

	const handleUncheckClick = ( itemId ) => {
		const methodConfig = methodsConfiguration[ itemId ];
		const statusAndRequirements = getStatusAndRequirements( itemId );
		if ( methodConfig && statusAndRequirements.status === 'active' ) {
			handleDeleteModalOpen( {
				id: itemId,
				label: methodConfig.label,
				Icon: methodConfig.icon,
			} );
		} else {
			completeDeleteAction( itemId );
		}
	};

	const { isUpeEnabled, status, upeType } = useContext( WcPayUpeContext );
	const [ openModalIdentifier, setOpenModalIdentifier ] = useState( '' );
	const rollbackNoticeForLegacyUPE = __(
		// eslint-disable-next-line max-len
		'You have been switched from the new checkout to your previous checkout experience. We will keep you posted on the new checkout availability.',
		'woocommerce-payments'
	);
	const rollbackNoticeForLegacyCard = __(
		// eslint-disable-next-line max-len
		'You have been switched from the new checkout to your previous card experience. We will keep you posted on the new checkout availability.'
	);

	return (
		<>
			{ openModalIdentifier === 'disable' ? (
				<DisableUPEModal
					setOpenModal={ setOpenModalIdentifier }
					triggerAfterDisable={ () =>
						setOpenModalIdentifier( 'survey' )
					}
				/>
			) : null }
			{ openModalIdentifier === 'survey' ? (
				<WcPaySurveyContextProvider>
					<SurveyModal
						setOpenModal={ setOpenModalIdentifier }
						surveyKey="wcpay-upe-disable-early-access-2022-may"
						surveyQuestion="why-disable"
					/>
				</WcPaySurveyContextProvider>
			) : null }

			<Card
				className={ classNames( 'payment-methods', {
					'is-loading': status === 'pending',
				} ) }
			>
				{ isUpeEnabled && (
					<CardHeader className="payment-methods__header">
						<h4 className="payment-methods__heading">
							<span>
								{ __(
									'Payment methods',
									'woocommerce-payments'
								) }
							</span>
						</h4>
						<PaymentMethodsDropdownMenu
							setOpenModal={ setOpenModalIdentifier }
						/>
					</CardHeader>
				) }

				{ isUpeEnabled && upeType === 'legacy' && (
					<CardHeader className="payment-methods__header">
						<InlineNotice
							icon
							status="warning"
							isDismissible={ false }
						>
							{ rollbackNoticeForLegacyUPE }
						</InlineNotice>
					</CardHeader>
				) }

				{ ! isUpeEnabled && (
					<CardHeader className="payment-methods__header">
						<InlineNotice
							icon
							status="warning"
							isDismissible={ false }
						>
							{ rollbackNoticeForLegacyCard }
						</InlineNotice>
					</CardHeader>
				) }

				<CardBody size={ null }>
					<CapabilityRequestNotice />

					<PaymentMethodsList className="payment-methods__available-methods">
						{ availableMethods.map(
							( {
								id,
								label,
								icon: Icon,
								allows_manual_capture: isAllowingManualCapture,
								setup_required: isSetupRequired,
								setup_tooltip: setupTooltip,
							} ) => (
								<PaymentMethod
									id={ id }
									key={ id }
									label={ label }
									description={ getPaymentMethodDescription(
										id,
										stripeAccountDomesticCurrency
									) }
									checked={
										enabledMethodIds.includes( id ) &&
										upeCapabilityStatuses.INACTIVE !==
											getStatusAndRequirements( id )
												.status
									}
									// The card payment method is required when UPE is active, and it can't be disabled/unchecked.
									required={
										PAYMENT_METHOD_IDS.CARD === id &&
										isUpeEnabled
									}
									locked={
										PAYMENT_METHOD_IDS.CARD === id &&
										isCreditCardEnabled &&
										isUpeEnabled
									}
									Icon={ Icon }
									status={
										getStatusAndRequirements( id ).status
									}
									isSetupRequired={ isSetupRequired }
									setupTooltip={ setupTooltip }
									isAllowingManualCapture={
										isAllowingManualCapture
									}
									onUncheckClick={ () => {
										handleUncheckClick( id );
									} }
									onCheckClick={ () => {
										handleCheckClick( id );
									} }
									isPoEnabled={
										wcpaySettings?.progressiveOnboarding
											?.isEnabled
									}
									isPoComplete={
										wcpaySettings?.progressiveOnboarding
											?.isComplete
									}
								/>
							)
						) }
					</PaymentMethodsList>
				</CardBody>
			</Card>

			{ activationModalParams && (
				<ConfirmPaymentMethodActivationModal
					onClose={ () => {
						handleActivationModalOpen( null );
					} }
					onConfirmClose={ () => {
						completeActivation( activationModalParams.id );
					} }
					requirements={ activationModalParams.requirements }
					paymentMethod={ activationModalParams.id }
				/>
			) }
			{ deleteModalParams && (
				<ConfirmPaymentMethodDeleteModal
					id={ deleteModalParams.id }
					label={ deleteModalParams.label }
					icon={ deleteModalParams.Icon }
					onConfirm={ () => {
						completeDeleteAction( deleteModalParams.id );
					} }
					onCancel={ () => {
						handleDeleteModalOpen( null );
					} }
				/>
			) }
		</>
	);
};

export default PaymentMethods;
