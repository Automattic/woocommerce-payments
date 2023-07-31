/** @format */
/**
 * External dependencies
 */
import React, { useContext, useEffect } from 'react';
import { Icon, VisuallyHidden } from '@wordpress/components';
import { useCallback } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import classNames from 'classnames';
import interpolateComponents from '@automattic/interpolate-components';

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
import { useManualCapture } from 'wcpay/data';
import { getDocumentationUrlForDisabledPaymentMethod } from 'components/payment-methods-list/utils';
import { FeeStructure } from 'wcpay/types/fees';

const PaymentMethodDescription = ( { name }: { name: string } ) => {
	const description = PaymentMethodsMap[ name ]?.description;
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
}: {
	onChange: ( name: string, enabled: boolean ) => void;
	name: string;
	checked: boolean;
	fees: string;
	status: string;
	required: boolean;
	locked: boolean;
} ): React.ReactElement => {
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
				onChange={ ( state: string ) => {
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
						<HoverTooltip
							content={ interpolateComponents( {
								// translators: {{learnMoreLink}}: placeholders are opening and closing anchor tags.
								mixedString: __(
									'We need more information from you to enable this method. ' +
										'{{learnMoreLink}}Learn more.{{/learnMoreLink}}',
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
												name
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
