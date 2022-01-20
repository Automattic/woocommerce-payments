/** @format */
/**
 * External dependencies
 */
import React, { useContext, useEffect } from 'react';
import { Icon, VisuallyHidden } from '@wordpress/components';
import { useCallback, useMemo } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import WCPaySettingsContext from '../../settings/wcpay-settings-context';
import {
	formatMethodFeesDescription,
	formatMethodFeesTooltip,
} from '../../utils/account-fees';
import LoadableCheckboxControl from 'components/loadable-checkbox';
import { upeCapabilityStatuses } from 'wcpay/additional-methods-setup/constants';
import PaymentMethodIcon from '../../settings/payment-method-icon';
import PaymentMethodsMap from '../../payment-methods-map';
import Pill from '../pill';
import Tooltip from '../tooltip';
import './payment-method-checkbox.scss';

const PaymentMethodDescription = ( { name } ) => {
	const description = PaymentMethodsMap[ name ]?.description;
	if ( ! description ) return null;

	return (
		<Tooltip content={ description }>
			<div className="payment-method-checkbox__info">
				<VisuallyHidden>
					{ __(
						'Information about the payment method, click to expand',
						'woocommerce-payments'
					) }
				</VisuallyHidden>
				<Icon icon="info-outline" />
			</div>
		</Tooltip>
	);
};

const PaymentMethodCheckbox = ( { onChange, name, checked, fees, status } ) => {
	const { accountFees } = useContext( WCPaySettingsContext );

	const handleChange = useCallback(
		( enabled ) => {
			onChange( name, enabled );
		},
		[ name, onChange ]
	);

	const disabled = upeCapabilityStatuses.INACTIVE === status;

	// Uncheck payment method if checked and disabled.
	useEffect( () => {
		if ( disabled && checked ) {
			handleChange( false );
		}
	}, [ disabled, checked, handleChange ] );

	const label = useMemo( () => <PaymentMethodIcon name={ name } showName />, [
		name,
	] );

	return (
		<li className="payment-method-checkbox">
			<LoadableCheckboxControl
				label={ label }
				checked={ checked }
				disabled={ disabled }
				onChange={ ( state ) => {
					handleChange( state );
				} }
				delayMsOnCheck={ 1500 }
				delayMsOnUncheck={ 0 }
			/>
			<div className={ 'payment-method-checkbox__pills' }>
				<div className={ 'payment-method-checkbox__pills-left' }>
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
									'Pending approval',
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
								PaymentMethodsMap[ name ].label
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
				<div className={ 'payment-method-checkbox__pills-right' }>
					<Tooltip
						content={ formatMethodFeesTooltip(
							accountFees[ name ]
						) }
						maxWidth={ '300px' }
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
									accountFees[ name ]
								) }
							</span>
						</Pill>
					</Tooltip>
					<PaymentMethodDescription name={ name } />
				</div>
			</div>
		</li>
	);
};

export default PaymentMethodCheckbox;
