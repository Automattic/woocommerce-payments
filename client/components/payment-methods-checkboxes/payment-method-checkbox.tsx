/** @format */
/**
 * External dependencies
 */
import { Icon, VisuallyHidden } from '@wordpress/components';
import { useCallback } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import classNames from 'classnames';
import React, { useContext, useEffect } from 'react';

/**
 * Internal dependencies
 */
import LoadableCheckboxControl from 'components/loadable-checkbox';
import { HoverTooltip } from 'components/tooltip';
import { upeCapabilityStatuses } from 'wcpay/additional-methods-setup/constants';
import { useManualCapture, useAccountDomesticCurrency } from 'wcpay/data';
import { FeeStructure } from 'wcpay/types/fees';
import PaymentMethodsMap from '../../payment-methods-map';
import WCPaySettingsContext from '../../settings/wcpay-settings-context';
import {
	formatMethodFeesDescription,
	formatMethodFeesTooltip,
} from '../../utils/account-fees';
import PaymentMethodDisabledTooltip from '../payment-method-disabled-tooltip';
import Pill from '../pill';
import './payment-method-checkbox.scss';

type PaymentMethodProps = {
	name: string;
};

const getDescription = ( name: string, currency: string ) => {
	let { description, allows_pay_later: allowsPayLater } = PaymentMethodsMap[
		name
	];

	if ( ! description ) return null;

	if ( allowsPayLater ) {
		description = sprintf( description, currency.toUpperCase() );
	}

	return description;
};

const PaymentMethodDescription: React.FC< PaymentMethodProps > = ( {
	name,
} ) => {
	const [ stripeAccountDomesticCurrency ] = useAccountDomesticCurrency();
	const description = getDescription(
		name,
		stripeAccountDomesticCurrency as string
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

type PaymentMethodCheckboxProps = {
	onChange: ( name: string, enabled: boolean ) => void;
	name: string;
	checked: boolean;
	fees: string;
	status: string;
	required: boolean;
	locked: boolean;
};

const PaymentMethodCheckbox: React.FC< PaymentMethodCheckboxProps > = ( {
	onChange,
	name,
	checked,
	fees,
	status,
	required,
	locked,
} ) => {
	const {
		accountFees,
	}: { accountFees: Record< string, FeeStructure > } = useContext(
		WCPaySettingsContext
	);

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
				onChange={ ( state: boolean ) => {
					handleChange( state );
				} }
				delayMsOnCheck={ 1500 }
				delayMsOnUncheck={ 0 }
				hideLabel={ true }
				isAllowingManualCapture={ paymentMethod.allows_manual_capture }
			/>
			<div className={ 'woocommerce-payments__payment-method-icon' }>
				{ paymentMethod.icon( {} ) }
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
						<PaymentMethodDisabledTooltip id={ name }>
							<Pill className={ 'payment-status-' + status }>
								{ __(
									'More information needed',
									'woocommerce-payments'
								) }
							</Pill>
						</PaymentMethodDisabledTooltip>
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
