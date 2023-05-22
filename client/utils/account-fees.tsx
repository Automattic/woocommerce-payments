/** @format */

/**
 * External depencencies
 */
import { __, sprintf } from '@wordpress/i18n';
import interpolateComponents from '@automattic/interpolate-components';
import './account-fees.scss';

/**
 * Internal dependencies
 */
import { formatCurrency } from 'utils/currency';
import { formatFee } from 'utils/fees';
import React from 'react';
import { BaseFee, DiscountFee, FeeStructure } from 'wcpay/types/fees';
import { PaymentMethod } from 'wcpay/types/payment-methods';
import { createInterpolateElement } from '@wordpress/element';

const countryFeeStripeDocsBaseLink =
	'https://woocommerce.com/document/payments/faq/fees/#';
const countryFeeStripeDocsBaseLinkNoCountry =
	'https://woocommerce.com/document/payments/faq/fees';
const countryFeeStripeDocsSectionNumbers: Record< string, string > = {
	AU: 'australia',
	AT: 'austria',
	BE: 'belgium',
	BG: 'bulgaria',
	CA: 'canada',
	CY: 'cyprus',
	FR: 'france',
	LU: 'luxembourg',
	DE: 'germany',
	DK: 'denmark',
	EE: 'estonia',
	FI: 'finland',
	GR: 'greece',
	HK: 'hong-kong',
	HR: 'croatia',
	IE: 'ireland',
	IT: 'italy',
	LT: 'lithuania',
	LV: 'latvia',
	MT: 'malta',
	NL: 'netherlands',
	NO: 'norway',
	NZ: 'new-zealand',
	PL: 'poland',
	PT: 'portugal',
	SG: 'singapore',
	SI: 'slovenia',
	SK: 'slovakia',
	ES: 'spain',
	CH: 'switzerland',
	UK: 'united-kingdom',
	US: 'united-states',
	RO: 'romania',
};

const stripeFeeSectionExistsForCountry = ( country: string ): boolean => {
	return countryFeeStripeDocsSectionNumbers.hasOwnProperty( country );
};

const getStripeFeeSectionUrl = ( country: string ): string => {
	return sprintf(
		'%s%s',
		countryFeeStripeDocsBaseLink,
		countryFeeStripeDocsSectionNumbers[ country ]
	);
};

const getFeeDescriptionString = ( fee: BaseFee ): string => {
	if ( fee.fixed_rate && fee.percentage_rate ) {
		return sprintf(
			'%1$f%% + %2$s',
			formatFee( fee.percentage_rate ),
			formatCurrency( fee.fixed_rate, fee.currency )
		);
	} else if ( fee.fixed_rate ) {
		return sprintf(
			'%2$s',
			formatFee( fee.percentage_rate ),
			formatCurrency( fee.fixed_rate, fee.currency )
		);
	} else if ( fee.percentage_rate ) {
		return sprintf(
			'%1$f%%',
			formatFee( fee.percentage_rate ),
			formatCurrency( fee.fixed_rate, fee.currency )
		);
	}
	return '';
};

export const getCurrentBaseFee = (
	accountFees: FeeStructure
): BaseFee | DiscountFee => {
	return accountFees.discount.length
		? accountFees.discount[ 0 ]
		: accountFees.base;
};

export const formatMethodFeesTooltip = (
	accountFees: FeeStructure
): JSX.Element => {
	if ( ! accountFees ) return <></>;
	const currentBaseFee = getCurrentBaseFee( accountFees );
	// If the current fee doesn't have a fixed or percentage rate, use the base fee's rate. Eg. when there is a promotional discount fee applied. Use this to calculate the total fee too.
	const currentFeeWithBaseFallBack = currentBaseFee.percentage_rate
		? currentBaseFee
		: accountFees.base;

	const total = {
		percentage_rate:
			currentFeeWithBaseFallBack.percentage_rate +
			accountFees.additional.percentage_rate +
			accountFees.fx.percentage_rate,
		fixed_rate:
			currentFeeWithBaseFallBack.fixed_rate +
			accountFees.additional.fixed_rate +
			accountFees.fx.fixed_rate,
		currency: accountFees.base.currency,
	};

	const hasFees = ( fee: BaseFee ): boolean => {
		return fee.fixed_rate > 0.0 || fee.percentage_rate > 0.0;
	};

	return (
		<div className={ 'wcpay-fees-tooltip' }>
			<div>
				<div>Base fee</div>
				<div>
					{ getFeeDescriptionString( currentFeeWithBaseFallBack ) }
				</div>
			</div>
			{ hasFees( accountFees.additional ) ? (
				<div>
					<div>International payment method fee</div>
					<div>
						{ getFeeDescriptionString( accountFees.additional ) }
					</div>
				</div>
			) : (
				''
			) }
			{ hasFees( accountFees.fx ) ? (
				<div>
					<div>Foreign exchange fee</div>
					<div>{ getFeeDescriptionString( accountFees.fx ) }</div>
				</div>
			) : (
				''
			) }
			<div>
				<div>Total per transaction</div>
				<div className={ 'wcpay-fees-tooltip__bold' }>
					{ getFeeDescriptionString( total ) }
				</div>
			</div>
			{ wcpaySettings &&
			wcpaySettings.connect &&
			wcpaySettings.connect.country ? (
				<div className={ 'wcpay-fees-tooltip__hint-text' }>
					<span>
						{ stripeFeeSectionExistsForCountry(
							wcpaySettings.connect.country
						)
							? interpolateComponents( {
									mixedString: __(
										'{{linkToStripePage /}} about WooCommerce Payments Fees in your country',
										'woocommerce-payments'
									),
									components: {
										linkToStripePage: (
											<a
												href={ getStripeFeeSectionUrl(
													wcpaySettings.connect
														.country
												) }
												target={ '_blank' }
												rel={ 'noreferrer' }
											>
												{ __(
													'Learn more',
													'woocommerce-payments'
												) }
											</a>
										),
									},
							  } )
							: interpolateComponents( {
									mixedString: __(
										'{{linkToStripePage /}} about WooCommerce Payments Fees',
										'woocommerce-payments'
									),
									components: {
										linkToStripePage: (
											<a
												href={
													countryFeeStripeDocsBaseLinkNoCountry
												}
												target={ '_blank' }
												rel={ 'noreferrer' }
											>
												{ __(
													'Learn more',
													'woocommerce-payments'
												) }
											</a>
										),
									},
							  } ) }
					</span>
				</div>
			) : (
				''
			) }
		</div>
	);
};

export const formatAccountFeesDescription = (
	accountFees: FeeStructure,
	customFormats = {}
): string | JSX.Element => {
	const defaultFee = {
		fixed_rate: 0,
		percentage_rate: 0,
		currency: 'USD',
	};
	const baseFee = accountFees.base;
	const additionalFee = accountFees.additional ?? defaultFee;
	const fxFee = accountFees.fx ?? defaultFee;
	const currentBaseFee = getCurrentBaseFee( accountFees );

	// Default formats will be used if no matching field was passed in the `formats` parameter.
	const formats = {
		/* translators: %1: Percentage part of the fee. %2: Fixed part of the fee */
		fee: __( '%1$f%% + %2$s per transaction', 'woocommerce-payments' ),
		/* translators: %f percentage discount to apply */
		discount: __( '(%f%% discount)', 'woocommerce-payments' ),
		displayBaseFeeIfDifferent: true,
		...customFormats,
	};

	// Some payment methods doesn't have base percentage rate. In this case, the lowest rate will be shown as a start value
	let displayFeePercentageRate = baseFee.percentage_rate;
	if ( displayFeePercentageRate <= 0 ) {
		displayFeePercentageRate =
			additionalFee.percentage_rate < fxFee.percentage_rate
				? additionalFee.percentage_rate
				: fxFee.percentage_rate;
	}
	const feeDescription = sprintf(
		formats.fee,
		formatFee( displayFeePercentageRate ),
		formatCurrency( baseFee.fixed_rate, baseFee.currency )
	);
	const isFormattingWithDiscount =
		currentBaseFee.percentage_rate !== baseFee.percentage_rate ||
		currentBaseFee.fixed_rate !== baseFee.fixed_rate ||
		currentBaseFee.currency !== baseFee.currency;
	if ( isFormattingWithDiscount ) {
		const discountFee = currentBaseFee as DiscountFee;
		// TODO: Figure out how the UI should work if there are several "discount" fees stacked.
		let percentage, fixed;

		if ( discountFee.discount ) {
			// Proper discount fee (XX% off)
			percentage = baseFee.percentage_rate * ( 1 - discountFee.discount );
			fixed = baseFee.fixed_rate * ( 1 - discountFee.discount );
		} else {
			// Custom base fee (2% + $.20)
			percentage = currentBaseFee.percentage_rate;
			fixed = currentBaseFee.fixed_rate;
		}

		let currentBaseFeeDescription = sprintf(
			formats.fee,
			formatFee( percentage ),
			formatCurrency( fixed, baseFee.currency )
		);

		if ( formats.displayBaseFeeIfDifferent ) {
			currentBaseFeeDescription = sprintf(
				// eslint-disable-next-line max-len
				/* translators: %1 Base fee (that don't apply to this account at this moment), %2: Current fee (e.g: "2.9% + $.30 per transaction") */
				__( '<s>%1$s</s> %2$s', 'woocommerce-payments' ),
				feeDescription,
				currentBaseFeeDescription
			);
		}

		if ( discountFee.discount && 0 < formats.discount.length ) {
			currentBaseFeeDescription +=
				' ' +
				sprintf( formats.discount, formatFee( discountFee.discount ) );
		}

		return createInterpolateElement( currentBaseFeeDescription, {
			s: <s />,
		} );
	}

	return feeDescription;
};

export const formatMethodFeesDescription = (
	methodFees: FeeStructure | undefined
): string | JSX.Element => {
	if ( ! methodFees ) {
		return __( 'missing fees', 'woocommerce-payments' );
	}

	/* translators: %1: Percentage part of the fee. %2: Fixed part of the fee */
	const format = __( 'From %1$f%% + %2$s', 'woocommerce-payments' );

	return formatAccountFeesDescription( methodFees, {
		fee: format,
		discount: '',
		displayBaseFeeIfDifferent: false,
	} );
};

export const getTransactionsPaymentMethodName = (
	paymentMethod: PaymentMethod
): string => {
	switch ( paymentMethod ) {
		case 'au_becs_debit':
			return __(
				'BECS Direct Debit transactions',
				'woocommerce-payments'
			);
		case 'bancontact':
			return __( 'Bancontact transactions', 'woocommerce-payments' );
		case 'card':
			return __( 'Card transactions', 'woocommerce-payments' );
		case 'card_present':
			return __( 'In-person transactions', 'woocommerce-payments' );
		case 'eps':
			return __( 'EPS transactions', 'woocommerce-payments' );
		case 'giropay':
			return __( 'GiroPay transactions', 'woocommerce-payments' );
		case 'ideal':
			return __( 'iDeal transactions', 'woocommerce-payments' );
		case 'p24':
			return __(
				'Przelewy24 (P24) transactions',
				'woocommerce-payments'
			);
		case 'sepa_debit':
			return __(
				'SEPA Direct Debit transactions',
				'woocommerce-payments'
			);
		case 'sofort':
			return __( 'Sofort transactions', 'woocommerce-payments' );
		default:
			return __( 'Unknown transactions', 'woocommerce-payments' );
	}
};
