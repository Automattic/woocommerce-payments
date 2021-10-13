/** @format */
/**
 * External dependencies
 */
import React, { useContext } from 'react';
import { CheckboxControl, Icon, VisuallyHidden } from '@wordpress/components';
import { useCallback, useMemo } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import PaymentMethodIcon from '../../settings/payment-method-icon';
import PaymentMethodsMap from '../../payment-methods-map';
import Pill from '../pill';
import Tooltip from '../tooltip';
import './payment-method-checkbox.scss';
import WCPaySettingsContext from '../../settings/wcpay-settings-context';
import { formatMethodFeesDescription } from '../../utils/account-fees';

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

	const disabled = ! [ 'active', 'pending', 'unrequested' ].includes(
		status
	);

	// Uncheck payment method if checked and disabled.
	if ( disabled && checked ) {
		handleChange( false );
	}

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
				{ 'pending' === status && (
					<Tooltip
						content={ __(
							'This payment method is pending approval. Once approved, you will be able to use it.',
							'woocommerce-payments'
						) }
					>
						<Pill className={ 'payment-status-pending' }>
							{ __( 'Pending', 'woocommerce-payments' ) }
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
			{ 'active' === status && (
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
