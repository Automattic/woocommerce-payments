/** @format */
/**
 * External dependencies
 */
import clsx from 'clsx';
import React, { useContext } from 'react';
import { CheckboxControl } from 'wcpay/components/wp-components-wrapped';

/**
 * Internal dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { HoverTooltip } from 'components/tooltip';
import { FeeStructure } from 'wcpay/types/fees';
import {
	formatMethodFeesDescription,
	formatMethodFeesTooltip,
} from 'wcpay/utils/account-fees';
import WCPaySettingsContext from '../wcpay-settings-context';
import Chip from 'wcpay/components/chip';
import Pill from 'wcpay/components/pill';
import './payment-method.scss';
import DuplicateNotice from 'wcpay/components/duplicate-notice';
import DuplicatedPaymentMethodsContext from '../settings-manager/duplicated-payment-methods-context';
import { PaymentMethodsLogos } from 'wcpay/checkout/blocks/payment-methods-logos/payment-methods-logos';
import Visa from 'assets/images/payment-method-icons/visa.svg?asset';
import Mastercard from 'assets/images/payment-method-icons/mastercard.svg?asset';
import Amex from 'assets/images/payment-method-icons/amex.svg?asset';
import Discover from 'assets/images/payment-method-icons/discover.svg?asset';
import Diners from 'assets/images/cards/diners.svg?asset';
import Jcb from 'assets/images/payment-method-icons/jcb.svg?asset';
import Cartebancaire from 'assets/images/cards/cartes_bancaires.svg?asset';
import UnionPay from 'assets/images/cards/unionpay.svg?asset';
import PAYMENT_METHOD_IDS from 'wcpay/constants/payment-method';
import usePaymentMethodAvailability from './use-payment-method-availability';
import InlineNotice from 'wcpay/components/inline-notice';
import { useEnabledPaymentMethodIds } from 'wcpay/data';

interface PaymentMethodProps {
	id: string;
	label: string;
	// eslint-disable-next-line @typescript-eslint/naming-convention
	Icon: () => JSX.Element | null;
	description: string;
	onCheckClick: ( id: string ) => void;
	onUncheckClick: ( id: string ) => void;
	className?: string;
	locked: boolean;
}

const PaymentMethodLabel = ( {
	id,
	label,
}: {
	id: string;
	label: string;
} ): React.ReactElement => {
	const { chip, chipType = 'warning' } = usePaymentMethodAvailability( id );

	return (
		<>
			{ label }
			{ PAYMENT_METHOD_IDS.CARD === id && (
				<span className="payment-method__required-label">
					{ '(' + __( 'Required', 'woocommerce-payments' ) + ')' }
				</span>
			) }
			{ chip && <Chip message={ chip } type={ chipType } /> }
		</>
	);
};

// Define the supported card brands
const cardBrands = [
	{ name: 'visa', component: Visa },
	{ name: 'mastercard', component: Mastercard },
	{ name: 'amex', component: Amex },
	{ name: 'discover', component: Discover },
	{ name: 'diners', component: Diners },
	{ name: 'jcb', component: Jcb },
	{ name: 'cartes_bancaires', component: Cartebancaire },
	{ name: 'unionpay', component: UnionPay },
];

const PaymentMethod = ( {
	id,
	label,
	Icon = () => null,
	description,
	onCheckClick,
	onUncheckClick,
	className,
	locked,
}: PaymentMethodProps ): React.ReactElement => {
	// APMs are not actionable if they are inactive or if Progressive Onboarding is enabled and not yet complete.
	const {
		isActionable,
		notice,
		noticeType = 'warning',
	} = usePaymentMethodAvailability( id );
	const [ enabledMethodIds ] = useEnabledPaymentMethodIds();

	const {
		accountFees,
	}: { accountFees?: Record< string, FeeStructure > } = useContext(
		WCPaySettingsContext
	);

	const {
		duplicates,
		dismissedDuplicateNotices,
		setDismissedDuplicateNotices,
	} = useContext( DuplicatedPaymentMethodsContext );
	const isDuplicate = Object.keys( duplicates ).includes( id );

	const handleChange = ( newStatus: boolean ) => {
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
		<li className={ clsx( 'payment-method__list-item', className ) }>
			<div className="payment-method">
				<div className="payment-method__checkbox">
					<CheckboxControl
						label={ label }
						checked={ enabledMethodIds.includes( id ) }
						disabled={ ! isActionable || locked }
						onChange={ handleChange }
						__nextHasNoMarginBottom
					/>
				</div>
				<div className="payment-method__text-container">
					<div className="payment-method__icon">
						<Icon />
					</div>
					<div className="payment-method__label payment-method__label-mobile">
						<PaymentMethodLabel label={ label } id={ id } />
					</div>
					<div className="payment-method__text">
						<div className="payment-method__label-container">
							<div className="payment-method__label payment-method__label-desktop">
								<PaymentMethodLabel label={ label } id={ id } />
							</div>
							<div className="payment-method__description">
								{ description }
							</div>
							{ id === PAYMENT_METHOD_IDS.CARD && (
								<div className="payment-method__supported-cards">
									<PaymentMethodsLogos
										paymentMethods={ cardBrands }
										maxElements={ 8 }
										breakpointConfigs={ [
											{ breakpoint: 480, maxElements: 8 },
											{ breakpoint: 768, maxElements: 8 },
										] }
									/>
								</div>
							) }
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
			{ notice && (
				<InlineNotice status={ noticeType } isDismissible={ false }>
					{ notice }
				</InlineNotice>
			) }
			{ isDuplicate && ! notice && (
				<DuplicateNotice
					paymentMethod={ id }
					gatewaysEnablingPaymentMethod={ duplicates[ id ] }
					dismissedNotices={ dismissedDuplicateNotices }
					setDismissedDuplicateNotices={
						setDismissedDuplicateNotices
					}
				/>
			) }
		</li>
	);
};

export default PaymentMethod;
