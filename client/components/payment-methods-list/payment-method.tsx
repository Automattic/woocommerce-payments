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
import './payment-method.scss';
import { getDisabledTooltipContent } from './utils';
import PaymentMethodLabel from './payment-method-label';

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
	const [ isManualCaptureEnabled ] = useManualCapture();
	// APMs are disabled if they are inactive or if Progressive Onboarding is enabled and not yet complete.
	const disabled =
		[
			upeCapabilityStatuses.INACTIVE,
			upeCapabilityStatuses.PENDING_APPROVAL,
			upeCapabilityStatuses.PENDING_VERIFICATION,
		].includes( status ) ||
		( id !== 'card' && isPoEnabled && ! isPoComplete ) ||
		( isManualCaptureEnabled && ! isAllowingManualCapture );
	const {
		accountFees,
	}: { accountFees: Record< string, FeeStructure > } = useContext(
		WCPaySettingsContext
	);

	const needsOverlay = isSetupRequired || disabled;

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
					disabled={ disabled as boolean }
					locked={ locked }
					onChange={ handleChange }
					delayMsOnCheck={ 1500 }
					delayMsOnUncheck={ 0 }
					hideLabel
					disabledTooltip={
						getDisabledTooltipContent(
							isSetupRequired as boolean,
							setupTooltip as string,
							isManualCaptureEnabled as boolean,
							isAllowingManualCapture,
							status,
							label,
							id,
							wcpaySettings?.accountEmail ?? ''
						) as string
					}
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
					/>
				</div>
				<div className="payment-method__text">
					<div className="payment-method__label-container">
						<div className="payment-method__label payment-method__label-desktop">
							<PaymentMethodLabel
								label={ label }
								required={ required }
								status={ status }
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
