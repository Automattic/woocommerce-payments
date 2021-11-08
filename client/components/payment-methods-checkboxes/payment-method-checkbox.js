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
			<LoadableCheckboxControl
				checked={ checked }
				onChange={ handleChange }
				label={ label }
				disabled={ disabled }
				delayMsOnCheck={ 1500 }
				delayMsOnUncheck={ 0 }
			/>
			<div className={ 'payment-method-checkbox__pills' }>
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
				<Tooltip
					content={ formatMethodFeesTooltip( accountFees[ name ] ) }
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
		</li>
	);
};

export default PaymentMethodCheckbox;
