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
import { getDisabledTooltipContent } from 'components/payment-methods-list/utils';
import Pill from '../pill';
import { getPaymentMethodDescription } from 'wcpay/utils/payment-methods';
import './payment-method-checkbox.scss';
import PaymentMethodLabel from '../payment-methods-list/payment-method-label';

interface PaymentMethodProps {
	name: string;
}

const PaymentMethodDescription: React.FC< PaymentMethodProps > = ( {
	name,
} ) => {
	const [ stripeAccountDomesticCurrency ] = useAccountDomesticCurrency();
	const description = getPaymentMethodDescription(
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

interface PaymentMethodCheckboxProps {
	onChange: ( name: string, enabled: boolean ) => void;
	name: string;
	checked: boolean;
	fees: string;
	status: string;
	required: boolean;
	locked: boolean;
	isPoEnabled: boolean;
	isPoComplete: boolean;
}

const PaymentMethodCheckbox: React.FC< PaymentMethodCheckboxProps > = ( {
	onChange,
	name,
	checked,
	fees,
	status,
	required,
	locked,
	isPoEnabled,
	isPoComplete,
} ) => {
	// TODO margin of the chip.
	// TODO check nofitication for BNPL.
	// TODO required label.
	// TODO check isSetupRequired. Does it apply?
	// TODO mobile view.
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

	const [ isManualCaptureEnabled ] = useManualCapture();
	const {
		label,
		icon,
		allows_manual_capture: allowsManualCapture,
		setup_required: isSetupRequired = false,
		setup_tooltip: setupTooltip = '',
	} = PaymentMethodsMap[ name ];

	const disabled =
		[
			upeCapabilityStatuses.INACTIVE,
			upeCapabilityStatuses.PENDING_APPROVAL,
			upeCapabilityStatuses.PENDING_VERIFICATION,
		].includes( status ) ||
		( isManualCaptureEnabled && ! allowsManualCapture ) ||
		( name !== 'card' && isPoEnabled && ! isPoComplete ) ||
		isSetupRequired;

	// Force uncheck payment method checkbox if it's checked and the payment method is disabled.
	useEffect( () => {
		if ( disabled && checked ) {
			handleChange( false );
		}
	}, [ disabled, checked, handleChange ] );

	return (
		<li
			className={ classNames( 'payment-method-checkbox', {
				overlay: disabled || isSetupRequired,
			} ) }
		>
			<LoadableCheckboxControl
				label={ label }
				checked={ checked }
				disabled={ disabled as boolean }
				locked={ locked }
				onChange={ ( state: boolean ) => {
					handleChange( state );
				} }
				delayMsOnCheck={ 1500 }
				delayMsOnUncheck={ 0 }
				hideLabel={ true }
				disabledTooltip={
					getDisabledTooltipContent(
						isSetupRequired,
						setupTooltip,
						isManualCaptureEnabled as boolean,
						allowsManualCapture,
						status,
						label,
						name,
						wcpaySettings?.accountEmail ?? ''
					) as string
				}
			/>
			<div className={ 'woocommerce-payments__payment-method-icon' }>
				{ icon( {} ) }
			</div>
			<div className={ 'payment-method-checkbox__pills' }>
				<div className={ 'payment-method-checkbox__pills-left' }>
					<span className="payment-method-checkbox__label">
						<PaymentMethodLabel
							label={ label }
							required={ required }
							status={ status }
						/>
					</span>
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
