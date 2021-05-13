/** @format */
/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Button, Modal } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { HorizontalRule } from '@wordpress/primitives';

/**
 * Internal dependencies
 */

import { addSelectedPaymentMethods } from 'data';
import PaymentMethodCheckboxes from '../../components/payment-methods-checkboxes';
import PaymentMethodCheckbox from '../../components/payment-methods-checkboxes/payment-method-checkbox';
import './style.scss';

const PaymentMethodsSelector = ( { onClose, enabledPaymentMethods = [] } ) => {
	const [ paymentMethods, setPaymentMethods ] = useState(
		enabledPaymentMethods.reduce( ( acc, value ) => {
			acc[ value ] = true;
			return acc;
		}, {} )
	);

	const handleChange = ( paymentMethod, enabled ) => {
		setPaymentMethods( ( oldPaymentMethods ) => ( {
			...oldPaymentMethods,
			[ paymentMethod ]: enabled,
		} ) );
	};

	const handleAddSelected = () => {
		const selectedPaymentMethods = Object.entries( paymentMethods )
			.filter( ( [ , enabled ] ) => enabled )
			.map( ( [ method ] ) => method );
		addSelectedPaymentMethods( selectedPaymentMethods );
		onClose();
	};

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
			<PaymentMethodCheckboxes>
				<PaymentMethodCheckbox
					checked={ paymentMethods.giropay }
					onChange={ handleChange }
					fees="missing fees"
					name="giropay"
				/>
				<PaymentMethodCheckbox
					checked={ paymentMethods.sofort }
					onChange={ handleChange }
					fees="missing fees"
					name="sofort"
				/>
				<PaymentMethodCheckbox
					checked={ paymentMethods.sepa }
					onChange={ handleChange }
					fees="missing fees"
					name="sepa"
				/>
			</PaymentMethodCheckboxes>
			<HorizontalRule className="woocommerce-payments__payment-method-selector__separator" />
			<div className="woocommerce-payments__payment-method-selector__footer">
				<Button isPrimary onClick={ handleAddSelected }>
					{ __( 'Add selected', 'woocommerce-payments' ) }
				</Button>
				<Button isSecondary onClick={ onClose }>
					{ __( 'Cancel', 'woocommerce-payments' ) }
				</Button>
			</div>
		</Modal>
	);
};

export default PaymentMethodsSelector;
