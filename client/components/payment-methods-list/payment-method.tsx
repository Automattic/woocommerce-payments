/** @format */
/**
 * External dependencies
 */
import classNames from 'classnames';
import React, { useContext } from 'react';
import interpolateComponents from '@automattic/interpolate-components';

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
import Chip from '../chip';
import PAYMENT_METHOD_IDS from 'wcpay/payment-methods/constants';
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

// Utility function to calculate the chip message based on status of the payment method.
const getChipMessage = ( status: string ): string => {
	switch ( status ) {
		case upeCapabilityStatuses.PENDING_APPROVAL:
			return __( 'Pending approval', 'woocommerce-payments' );
		case upeCapabilityStatuses.PENDING_VERIFICATION:
			return __( 'Pending activation', 'woocommerce-payments' );
		case upeCapabilityStatuses.INACTIVE:
			return __( 'More information needed', 'woocommerce-payments' );
		default:
			return '';
	}
};

const PaymentMethodLabel = ( {
	label,
	required,
	status,
}: {
	label: string;
	required: boolean;
	status: string;
} ): React.ReactElement => {
	return (
		<>
			{ label }
			{ required && (
				<span className="payment-method__required-label">
					{ '(' + __( 'Required', 'woocommerce-payments' ) + ')' }
				</span>
			) }
			{ <Chip message={ getChipMessage( status ) } type="warning" /> }
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
	const [ isManualCaptureEnabled ] = useManualCapture();
	// APMs are disabled if they are inactive or if Progressive Onboarding is enabled and not yet complete.
	const disabled =
		upeCapabilityStatuses.INACTIVE === status ||
		upeCapabilityStatuses.PENDING_APPROVAL === status ||
		upeCapabilityStatuses.PENDING_VERIFICATION === status ||
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

	const getDocumentationUrlForDisabledPaymentMethod = (
		paymentMethodId: string
	): string => {
		const DocumentationUrlForDisabledPaymentMethod = {
			DEFAULT:
				'https://woocommerce.com/document/woopayments/payment-methods/additional-payment-methods/#method-cant-be-enabled',
			BNPLS:
				'https://woocommerce.com/document/woopayments/payment-methods/buy-now-pay-later/#contact-support',
		};
		let url;
		switch ( paymentMethodId ) {
			case PAYMENT_METHOD_IDS.AFTERPAY_CLEARPAY:
			case PAYMENT_METHOD_IDS.AFFIRM:
				url = DocumentationUrlForDisabledPaymentMethod.BNPLS;
				break;
			default:
				url = DocumentationUrlForDisabledPaymentMethod.DEFAULT;
		}
		return url;
	};

	const getDisabledTooltipContent = () => {
		if ( isSetupRequired ) {
			return setupTooltip;
		}

		if ( isManualCaptureEnabled && ! isAllowingManualCapture ) {
			return sprintf(
				__(
					'%s is not available to your customers when the "manual capture" setting is enabled.',
					'woocommerce-payments'
				),
				label
			);
		}

		if ( upeCapabilityStatuses.PENDING_APPROVAL === status ) {
			return __(
				'This payment method is pending approval. Once approved, you will be able to use it.',
				'woocommerce-payments'
			);
		}

		if ( upeCapabilityStatuses.PENDING_VERIFICATION === status ) {
			return sprintf(
				__(
					"%s won't be visible to your customers until you provide the required " +
						'information. Follow the instructions sent by our partner Stripe to %s.',
					'woocommerce-payments'
				),
				label,
				wcpaySettings?.accountEmail ?? ''
			);
		}

		if ( disabled && upeCapabilityStatuses.INACTIVE === status ) {
			return interpolateComponents( {
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
							title={ __(
								'Learn more about enabling payment methods',
								'woocommerce-payments'
							) }
							/* eslint-disable-next-line max-len */
							href={ getDocumentationUrlForDisabledPaymentMethod(
								id
							) }
						/>
					),
				},
			} );
		}

		return __( 'Disabled', 'woocommerce-payments' );
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
					onChange={ handleChange }
					delayMsOnCheck={ 1500 }
					delayMsOnUncheck={ 0 }
					hideLabel
					disabledTooltip={ getDisabledTooltipContent() as string }
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
