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
			{
				/* eslint-disable camelcase */
				woocommerce_payments_giropay: false,
				woocommerce_payments_sofort: false,
				woocommerce_payments_sepa: false,
				/* eslint-enable camelcase */
			}
		)
	);

	const makeHandlePaymentMethodChange = ( paymentMethod ) => {
		return ( enabled ) => {
			setPaymentMethods( ( oldPaymentMethods ) => ( {
				...oldPaymentMethods,
				[ paymentMethod ]: enabled,
			} ) );
		};
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
			<ul>
				<li className="woocommerce-payments__payment-method-selector__list-item">
					<CheckboxControl
						checked={ paymentMethods.woocommerce_payments_giropay }
						onChange={ makeHandlePaymentMethodChange(
							'woocommerce_payments_giropay'
						) }
						label={
							<PaymentMethodIcon
								name="woocommerce_payments_giropay"
								showName
							/>
						}
					/>
					<span className="woocommerce-payments__payment-method-selector__list-item__fees">
						missing fees
					</span>
					<Icon
						className="woocommerce-payments__payment-method-selector__list-item__info"
						icon="info-outline"
					/>
				</li>
				<li className="woocommerce-payments__payment-method-selector__list-item">
					<CheckboxControl
						checked={ paymentMethods.woocommerce_payments_sofort }
						onChange={ makeHandlePaymentMethodChange(
							'woocommerce_payments_sofort'
						) }
						label={
							<PaymentMethodIcon
								name="woocommerce_payments_sofort"
								showName
							/>
						}
					/>
					<span className="woocommerce-payments__payment-method-selector__list-item__fees">
						missing fees
					</span>
					<Icon
						className="woocommerce-payments__payment-method-selector__list-item__info"
						icon="info-outline"
					/>
				</li>
				<li className="woocommerce-payments__payment-method-selector__list-item">
					<CheckboxControl
						checked={ paymentMethods.woocommerce_payments_sepa }
						onChange={ makeHandlePaymentMethodChange(
							'woocommerce_payments_sepa'
						) }
						label={
							<PaymentMethodIcon
								name="woocommerce_payments_sepa"
								showName
							/>
						}
					/>
					<span className="woocommerce-payments__payment-method-selector__list-item__fees">
						missing fees
					</span>
					<Icon
						className="woocommerce-payments__payment-method-selector__list-item__info"
						icon="info-outline"
					/>
				</li>
			</ul>
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
