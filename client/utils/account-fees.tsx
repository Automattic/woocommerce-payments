/** @format */

/**
 * External depencencies
 */
import { __, sprintf } from '@wordpress/i18n';
import interpolateComponents from 'interpolate-components';
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
	'https://woocommerce.com/document/payments/faq/fees/#section-';
const countryFeeStripeDocsBaseLinkNoCountry =
	'https://woocommerce.com/document/payments/faq/fees';
const countryFeeStripeDocsSectionNumbers: Record< string, number > = {
	AU: 1,
	AT: 2,
	BE: 3,
	CA: 4,
	FR: 5,
	DE: 6,
	HK: 7,
	IE: 8,
	IT: 9,
	NL: 10,
	NZ: 11,
	PL: 12,
	PT: 13,
	SG: 14,
	ES: 15,
	CH: 16,
	UK: 17,
	US: 18,
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

export const getCurrentFee = (
	accountFees: FeeStructure
): BaseFee | DiscountFee => {
	return accountFees.discount.length
		? accountFees.discount[ 0 ]
		: accountFees.base;
};

export const formatMethodFeesTooltip = (
	accountFees: FeeStructure | undefined
): JSX.Element => {
	if ( ! accountFees ) return <></>;

	const total = {
		percentage_rate:
			accountFees.base.percentage_rate +
			accountFees.additional.percentage_rate +
			accountFees.fx.percentage_rate,
		fixed_rate:
			accountFees.base.fixed_rate +
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
				<div>{ getFeeDescriptionString( accountFees.base ) }</div>
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
	const currentFee = getCurrentFee( accountFees );

	// Default formats will be used if no matching field was passed in the `formats` parameter.
	const formats = {
		/* translators: %1: Percentage part of the fee. %2: Fixed part of the fee */
		fee: __( '%1$f%% + %2$s per transaction', 'woocommerce-payments' ),
		/* translators: %f percentage discount to apply */
		discount: __( '(%f%% discount)', 'woocommerce-payments' ),
		displayBaseFeeIfDifferent: true,
		...customFormats,
	};

	const feeDescription = sprintf(
		formats.fee,
		formatFee(
			baseFee.percentage_rate +
				additionalFee.percentage_rate +
				fxFee.percentage_rate
		),
		formatCurrency(
			baseFee.fixed_rate + additionalFee.fixed_rate + fxFee.fixed_rate,
			baseFee.currency
		)
	);

	const isFormattingWithDiscount =
		currentFee.percentage_rate !== baseFee.percentage_rate ||
		currentFee.fixed_rate !== baseFee.fixed_rate ||
		currentFee.currency !== baseFee.currency;
	if ( isFormattingWithDiscount ) {
		const discountFee = currentFee as DiscountFee;
		// TODO: Figure out how the UI should work if there are several "discount" fees stacked.
		let percentage, fixed;

		if ( discountFee.discount ) {
			// Proper discount fee (XX% off)
			percentage = baseFee.percentage_rate * ( 1 - discountFee.discount );
			fixed = baseFee.fixed_rate * ( 1 - discountFee.discount );
		} else {
			// Custom base fee (2% + $.20)
			percentage = currentFee.percentage_rate;
			fixed = currentFee.fixed_rate;
		}

		let currentFeeDescription = sprintf(
			formats.fee,
			formatFee( percentage ),
			formatCurrency( fixed, baseFee.currency )
		);

		if ( formats.displayBaseFeeIfDifferent ) {
			currentFeeDescription = sprintf(
				// eslint-disable-next-line max-len
				/* translators: %1 Base fee (that don't apply to this account at this moment), %2: Current fee (e.g: "2.9% + $.30 per transaction") */
				__( '<s>%1$s</s> %2$s', 'woocommerce-payments' ),
				feeDescription,
				currentFeeDescription
			);
		}

		if ( discountFee.discount && 0 < formats.discount.length ) {
			currentFeeDescription +=
				' ' +
				sprintf( formats.discount, formatFee( discountFee.discount ) );
		}

		return createInterpolateElement( currentFeeDescription, {
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
	const format = __( '%1$f%% + %2$s', 'woocommerce-payments' );

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
		case 'bancontact':
			return __( 'Bancontact transactions', 'woocommerce-payments' );
		case 'card':
			return __( 'Card transactions', 'woocommerce-payments' );
		case 'card_present':
			return __( 'In-person transactions', 'woocommerce-payments' );
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
