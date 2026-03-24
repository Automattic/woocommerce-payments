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
import { upeCapabilityStatuses } from 'wcpay/settings/constants';
import ConfirmPaymentMethodActivationModal from './activation-modal';

const PaymentMethodsList = ( { methodIds } ) => {
	const [ enabledMethodIds ] = useEnabledPaymentMethodIds();

	const paymentMethodStatuses = useGetPaymentMethodStatuses();

	const availableMethods = methodIds.map(
		( methodId ) => methodsConfiguration[ methodId ]
	);

	const [ activationModalParams, handleActivationModalOpen ] = useState(
		null
	);

	const [ , updateSelectedPaymentMethod ] = useSelectedPaymentMethod();

	const completeActivation = ( itemId ) => {
		updateSelectedPaymentMethod( itemId );
		handleActivationModalOpen( null );
	};

	const [ , updateUnselectedPaymentMethod ] = useUnselectedPaymentMethod();

	const completeDeleteAction = ( itemId ) => {
		updateUnselectedPaymentMethod( itemId );
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
		completeDeleteAction( itemId );
	};

	return (
		<>
			<ul className="payment-methods-list payment-methods__available-methods">
				{ availableMethods.map(
					( { id, label, icon: Icon, description } ) => (
						<PaymentMethod
							id={ id }
							key={ id }
							label={ label }
							description={ description }
							// The card payment method is required and it can't be disabled/unchecked.
							locked={
								PAYMENT_METHOD_IDS.CARD === id &&
								enabledMethodIds.includes(
									PAYMENT_METHOD_IDS.CARD
								)
							}
							Icon={ Icon }
							onUncheckClick={ () => {
								handleUncheckClick( id );
							} }
							onCheckClick={ () => {
								handleCheckClick( id );
							} }
						/>
					)
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
		</>
	);
};

export default PaymentMethodsList;
