/** @format */
/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Button, Modal } from '@wordpress/components';
import { HorizontalRule } from '@wordpress/primitives';
import './style.scss';

/**
 * Internal dependencies
 */
import { addSelectedPaymentMethods } from 'data';

const PaymentMethodsSelector = ( props ) => {
	const { onClose } = props;
	return (
		<Modal
			title={ __( 'Add payment methods', 'woocommerce-payments' ) }
			onRequestClose={ onClose }
		>
			<p>
				{ __(
					"Increase your store's conversion by offering your customers preferred and convenient payment methods.",
					'woocommerce-payments'
				) }
			</p>
			<HorizontalRule className="woocommerce-payments__modal-footer-separator" />
			<div className="woocommerce-payments__modal-footer">
				<Button isPrimary onClick={ addSelectedPaymentMethods }>
					{ __( 'Add selected', 'woocommerce-payments' ) }
				</Button>
				<Button isTertiary onClick={ onClose }>
					{ __( 'Cancel', 'woocommerce-payments' ) }
				</Button>
			</div>
		</Modal>
	);
};

export default PaymentMethodsSelector;
