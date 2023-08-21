/** @format */
/**
 * External dependencies
 */
import classNames from 'classnames';
import React, { useContext } from 'react';

/**
 * Internal dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { HoverTooltip } from 'components/tooltip';
import { upeCapabilityStatuses } from 'wcpay/additional-methods-setup/constants';
import { useManualCapture } from 'wcpay/data';
import { FeeStructure } from 'wcpay/types/fees';
import {
	formatMethodFeesDescription,
	formatMethodFeesTooltip,
} from 'wcpay/utils/account-fees';
import WCPaySettingsContext from '../../settings/wcpay-settings-context';
import LoadableCheckboxControl from '../loadable-checkbox';
import Pill from '../pill';
import PaymentMethodDisabledTooltip from '../payment-method-disabled-tooltip';
import './payment-method.scss';

interface PaymentMethodProps {
	id: string;
	label: string;
	// eslint-disable-next-line @typescript-eslint/naming-convention
	Icon: () => JSX.Element | null;
	description: string;
	status: string;
	checked: boolean;
	onCheckClick: ( id: string ) => void;
	onUncheckClick: ( id: string ) => void;
	className?: string;
	isAllowingManualCapture: boolean;
	isSetupRequired?: boolean;
	setupTooltip?: string;
	required: boolean;
	locked: boolean;
	isPoEnabled: boolean;
	isPoComplete: boolean;
}

const PaymentMethodLabel = ( {
	label,
	required,
	status,
	disabled,
	id,
}: {
	label: string;
	required: boolean;
	status: string;
	disabled: boolean;
	id: string;
} ): React.ReactElement => {
	return (
		<>
			{ label }
			{ required && (
				<span className="payment-method__required-label">
					{ '(' + __( 'Required', 'woocommerce-payments' ) + ')' }
				</span>
			) }
			{ upeCapabilityStatuses.PENDING_APPROVAL === status && (
				<HoverTooltip
					content={ __(
						'This payment method is pending approval. Once approved, you will be able to use it.',
						'woocommerce-payments'
					) }
				>
					<Pill className={ 'payment-status-pending-approval' }>
						{ __( 'Pending approval', 'woocommerce-payments' ) }
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
						label,
						wcpaySettings?.accountEmail ?? ''
					) }
				>
					<Pill className={ 'payment-status-pending-verification' }>
						{ __( 'Pending activation', 'woocommerce-payments' ) }
					</Pill>
				</HoverTooltip>
			) }
			{ disabled && (
				<PaymentMethodDisabledTooltip id={ id }>
					<Pill className={ 'payment-status-' + status }>
						{ __(
							'More information needed',
							'woocommerce-payments'
						) }
					</Pill>
				</PaymentMethodDisabledTooltip>
			) }
		</>
	);
};

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
	isAllowingManualCapture,
	isSetupRequired,
	setupTooltip,
	required,
	locked,
	isPoEnabled,
	isPoComplete,
}: PaymentMethodProps ): React.ReactElement => {
	// APMs are disabled if they are inactive or if Progressive Onboarding is enabled and not yet complete.
	const disabled =
		upeCapabilityStatuses.INACTIVE === status ||
		( id !== 'card' && isPoEnabled && ! isPoComplete );
	const {
		accountFees,
	}: { accountFees: Record< string, FeeStructure > } = useContext(
		WCPaySettingsContext
	);
	const [ isManualCaptureEnabled ] = useManualCapture();

	const needsOverlay =
		( isManualCaptureEnabled && ! isAllowingManualCapture ) ||
		isSetupRequired;

	// As the JCB is not a separate payment method we fallback to card.
	if ( id === 'jcb' ) {
		id = 'card';
	}

	const handleChange = ( newStatus: string ) => {
		// If the payment method control is locked, reject any changes.
		if ( locked ) {
			return;
		}

		if ( newStatus ) {
			return onCheckClick( id );
		}
		return onUncheckClick( id );
	};

	return (
		<li
			className={ classNames(
				'payment-method',
				{ 'has-icon-border': id !== 'card' },
				{ overlay: needsOverlay },
				className
			) }
		>
			<div className="payment-method__checkbox">
				<LoadableCheckboxControl
					label={ label }
					checked={ checked }
					disabled={ disabled || locked }
					onChange={ handleChange }
					delayMsOnCheck={ 1500 }
					delayMsOnUncheck={ 0 }
					hideLabel
					isAllowingManualCapture={ isAllowingManualCapture }
					isSetupRequired={ isSetupRequired }
					setupTooltip={ setupTooltip }
				/>
			</div>
			<div className="payment-method__text-container">
				<div className="payment-method__icon">
					<Icon />
				</div>
				<div className="payment-method__label payment-method__label-mobile">
					<PaymentMethodLabel
						label={ label }
						required={ required }
						status={ status }
						disabled={ disabled }
						id={ id }
					/>
				</div>
				<div className="payment-method__text">
					<div className="payment-method__label-container">
						<div className="payment-method__label payment-method__label-desktop">
							<PaymentMethodLabel
								label={ label }
								required={ required }
								status={ status }
								disabled={ disabled }
								id={ id }
							/>
						</div>
						<div className="payment-method__description">
							{ description }
						</div>
					</div>
					{ accountFees && accountFees[ id ] && (
						<div className="payment-method__fees">
							<HoverTooltip
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
							</HoverTooltip>
						</div>
					) }
				</div>
			</div>
		</li>
	);
};

export default PaymentMethod;
