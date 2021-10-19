/** @format */
/**
 * External dependencies
 */
import React, { useContext, useEffect } from 'react';
import { CheckboxControl, Icon, VisuallyHidden } from '@wordpress/components';
import { useCallback, useMemo } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import WCPaySettingsContext from '../../settings/wcpay-settings-context';
import { formatMethodFeesDescription } from '../../utils/account-fees';
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

const PaymentMethodCheckbox = ( {
	onChange,
	name,
	checked = false,
	fees,
	status,
} ) => {
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
			<CheckboxControl
				checked={ checked }
				onChange={ handleChange }
				label={ label }
				disabled={ disabled }
			/>
			<div className={ 'payment-method-checkbox__statuses' }>
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
			{ upeCapabilityStatuses.ACTIVE === status && (
				<Tooltip
					content={ __(
						'Base transaction fees',
						'woocommerce-payments'
					) }
				>
					<Pill
						className="payment-method-checkbox__fees"
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
			) }
			<PaymentMethodDescription name={ name } />
		</li>
	);
};

export default PaymentMethodCheckbox;
