/** @format */
/**
 * External dependencies
 */
import classNames from 'classnames';
import React, { useContext } from 'react';

/**
 * Internal dependencies
 */
import interpolateComponents from '@automattic/interpolate-components';
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
import Chip from '../chip';
import LoadableCheckboxControl from '../loadable-checkbox';
import { getDocumentationUrlForDisabledPaymentMethod } from '../payment-method-disabled-tooltip';
import Pill from '../pill';
import InlineNotice from '../inline-notice';
import './payment-method.scss';
import DuplicateNotice from '../duplicate-notice';
import DuplicatedPaymentMethodsContext from 'wcpay/settings/settings-manager/duplicated-payment-methods-context';

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
}: {
	label: string;
	required: boolean;
	status: string;
	disabled: boolean;
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
				<Chip
					message={ __( 'Pending approval', 'woocommerce-payments' ) }
					type="warning"
				/>
			) }
			{ upeCapabilityStatuses.REJECTED === status && (
				<Chip
					message={ __( 'Rejected', 'woocommerce-payments' ) }
					type="alert"
				/>
			) }
			{ upeCapabilityStatuses.PENDING_VERIFICATION === status && (
				<Chip
					message={ __(
						'Pending activation',
						'woocommerce-payments'
					) }
					type="warning"
				/>
			) }
			{ disabled && (
				<Chip
					message={ __(
						'More information needed',
						'woocommerce-payments'
					) }
					type="warning"
				/>
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
	// We want to show a tooltip if PO is enabled and not yet complete. (We make an exception to not show this for card payments).
	const isPoInProgress =
		isPoEnabled &&
		! isPoComplete &&
		status !== upeCapabilityStatuses.ACTIVE;

	// APMs are disabled if they are inactive or if Progressive Onboarding is enabled and not yet complete.
	const disabled =
		upeCapabilityStatuses.INACTIVE === status || isPoInProgress;
	const {
		accountFees,
	}: { accountFees: Record< string, FeeStructure > } = useContext(
		WCPaySettingsContext
	);
	const [ isManualCaptureEnabled ] = useManualCapture();

	const needsMoreInformation = [
		upeCapabilityStatuses.INACTIVE,
		upeCapabilityStatuses.PENDING_APPROVAL,
		upeCapabilityStatuses.PENDING_VERIFICATION,
	].includes( status );

	const needsAttention =
		needsMoreInformation ||
		isPoInProgress ||
		upeCapabilityStatuses.REJECTED === status;
	const shouldDisplayNotice = id === 'sofort';
	const {
		duplicates,
		dismissedDuplicateNotices,
		setDismissedDuplicateNotices,
	} = useContext( DuplicatedPaymentMethodsContext );
	const isDuplicate = duplicates.includes( id );

	const needsOverlay =
		( isManualCaptureEnabled && ! isAllowingManualCapture ) ||
		isSetupRequired ||
		needsAttention;

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

	const getTooltipContent = ( paymentMethodId: string ) => {
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

		if ( upeCapabilityStatuses.REJECTED === status ) {
			return interpolateComponents( {
				// translators: {{contactSupportLink}}: placeholders are opening and closing anchor tags.
				mixedString: __(
					'Please {{contactSupportLink}}contact support{{/contactSupportLink}} for more details.',
					'woocommerce-payments'
				),
				components: {
					contactSupportLink: (
						// eslint-disable-next-line jsx-a11y/anchor-has-content
						<a
							target="_blank"
							rel="noreferrer"
							title={ __(
								'Contact Support',
								'woocommerce-payments'
							) }
							href={
								'https://woocommerce.com/my-account/contact-support/'
							}
						/>
					),
				},
			} );
		}

		if ( isSetupRequired ) {
			return setupTooltip;
		}

		if ( needsAttention ) {
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
							href={
								isPoInProgress
									? 'https://woocommerce.com/document/woopayments/startup-guide/gradual-signup/#additional-payment-methods'
									: getDocumentationUrlForDisabledPaymentMethod(
											paymentMethodId
									  )
							}
						/>
					),
				},
			} );
		}

		return sprintf(
			/* translators: %s: a payment method name. */
			__(
				'%s is not available to your customers when the "manual capture" setting is enabled.',
				'woocommerce-payments'
			),
			label
		);
	};

	return (
		<li
			className={ classNames(
				'payment-method__list-item',
				{ 'has-icon-border': id !== 'card' },
				{ overlay: needsOverlay },
				className
			) }
		>
			<div className="payment-method">
				<div className="payment-method__checkbox">
					<LoadableCheckboxControl
						label={ label }
						checked={ checked }
						disabled={ disabled || locked }
						onChange={ handleChange }
						hideLabel
						isAllowingManualCapture={ isAllowingManualCapture }
						isSetupRequired={ isSetupRequired }
						setupTooltip={ getTooltipContent( id ) as any }
						needsAttention={ needsAttention }
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
			</div>
			{ shouldDisplayNotice && (
				<InlineNotice
					status="warning"
					icon={ true }
					isDismissible={ false }
					className="sofort__notice"
				>
					<span>
						{ __(
							'Support for Sofort is ending soon. ',
							'woocommerce-payments'
						) }
						<a
							// eslint-disable-next-line max-len
							href="https://woocommerce.com/document/woopayments/payment-methods/additional-payment-methods/#sofort-migration"
							target="_blank"
							rel="external noreferrer noopener"
						>
							{ __( 'Learn more', 'woocommerce-payments' ) }
						</a>
					</span>
				</InlineNotice>
			) }
			{ isDuplicate && (
				<DuplicateNotice
					paymentMethod={ id }
					dismissedDuplicateNotices={ dismissedDuplicateNotices }
					setDismissedDuplicateNotices={
						setDismissedDuplicateNotices
					}
				/>
			) }
		</li>
	);
};

export default PaymentMethod;
