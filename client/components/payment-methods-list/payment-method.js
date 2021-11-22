/** @format */
/**
 * External dependencies
 */
import { useContext } from 'react';
import classNames from 'classnames';

/**
 * Internal dependencies
 */
import Pill from '../pill';
import Tooltip from '../tooltip';
import WCPaySettingsContext from '../../settings/wcpay-settings-context';
import LoadableCheckboxControl from '../loadable-checkbox';
import { __, sprintf } from '@wordpress/i18n';
import { upeCapabilityStatuses } from 'wcpay/additional-methods-setup/constants';
import {
	formatMethodFeesDescription,
	formatMethodFeesTooltip,
} from 'wcpay/utils/account-fees';
import './payment-method.scss';

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
} ) => {
	const disabled = upeCapabilityStatuses.INACTIVE === status;
	const { accountFees } = useContext( WCPaySettingsContext );

	const handleChange = ( newStatus ) => {
		if ( newStatus ) {
			return onCheckClick( id );
		}
		return onUncheckClick( id );
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
					label={ label }
					checked={ checked }
					disabled={ disabled }
					onChange={ handleChange }
					delayMsOnCheck={ 1500 }
					delayMsOnUncheck={ 0 }
					hideLabel
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
									wcpaySettings?.accountEmail ?? ''
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
				{ accountFees && accountFees[ id ] && (
					<div className="payment-method__fees">
						<Tooltip
							maxWidth={ '300px' }
							content={ formatMethodFeesTooltip(
								accountFees[ id ]
							) }
						>
							<Pill
								aria-label={ sprintf(
									__(
										'Base transaction fees: %s',
										'woocommerce-payments'
									),
									formatMethodFeesDescription(
										accountFees[ id ]
									)
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
				) }
			</div>
		</li>
	);
};

export default PaymentMethod;
