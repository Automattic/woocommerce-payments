/** @format **/

/**
 * External dependencies
 */

import React from 'react';
import { Button, RadioControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import interpolateComponents from '@automattic/interpolate-components';

/**
 * Internal dependencies.
 */

import ConfirmationModal from 'wcpay/components/confirmation-modal';
import { Charge } from 'wcpay/types/charges';
import { usePaymentIntentWithChargeFallback } from 'wcpay/data';
import { PaymentChargeDetailsResponse } from 'wcpay/payment-details/types';
import { recordEvent } from 'tracks';

interface RefundModalProps {
	charge: Charge;
	formattedAmount: string;
	onModalClose: () => void;
}

const RefundModal: React.FC< RefundModalProps > = ( {
	charge,
	formattedAmount,
	onModalClose,
} ) => {
	const [ reason, setReason ] = useState< string | null >( null );

	const [ isRefundInProgress, setIsRefundInProgress ] = useState< boolean >(
		false
	);

	const { doRefund } = usePaymentIntentWithChargeFallback(
		charge.payment_intent as string
	) as PaymentChargeDetailsResponse;

	const handleModalCancel = () => {
		onModalClose();
	};

	const handleRefund = async () => {
		recordEvent( 'payments_transactions_details_refund_full', {
			payment_intent_id: charge.payment_intent,
		} );
		setIsRefundInProgress( true );
		await doRefund( charge, reason === 'other' ? null : reason );
		setIsRefundInProgress( false );
		handleModalCancel();
	};

	return (
		<ConfirmationModal
			className="missing-order-notice-modal"
			title={ __( 'Refund transaction', 'woocommerce-payments' ) }
			actions={
				<>
					<Button onClick={ handleModalCancel } variant="secondary">
						{ __( 'Cancel', 'woocommerce-payments' ) }
					</Button>
					<Button
						onClick={ handleRefund }
						isPrimary
						isBusy={ isRefundInProgress }
						disabled={ isRefundInProgress }
					>
						{ __( 'Refund transaction', 'woocommerce-payments' ) }
					</Button>
				</>
			}
			onRequestClose={ handleModalCancel }
		>
			<p>
				{ interpolateComponents( {
					mixedString: sprintf(
						__(
							'This will issue a full refund of {{strong}}%s{{/strong}} to the customer.',
							'woocommerce-payments'
						),
						formattedAmount
					),
					components: {
						strong: <strong />,
					},
				} ) }
			</p>
			<RadioControl
				className="missing-order-notice-modal__reason"
				label={ __(
					'Select a reason (Optional)',
					'woocommerce-payments'
				) }
				selected={ reason }
				options={ [
					{
						label: __( 'Duplicate order', 'woocommerce-payments' ),
						value: 'duplicate',
					},
					{
						label: __( 'Fraudulent', 'woocommerce-payments' ),
						value: 'fraudulent',
					},
					{
						label: __(
							'Requested by customer',
							'woocommerce-payments'
						),
						value: 'requested_by_customer',
					},
					{
						label: __( 'Other', 'woocommerce-payments' ),
						value: 'other',
					},
				] }
				onChange={ ( value: string ) => setReason( value ) }
			/>
		</ConfirmationModal>
	);
};

export default RefundModal;
