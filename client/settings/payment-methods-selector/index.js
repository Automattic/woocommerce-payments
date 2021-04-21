/** @format */
/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Button, CheckboxControl, Icon, Modal } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { HorizontalRule } from '@wordpress/primitives';

/**
 * Internal dependencies
 */
import { addSelectedPaymentMethods } from 'data';
import PaymentMethodIcon from 'settings/payment-method-icon';
import './style.scss';

const PaymentMethodsSelector = ( props ) => {
	const { onClose, enabledPaymentMethods = [] } = props;
	const [ paymentMethods, setPaymentMethods ] = useState(
		enabledPaymentMethods.reduce(
			( acc, value ) => {
				acc[ value ] = true;
				return acc;
			},
			{ giropay: false, sofort: false, sepa: false }
		)
	);

	const onChangePaymentMethod = ( paymentMethod ) => {
		return ( enabled ) => {
			setPaymentMethods( {
				...paymentMethods,
				[ paymentMethod ]: enabled,
			} );
		};
	};

	const onAddSelected = () => {
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
			<ul>
				<li className="woocommerce-payments__payment-method-selector__list-item">
					<CheckboxControl
						checked={ paymentMethods.giropay }
						onChange={ onChangePaymentMethod( 'giropay' ) }
						label={ <PaymentMethodIcon name="giropay" showName /> }
					/>
					<span className="woocommerce-payments__payment-method-selector__list-item-fees">
						missing fees
					</span>
					<Icon
						className="woocommerce-payments__payment-method-selector__list-item-info"
						icon="info-outline"
					/>
				</li>
				<li className="woocommerce-payments__payment-method-selector__list-item">
					<CheckboxControl
						checked={ paymentMethods.sofort }
						onChange={ onChangePaymentMethod( 'sofort' ) }
						label={ <PaymentMethodIcon name="sofort" showName /> }
					/>
					<span className="woocommerce-payments__payment-method-selector__list-item-fees">
						missing fees
					</span>
					<Icon
						className="woocommerce-payments__payment-method-selector__list-item-info"
						icon="info-outline"
					/>
				</li>
				<li className="woocommerce-payments__payment-method-selector__list-item">
					<CheckboxControl
						checked={ paymentMethods.sepa }
						onChange={ onChangePaymentMethod( 'sepa' ) }
						label={ <PaymentMethodIcon name="sepa" showName /> }
					/>
					<span className="woocommerce-payments__payment-method-selector__list-item-fees">
						missing fees
					</span>
					<Icon
						className="woocommerce-payments__payment-method-selector__list-item-info"
						icon="info-outline"
					/>
				</li>
			</ul>
			<HorizontalRule className="woocommerce-payments__payment-method-selector__separator" />
			<div className="woocommerce-payments__payment-method-selector__footer">
				<Button isPrimary onClick={ onAddSelected }>
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
