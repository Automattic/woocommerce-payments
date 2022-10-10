/** @format */

/**
 * External dependencies
 */
import React, { useState } from 'react';
import { __ } from '@wordpress/i18n';
import { Card, CardHeader, DropdownMenu } from '@wordpress/components';
import { moreVertical } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import './style.scss';
import {
	useEnabledPaymentMethodIds,
	useGetAvailablePaymentMethodIds,
	useGetPaymentMethodStatuses,
} from 'wcpay/data';

// Survey modal imports.
import WcPaySurveyContextProvider from '../settings/survey-modal/provider';
import SurveyModal from '../settings/survey-modal';
import PaymentMethodsList from 'components/payment-methods-list';
import PaymentMethod from 'components/payment-methods-list/payment-method';
import methodsConfiguration from '../payment-methods-map';
import CardBody from '../settings/card-body';
import { upeCapabilityStatuses } from 'wcpay/additional-methods-setup/constants';
import ConfirmPaymentMethodActivationModal from './activation-modal';
import ConfirmPaymentMethodDeleteModal from './delete-modal';

const PaymentMethodsDropdownMenu = ( { setOpenModal } ) => {
	return (
		<DropdownMenu
			icon={ moreVertical }
			label={ __( 'Add feedback', 'woocommerce-payments' ) }
			controls={ [
				{
					title: __( 'Provide feedback', 'woocommerce-payments' ),
					onClick: () => setOpenModal( 'survey' ),
				},
			] }
		/>
	);
};

const PaymentMethods = () => {
	const [
		enabledMethodIds,
		updateEnabledMethodIds,
	] = useEnabledPaymentMethodIds();

	const paymentMethodStatuses = useGetPaymentMethodStatuses();

	const availablePaymentMethodIds = useGetAvailablePaymentMethodIds();

	// We filter link payment method since this will be displayed in other section (express checkout).
	const availableMethods = availablePaymentMethodIds
		.filter( ( id ) => 'link' !== id )
		.map( ( methodId ) => methodsConfiguration[ methodId ] );

	const [ activationModalParams, handleActivationModalOpen ] = useState(
		null
	);
	const [ deleteModalParams, handleDeleteModalOpen ] = useState( null );

	const completeActivation = ( itemId ) => {
		updateEnabledMethodIds( [
			...new Set( [ ...enabledMethodIds, itemId ] ),
		] );
		handleActivationModalOpen( null );
	};

	const completeDeleteAction = ( itemId ) => {
		updateEnabledMethodIds( [
			...enabledMethodIds.filter( ( id ) => id !== itemId ),
		] );
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
			'unrequested' === statusAndRequirements.status &&
			0 < statusAndRequirements.requirements.length
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
		if ( methodConfig && 'active' === statusAndRequirements.status ) {
			handleDeleteModalOpen( {
				id: itemId,
				label: methodConfig.label,
				Icon: methodConfig.icon,
			} );
		} else {
			completeDeleteAction( itemId );
		}
	};

	const [ openModalIdentifier, setOpenModalIdentifier ] = useState( '' );

	return (
		<>
			{ 'survey' === openModalIdentifier ? (
				<WcPaySurveyContextProvider>
					<SurveyModal
						setOpenModal={ setOpenModalIdentifier }
						surveyKey="wcpay-upe-disable-early-access-2022-may"
						surveyQuestion="why-disable"
					/>
				</WcPaySurveyContextProvider>
			) : null }

			<Card className="payment-methods">
				<CardHeader className="payment-methods__header">
					<h4 className="payment-methods__heading">
						<span>
							{ __( 'Payment methods', 'woocommerce-payments' ) }
						</span>
					</h4>
					<PaymentMethodsDropdownMenu
						setOpenModal={ setOpenModalIdentifier }
					/>
				</CardHeader>

				<CardBody size={ null }>
					<PaymentMethodsList className="payment-methods__available-methods">
						{ availableMethods.map(
							( {
								id,
								label,
								description,
								icon: Icon,
								allows_manual_capture: isAllowingManualCapture,
							} ) => (
								<PaymentMethod
									id={ id }
									key={ id }
									label={ label }
									description={ description }
									checked={
										enabledMethodIds.includes( id ) &&
										upeCapabilityStatuses.INACTIVE !==
											getStatusAndRequirements( id )
												.status
									}
									Icon={ Icon }
									status={
										getStatusAndRequirements( id ).status
									}
									isAllowingManualCapture={
										isAllowingManualCapture
									}
									onUncheckClick={ () => {
										handleUncheckClick( id );
									} }
									onCheckClick={ () => {
										handleCheckClick( id );
									} }
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
