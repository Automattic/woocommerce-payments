/** @format */
/**
 * External dependencies
 */
import React, { useContext } from 'react';
import classNames from 'classnames';
import interpolateComponents from '@automattic/interpolate-components';

/**
 * Internal dependencies
 */
import Pill from '../pill';
import { HoverTooltip } from 'components/tooltip';
import WCPaySettingsContext from '../../settings/wcpay-settings-context';
import LoadableCheckboxControl from '../loadable-checkbox';
import { __, sprintf } from '@wordpress/i18n';
import { upeCapabilityStatuses } from 'wcpay/additional-methods-setup/constants';
import {
	formatMethodFeesDescription,
	formatMethodFeesTooltip,
} from 'wcpay/utils/account-fees';
import './payment-method.scss';
import { useManualCapture } from 'wcpay/data';
import { getDocumentationUrlForDisabledPaymentMethod } from './utils';
import { FeeStructure } from 'wcpay/types/fees';

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
	required,
	locked,
}: {
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
	required: boolean;
	locked: boolean;
} ): React.ReactElement => {
	const disabled = upeCapabilityStatuses.INACTIVE === status;
	const {
		accountFees,
	}: { accountFees: Record< string, FeeStructure > } = useContext(
		WCPaySettingsContext
	);
	const [ isManualCaptureEnabled ] = useManualCapture();

	const needsOverlay = isManualCaptureEnabled && ! isAllowingManualCapture;

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
				/>
			</div>
			<div className="payment-method__icon">
				<Icon />
			</div>
			<div className="payment-method__text">
				<div className="payment-method__label-container">
					<div className="payment-method__label">
						{ label }
						{ required && (
							<span className="payment-method__required-label">
								{ '(' +
									__( 'Required', 'woocommerce-payments' ) +
									')' }
							</span>
						) }
						{ upeCapabilityStatuses.PENDING_APPROVAL === status && (
							<HoverTooltip
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
							</HoverTooltip>
						) }
						{ upeCapabilityStatuses.PENDING_VERIFICATION ===
							status && (
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
								content={ interpolateComponents( {
									//TODO: translators hint.
									mixedString: __(
										'We need more information from you to enable this method. ' +
											'{{learnMoreLink}}Learn more.{{/learnMoreLink}},',
										'woocommerce-payments'
									),
									components: {
										learnMoreLink: (
											// eslint-disable-next-line jsx-a11y/anchor-has-content
											<a
												target="_blank"
												rel="noreferrer"
												/* eslint-disable-next-line max-len */
												href={ getDocumentationUrlForDisabledPaymentMethod(
													id
												) }
											/>
										),
									},
								} ) }
							>
								<Pill className={ 'payment-status-' + status }>
									{ __(
										'More information needed',
										'woocommerce-payments'
									) }
								</Pill>
							</HoverTooltip>
						) }
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
		</li>
	);
};

export default PaymentMethod;
