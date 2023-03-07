/* global jQuery */
/** @format */
/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import interpolateComponents from 'interpolate-components';
import { useState } from '@wordpress/element';
/**
 * Internal dependencies
 */
import ConfirmationModal from 'wcpay/components/confirmation-modal';

const CancelConfirmationModal = ( { originalOrderStatus } ) => {
	const [
		isCancelConfirmationModalOpen,
		setIsCancelConfirmationModalOpen,
	] = useState( true );

	const closeModal = () => {
		setIsCancelConfirmationModalOpen( false );
	};

	const doNotCancel = () => {
		jQuery( '#order_status' ).val( originalOrderStatus ).change();
		closeModal();
	};

	const cancelOrder = () => {
		closeModal();
	};

	return (
		<>
			{ isCancelConfirmationModalOpen && (
				<ConfirmationModal
					title={ __( 'Cancel order', 'woocommerce-payments' ) }
					isDismissible={ false }
					className="cancel-confirmation-modal"
					actions={
						<>
							<Button isSecondary onClick={ doNotCancel }>
								{ __( 'Do Nothing', 'woocommerce-payments' ) }
							</Button>
							<Button isPrimary onClick={ cancelOrder }>
								{ __( 'Cancel order', 'woocommerce-payments' ) }
							</Button>
						</>
					}
				>
					<p>
						{ interpolateComponents( {
							mixedString: __(
								'Are you trying to issue a refund for this order? If so, please click ' +
									'{{doNothingBold/}} and see our documentation on {{howtoIssueRefunds/}}. If you want ' +
									'to mark this order as Cancelled without issuing a refund, click {{cancelOrderBold/}}.',
								'woocommerce-payments'
							),
							components: {
								doNothingBold: <b>Do Nothing</b>,
								cancelOrderBold: <b>Cancel Order</b>,
								howtoIssueRefunds: (
									<a
										target="_blank"
										href="https://woocommerce.com/document/woocommerce-payments/managing-money/#refunds"
										rel="noopener noreferrer"
									>
										{ __(
											'how to issue refunds',
											'woocommerce-payments'
										) }
									</a>
								),
							},
						} ) }
					</p>
				</ConfirmationModal>
			) }
		</>
	);
};
export default CancelConfirmationModal;
