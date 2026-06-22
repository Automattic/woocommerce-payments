/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { Button, RadioControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { useState, createInterpolateElement } from '@wordpress/element';
import interpolateComponents from '@automattic/interpolate-components';
import { Link } from '@woocommerce/components';

/**
 * Internal dependencies.
 */
import ConfirmationModal from 'wcpay/components/confirmation-modal';
import { Charge } from 'wcpay/types/charges';
import { usePaymentIntentWithChargeFallback } from 'wcpay/data/payment-intents';
import { PaymentChargeDetailsResponse } from 'wcpay/payment-details/types';
import { isAwaitingResponse, isInquiry } from 'wcpay/disputes/utils';
import { recordEvent } from 'tracks';
import './style.scss';

interface RefundModalProps {
	charge: Charge;
	formattedAmount: string;
	/**
	 * URL of the associated order, when one exists. When provided, the modal
	 * offers a link to the order screen for a partial / more granular refund.
	 */
	orderUrl?: string;
	onModalClose: () => void;
}

const RefundModal: React.FC< RefundModalProps > = ( {
	charge,
	formattedAmount,
	orderUrl,
	onModalClose,
} ) => {
	const [ reason, setReason ] = useState< string | null >( null );

	const [ isRefundInProgress, setIsRefundInProgress ] =
		useState< boolean >( false );

	const { doRefund } = usePaymentIntentWithChargeFallback(
		charge.payment_intent as string
	) as PaymentChargeDetailsResponse;

	// Refunding a charge with an open inquiry resolves the inquiry, so the
	// modal surfaces that context and records the inquiry-specific event.
	const dispute = charge.dispute;
	const isOpenInquiry =
		!! dispute &&
		isInquiry( dispute.status ) &&
		isAwaitingResponse( dispute.status );

	const handleModalCancel = () => {
		onModalClose();
	};

	const handleRefund = async () => {
		recordEvent( 'payments_transactions_details_refund_full', {
			payment_intent_id: charge.payment_intent,
		} );

		if ( isOpenInquiry && dispute ) {
			recordEvent( 'wcpay_dispute_inquiry_refund_click', {
				dispute_id: dispute.id,
				dispute_status: dispute.status,
				dispute_reason: dispute.reason,
				on_page: 'transaction_details',
			} );
		}

		setIsRefundInProgress( true );
		await doRefund( charge, reason === 'other' ? null : reason );
		setIsRefundInProgress( false );
		handleModalCancel();
	};

	return (
		<ConfirmationModal
			className="wcpay-refund-modal"
			title={ __( 'Refund transaction', 'woocommerce-payments' ) }
			actions={
				<>
					<Button
						onClick={ handleModalCancel }
						variant="secondary"
						__next40pxDefaultSize
					>
						{ __( 'Cancel', 'woocommerce-payments' ) }
					</Button>
					<Button
						onClick={ handleRefund }
						variant="primary"
						isBusy={ isRefundInProgress }
						disabled={ isRefundInProgress }
						__next40pxDefaultSize
					>
						{ __( 'Refund transaction', 'woocommerce-payments' ) }
					</Button>
				</>
			}
			onRequestClose={ handleModalCancel }
		>
			{ isOpenInquiry && (
				<p>
					{ __(
						'Issuing a refund will close the inquiry, returning the amount in question back to the cardholder. No additional fees apply.',
						'woocommerce-payments'
					) }
				</p>
			) }
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
				className="wcpay-refund-modal__reason"
				label={ __(
					'Select a reason (Optional)',
					'woocommerce-payments'
				) }
				selected={ reason || undefined }
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
			{ orderUrl && (
				<p className="wcpay-refund-modal__partial-refund">
					{ createInterpolateElement(
						__(
							'Need to refund part of the order? <link>Go to the order</link>.',
							'woocommerce-payments'
						),
						{
							link: (
								<Link
									href={ orderUrl }
									type="external"
									onClick={ () =>
										recordEvent(
											'payments_transactions_details_partial_refund',
											{
												payment_intent_id:
													charge.payment_intent,
												order_id: charge.order?.id,
											}
										)
									}
								/>
							),
						}
					) }
				</p>
			) }
		</ConfirmationModal>
	);
};

export default RefundModal;
