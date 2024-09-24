/** @format */

/**
 * External dependencies
 */
import React, { useState } from 'react';

/**
 * Internal dependencies
 */
import './style.scss';
import {
	useEnabledPaymentMethodIds,
	useGetPaymentMethodStatuses,
	useSelectedPaymentMethod,
	useUnselectedPaymentMethod,
} from 'wcpay/data';
import PAYMENT_METHOD_IDS from 'wcpay/constants/payment-method';
import PaymentMethod from './payment-method';
import methodsConfiguration from '../../payment-methods-map';
import { upeCapabilityStatuses } from 'wcpay/additional-methods-setup/constants';
import ConfirmPaymentMethodActivationModal from './activation-modal';
import ConfirmPaymentMethodDeleteModal from './delete-modal';
import CapabilityRequestNotice from './capability-request';
import { getMissingCurrenciesTooltipMessage } from 'wcpay/multi-currency/missing-currencies-message';

const PaymentMethodsList = ( { methodIds } ) => {
	const [ enabledMethodIds ] = useEnabledPaymentMethodIds();

	const paymentMethodStatuses = useGetPaymentMethodStatuses();

	const availableMethods = methodIds.map(
		( methodId ) => methodsConfiguration[ methodId ]
	);

	const isCreditCardEnabled = enabledMethodIds.includes(
		PAYMENT_METHOD_IDS.CARD
	);

	const [ activationModalParams, handleActivationModalOpen ] = useState(
		null
	);

	const [ deleteModalParams, handleDeleteModalOpen ] = useState( null );

	const [ , updateSelectedPaymentMethod ] = useSelectedPaymentMethod();

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

	return (
		<>
			<CapabilityRequestNotice />

			<ul className="payment-methods-list payment-methods__available-methods">
				{ availableMethods.map(
					( {
						id,
						label,
						icon: Icon,
						description,
						allows_manual_capture: isAllowingManualCapture,
						setup_required: isSetupRequired,
						setup_tooltip: setupTooltip,
						currencies,
					} ) => {
						if (
							! wcpaySettings.isMultiCurrencyEnabled &&
							id !== PAYMENT_METHOD_IDS.CARD
						) {
							const currency = wcpaySettings.storeCurrency;
							if ( currencies.indexOf( currency ) < 0 ) {
								isSetupRequired = true;
								setupTooltip = getMissingCurrenciesTooltipMessage(
									label,
									currencies
								);
							}
						}
						return (
							<PaymentMethod
								id={ id }
								key={ id }
								label={ label }
								description={ description }
								checked={
									enabledMethodIds.includes( id ) &&
									upeCapabilityStatuses.INACTIVE !==
										getStatusAndRequirements( id ).status
								}
								// The card payment method is required when UPE is active, and it can't be disabled/unchecked.
								required={ PAYMENT_METHOD_IDS.CARD === id }
								locked={
									PAYMENT_METHOD_IDS.CARD === id &&
									isCreditCardEnabled
								}
								Icon={ Icon }
								status={ getStatusAndRequirements( id ).status }
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
						);
					}
				) }
			</ul>

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

export default PaymentMethodsList;
