/** @format */
/**
 * External dependencies
 */
import React, { useContext, useEffect, useMemo } from 'react';
import { Icon, VisuallyHidden } from '@wordpress/components';
import { useCallback } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import classNames from 'classnames';

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
import PaymentMethodsMap from '../../payment-methods-map';
import Pill from '../pill';
import { HoverTooltip } from 'components/tooltip';
import './payment-method-checkbox.scss';
import { useManualCapture, useAccountDefaultCurrency } from 'wcpay/data';

const getDescription = ( name, currency ) => {
	const paymentMethod = PaymentMethodsMap[ name ];

	if ( ! paymentMethod ) return null;

	let description = paymentMethod.description;
	const isAllowingPayLater = paymentMethod.allows_pay_later;

	if ( isAllowingPayLater ) {
		description = sprintf( description, currency.toUpperCase() );
	}

	return description;
};

const PaymentMethodDescription = ( { name } ) => {
	const [ stripeAccountDefaultCurrency ] = useAccountDefaultCurrency();
	const description = useMemo(
		() => getDescription( name, stripeAccountDefaultCurrency ),
		[ name, stripeAccountDefaultCurrency ]
	);

	if ( ! description ) return null;

	return (
		<HoverTooltip content={ description }>
			<div className="payment-method-checkbox__info">
				<VisuallyHidden>
					{ __(
						'Information about the payment method, click to expand',
						'woocommerce-payments'
					) }
				</VisuallyHidden>
				<Icon icon="info-outline" />
			</div>
		</HoverTooltip>
	);
};

const PaymentMethodCheckbox = ( {
	onChange,
	name,
	checked,
	fees,
	status,
	required,
	locked,
} ) => {
	const { accountFees } = useContext( WCPaySettingsContext );

	const handleChange = useCallback(
		( enabled ) => {
			// If the payment method checkbox is locked, reject any changes.
			if ( locked ) {
				return;
			}

			onChange( name, enabled );
		},
		[ locked, name, onChange ]
	);

	const disabled = upeCapabilityStatuses.INACTIVE === status;

	// Force uncheck payment method checkbox if it's checked and the payment method is disabled.
	useEffect( () => {
		if ( disabled && checked ) {
			handleChange( false );
		}
	}, [ disabled, checked, handleChange ] );

	const [ isManualCaptureEnabled ] = useManualCapture();
	const paymentMethod = PaymentMethodsMap[ name ];
	const needsOverlay =
		isManualCaptureEnabled && ! paymentMethod.allows_manual_capture;

	return (
		<li
			className={ classNames( 'payment-method-checkbox', {
				overlay: needsOverlay,
			} ) }
		>
			<LoadableCheckboxControl
				label={ paymentMethod.label }
				checked={ checked }
				disabled={ disabled || locked }
				onChange={ ( state ) => {
					handleChange( state );
				} }
				delayMsOnCheck={ 1500 }
				delayMsOnUncheck={ 0 }
				hideLabel={ true }
				isAllowingManualCapture={ paymentMethod.allows_manual_capture }
			/>
			<div className={ 'woocommerce-payments__payment-method-icon' }>
				{ paymentMethod.icon() }
			</div>
			<div className={ 'payment-method-checkbox__pills' }>
				<div className={ 'payment-method-checkbox__pills-left' }>
					<span className="payment-method-checkbox__label">
						{ paymentMethod.label }
						{ required && (
							<span className="payment-method-checkbox__required-label">
								{ __( 'Required', 'woocommerce-payments' ) }
							</span>
						) }
					</span>
					{ upeCapabilityStatuses.PENDING_APPROVAL === status && (
						<HoverTooltip
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
						</HoverTooltip>
					) }
					{ upeCapabilityStatuses.PENDING_VERIFICATION === status && (
						<HoverTooltip
							content={ sprintf(
								__(
									"%s won't be visible to your customers until you provide the required " +
										'information. Follow the instructions sent by our partner Stripe to %s.',
									'woocommerce-payments'
								),
								paymentMethod.label,
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
						</HoverTooltip>
					) }
					{ disabled && (
						<HoverTooltip
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
						</HoverTooltip>
					) }
				</div>
				<div className={ 'payment-method-checkbox__pills-right' }>
					<HoverTooltip
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
					</HoverTooltip>
					<PaymentMethodDescription name={ name } />
				</div>
			</div>
		</li>
	);
};

export default PaymentMethodCheckbox;
