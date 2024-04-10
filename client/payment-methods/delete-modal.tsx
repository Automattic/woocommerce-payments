/** @format */
/**
 * External dependencies
 */
import React from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import interpolateComponents from '@automattic/interpolate-components';

/**
 * Internal dependencies
 */
import PaymentDeleteIllustration from '../components/payment-delete-illustration';
import ConfirmationModal from '../components/confirmation-modal';
import InlineNotice from 'wcpay/components/inline-notice';

const ConfirmPaymentMethodDeleteModal: React.FunctionComponent< {
	id: string;
	label: string;
	icon: () => JSX.Element;
	onConfirm: () => void;
	onCancel: () => void;
} > = ( { id, label, icon: Icon, onConfirm, onCancel } ): JSX.Element => {
	const shouldDisplayNotice = id === 'sofort';

	return (
		<ConfirmationModal
			title={ sprintf(
				__(
					/* translators: %1: Name of the payment method being removed */
					'Remove %1$s from checkout',
					'woocommerce-payments'
				),
				label
			) }
			onRequestClose={ onCancel }
			actions={
				<>
					<Button onClick={ onConfirm } isPrimary isDestructive>
						{ __( 'Remove', 'woocommerce-payments' ) }
					</Button>
					<Button onClick={ onCancel } isSecondary>
						{ __( 'Cancel', 'woocommerce-payments' ) }
					</Button>
				</>
			}
		>
			<PaymentDeleteIllustration
				icon={ Icon }
				hasBorder={ 'card' !== id }
			/>
			<p>
				{ interpolateComponents( {
					mixedString: sprintf(
						__(
							'Are you sure you want to remove {{strong}}%s{{/strong}}? ' +
								'Your customers will no longer be able to pay using %s.',
							'woocommerce-payments'
						),
						label,
						label
					),
					components: {
						strong: <strong />,
					},
				} ) }
			</p>
			<p>
				{ interpolateComponents( {
					mixedString: __(
						'You can add it again at any time in {{wooCommercePaymentsLink /}}.',
						'woocommerce-payments'
					),
					components: {
						wooCommercePaymentsLink: (
							<a href="admin.php?page=wc-settings&tab=checkout&section=woocommerce_payments">
								{ 'WooPayments' }
							</a>
						),
					},
				} ) }
			</p>
			{ shouldDisplayNotice && (
				<InlineNotice
					status="warning"
					icon={ true }
					isDismissible={ false }
					className="sofort__notice"
				>
					<span>
						{ __(
							'As of October 20th 2023, Sofort is no longer supported for merchants who are not already using it. This means that if you disable Sofort, you will not be able to re-enable it later. ',
							'woocommerce-payments'
						) }
						<a
							// eslint-disable-next-line max-len
							href="https://woocommerce.com/document/woopayments/payment-methods/additional-payment-methods/#sofort-migration"
							target="_blank"
							rel="external noreferrer noopener"
						>
							{ __( 'Learn more', 'woocommerce-payments' ) }
						</a>
					</span>
				</InlineNotice>
			) }
		</ConfirmationModal>
	);
};

export default ConfirmPaymentMethodDeleteModal;
