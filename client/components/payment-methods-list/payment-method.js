/** @format */
/**
 * External dependencies
 */
import classNames from 'classnames';

/**
 * Internal dependencies
 */
import Pill from '../pill';
import Tooltip from '../tooltip';
import { __, sprintf } from '@wordpress/i18n';
import { upeCapabilityStatuses } from 'wcpay/additional-methods-setup/constants';
import './payment-method.scss';
import LoadableCheckboxControl from '../loadable-checkbox';
import { formatMethodFeesDescription } from 'wcpay/utils/account-fees';
import WCPaySettingsContext from 'wcpay/settings/wcpay-settings-context';
import { useContext, useState } from 'react';

const PaymentMethod = ( {
	id,
	label,
	Icon = () => null,
	description,
	status,
	checked,
	onCheckClick,
	onUncheckClick,
	className,
	fees,
} ) => {
	const disabled = upeCapabilityStatuses.INACTIVE === status;
	const { accountFees } = useContext( WCPaySettingsContext );
	const [ isChecked, setChecked ] = useState( checked );

	const handleChange = ( newStatus ) => {
		if ( newStatus ) {
			setChecked( true );
			return onCheckClick();
		}
		setChecked( false );
		return onUncheckClick();
	};

	return (
		<li
			className={ classNames(
				'payment-method',
				{ 'has-icon-border': 'card' !== id },
				className
			) }
		>
			<div className="payment-method__checkbox">
				<LoadableCheckboxControl
					checked={ checked }
					onChange={ handleChange }
					disabled={ disabled }
					delayMs={ ! isChecked ? 1500 : 0 }
				/>
			</div>
			<div className="payment-method__icon">
				<Icon />
			</div>
			<div className="payment-method__text">
				<div className="payment-method__label-container">
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
									className={
										'payment-status-pending-approval'
									}
								>
									{ __(
										'Pending approval',
										'woocommerce-payments'
									) }
								</Pill>
							</Tooltip>
						) }
						{ upeCapabilityStatuses.PENDING_VERIFICATION ===
							status && (
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
										'Pending activation',
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
				<div className="payment-method__fees">
					<Tooltip
						content={ __(
							'Base transaction fees',
							'woocommerce-payments'
						) }
					>
						<Pill
							aria-label={ sprintf(
								__(
									'Base transaction fees: %s',
									'woocommerce-payments'
								),
								fees
							) }
						>
							<span>
								{ formatMethodFeesDescription(
									accountFees[ id ]
								) }
							</span>
						</Pill>
					</Tooltip>
				</div>
			</div>
		</li>
	);
};

export default PaymentMethod;
