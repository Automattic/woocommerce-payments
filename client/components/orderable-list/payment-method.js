/** @format */
/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import './payment-method.scss';
import { getPaymentMethodSettingsUrl } from '../../utils';
import PaymentMethodDeleteButton from './delete-button';

const PaymentMethod = ( {
	id,
	label,
	Icon = () => null,
	description,
	onDeleteClick,
} ) => {
	const settingsUrl = getPaymentMethodSettingsUrl( id );

	return (
		<>
			<div className="payment-method__icon">
				<Icon />
			</div>
			<div className="payment-method__text">
				<a href={ settingsUrl } className="payment-method__label">
					{ label }
				</a>
				<div className="payment-method__description">
					{ description }
				</div>
			</div>
			<div className="payment-method__actions">
				<a
					href={ settingsUrl }
					className="payment-method__action manage"
				>
					{ __( 'Manage', 'woocommerce-payments' ) }
				</a>
				{ onDeleteClick && (
					<PaymentMethodDeleteButton
						className="payment-method__action delete"
						onClick={ onDeleteClick }
						label={ label }
						Icon={ Icon }
						id={ id }
					/>
				) }
			</div>
		</>
	);
};

export default PaymentMethod;
