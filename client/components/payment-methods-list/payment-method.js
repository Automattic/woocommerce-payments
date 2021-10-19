/** @format */
/**
 * External dependencies
 */
import classNames from 'classnames';

/**
 * Internal dependencies
 */
import PaymentMethodDeleteButton from './delete-button';
import Pill from '../pill';
import Tooltip from '../tooltip';
import { __, sprintf } from '@wordpress/i18n';
import { upeCapabilityStatuses } from 'wcpay/additional-methods-setup/constants';
import './payment-method.scss';

const PaymentMethod = ( {
	id,
	label,
	Icon = () => null,
	description,
	status,
	onDeleteClick,
	className,
} ) => {
	const disabled = upeCapabilityStatuses.INACTIVE === status;

	return (
		<li
			className={ classNames(
				'payment-method',
				{ 'has-icon-border': 'card' !== id },
				className
			) }
		>
			<div className="payment-method__icon">
				<Icon />
			</div>
			<div className="payment-method__text">
				<div className="payment-method__label">
					{ label }
					{ upeCapabilityStatuses.PENDING_APPROVAL === status && (
						<Tooltip
							content={ __(
								'This payment method is pending approval. Once approved, you will be able to use it.',
								'woocommerce-payments'
							) }
						>
							<Pill
								className={ 'payment-status-pending-approval' }
							>
								{ __(
									'Pending Approval',
									'woocommerce-payments'
								) }
							</Pill>
						</Tooltip>
					) }
					{ upeCapabilityStatuses.PENDING_VERIFICATION === status && (
						<Tooltip
							content={ sprintf(
								__(
									"%s won't be visible to your customers until you provide the required " +
										'information. Follow the instructions sent by our partner Stripe to %s.',
									'woocommerce-payments'
								),
								label,
								wcSettings.currentUserData.email ?? null
							) }
						>
							<Pill
								className={
									'payment-status-pending-verification'
								}
							>
								{ __(
									'Pending Activation',
									'woocommerce-payments'
								) }
							</Pill>
						</Tooltip>
					) }
					{ disabled && (
						<Tooltip
							content={ sprintf(
								__(
									'To use %s, please contact WooCommerce support.',
									'woocommerce-payments'
								),
								label
							) }
						>
							<Pill className={ 'payment-status-' + status }>
								{ __(
									'Contact WooCommerce Support',
									'woocommerce-payments'
								) }
							</Pill>
						</Tooltip>
					) }
				</div>
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
