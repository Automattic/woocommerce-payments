/** @format */
/**
 * External dependencies
 */
import classNames from 'classnames';

/**
 * Internal dependencies
 */
import './payment-method.scss';
import PaymentMethodDeleteButton from './delete-button';

const PaymentMethod = ( {
	id,
	label,
	Icon = () => null,
	description,
	onDeleteClick,
	className,
} ) => {
	return (
		<li
			className={ classNames(
				'payment-method',
				{ 'has-icon-border': 'woocommerce_payments' !== id },
				className
			) }
		>
			<div className="payment-method__icon">
				<Icon />
			</div>
			<div className="payment-method__text">
				<div className="payment-method__label">{ label }</div>
				<div className="payment-method__description">
					{ description }
				</div>
			</div>
			<div className="payment-method__actions">
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
		</li>
	);
};

export default PaymentMethod;
