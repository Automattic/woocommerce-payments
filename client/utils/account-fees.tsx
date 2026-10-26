/** @format */

/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import interpolateComponents from '@automattic/interpolate-components';
import { ExternalLink } from '@wordpress/components';
import './account-fees.scss';

/**
 * Internal dependencies
 */
import { formatCurrency } from 'multi-currency/interface/functions';
import { formatFee } from 'utils/fees';
import { formatDateTimeFromString } from 'wcpay/utils/date-time';
import React from 'react';
import { BaseFee, DiscountFee, FeeStructure } from 'wcpay/types/fees';
import { createInterpolateElement } from '@wordpress/element';
import PAYMENT_METHOD_IDS from 'constants/payment-method';

const countryFeeDocsBaseLink =
	'https://woocommerce.com/document/woopayments/fees/';

// Keyed on the Stripe account country — the same account the fees in this
// tooltip come from. Full URL literals let CI check the actual destinations.
const countryFeeDocsUrls: Record< string, string > = {
	AE: 'https://woocommerce.com/document/woopayments/fees/#united-arab-emirates',
	AU: 'https://woocommerce.com/document/woopayments/fees/#australia',
	AT: 'https://woocommerce.com/document/woopayments/fees/#austria',
	BE: 'https://woocommerce.com/document/woopayments/fees/#belgium',
	BG: 'https://woocommerce.com/document/woopayments/fees/#bulgaria',
	CA: 'https://woocommerce.com/document/woopayments/fees/#canada',
	CY: 'https://woocommerce.com/document/woopayments/fees/#cyprus',
	CZ: 'https://woocommerce.com/document/woopayments/fees/#czech-republic',
	FR: 'https://woocommerce.com/document/woopayments/fees/#france',
	LU: 'https://woocommerce.com/document/woopayments/fees/#luxembourg',
	DE: 'https://woocommerce.com/document/woopayments/fees/#germany',
	DK: 'https://woocommerce.com/document/woopayments/fees/#denmark',
	EE: 'https://woocommerce.com/document/woopayments/fees/#estonia',
	FI: 'https://woocommerce.com/document/woopayments/fees/#finland',
	GR: 'https://woocommerce.com/document/woopayments/fees/#greece',
	HK: 'https://woocommerce.com/document/woopayments/fees/#hong-kong',
	HR: 'https://woocommerce.com/document/woopayments/fees/#croatia',
	HU: 'https://woocommerce.com/document/woopayments/fees/#hungary',
	IE: 'https://woocommerce.com/document/woopayments/fees/#ireland',
	IT: 'https://woocommerce.com/document/woopayments/fees/#italy',
	JP: 'https://woocommerce.com/document/woopayments/fees/#japan',
	LT: 'https://woocommerce.com/document/woopayments/fees/#lithuania',
	LV: 'https://woocommerce.com/document/woopayments/fees/#latvia',
	MT: 'https://woocommerce.com/document/woopayments/fees/#malta',
	NL: 'https://woocommerce.com/document/woopayments/fees/#netherlands',
	NO: 'https://woocommerce.com/document/woopayments/fees/#norway',
	NZ: 'https://woocommerce.com/document/woopayments/fees/#new-zealand',
	PL: 'https://woocommerce.com/document/woopayments/fees/#poland',
	PT: 'https://woocommerce.com/document/woopayments/fees/#portugal',
	SG: 'https://woocommerce.com/document/woopayments/fees/#singapore',
	SI: 'https://woocommerce.com/document/woopayments/fees/#slovenia',
	SK: 'https://woocommerce.com/document/woopayments/fees/#slovakia',
	SE: 'https://woocommerce.com/document/woopayments/fees/#sweden',
	ES: 'https://woocommerce.com/document/woopayments/fees/#spain',
	CH: 'https://woocommerce.com/document/woopayments/fees/#switzerland',
	GB: 'https://woocommerce.com/document/woopayments/fees/#united-kingdom',
	US: 'https://woocommerce.com/document/woopayments/fees/#united-states',
	RO: 'https://woocommerce.com/document/woopayments/fees/#romania',
	// PR (Puerto Rico) needs no entry. It is a supported *store* country but
	// is not selectable during Stripe account signup, so a Puerto Rico store's
	// account is created as US and this lookup resolves to 'united-states' —
	// which is the section describing the rates that account is actually
	// charged. The fees page has no Puerto Rico section to point at anyway;
	// its only PR line sits inside the US section, noting that PR-issued cards
	// trigger the international payment fee.
};

/**
 * The fees page URL for an account's country, if it has a documented section.
 *
 * `hasOwnProperty` rather than a bare lookup: a bare lookup walks the
 * prototype chain, so a country of `constructor` or `toString` would resolve
 * to a function and read as a mapped country.
 */
const getCountryFeeDocsUrl = ( country: string ): string | undefined =>
	Object.prototype.hasOwnProperty.call( countryFeeDocsUrls, country )
		? countryFeeDocsUrls[ country ]
		: undefined;

const getFeeDescriptionString = (
	fee: BaseFee,
	discountBasedMultiplier = 1
): string => {
	if ( fee.fixed_rate && fee.percentage_rate ) {
		return sprintf(
			'%1$f%% + %2$s',
			formatFee( fee.percentage_rate * discountBasedMultiplier ),
			formatCurrency(
				fee.fixed_rate * discountBasedMultiplier,
				fee.currency
			)
		);
	} else if ( fee.fixed_rate ) {
		return sprintf(
			'%1$s',
			formatCurrency(
				fee.fixed_rate * discountBasedMultiplier,
				fee.currency
			)
		);
	} else if ( fee.percentage_rate ) {
		return sprintf(
			'%1$f%%',
			formatFee( fee.percentage_rate * discountBasedMultiplier )
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
	accountFees?: FeeStructure
): JSX.Element => {
	if ( ! accountFees ) return <></>;

	const discountAdjustedFeeRate: number =
		accountFees.discount.length && accountFees.discount[ 0 ].discount
			? 1 - accountFees.discount[ 0 ].discount
			: 1;

	// Per https://woocommerce.com/terms-conditions/woopayments-promotion-2023/ we exclude FX fees from discounts.
	const total = {
		percentage_rate:
			accountFees.base.percentage_rate * discountAdjustedFeeRate +
			accountFees.additional.percentage_rate * discountAdjustedFeeRate +
			accountFees.fx.percentage_rate,
		fixed_rate:
			accountFees.base.fixed_rate * discountAdjustedFeeRate +
			accountFees.additional.fixed_rate * discountAdjustedFeeRate +
			accountFees.fx.fixed_rate,
		currency: accountFees.base.currency,
	};

	const hasFees = ( fee: BaseFee ): boolean => {
		return fee.fixed_rate > 0.0 || fee.percentage_rate > 0.0;
	};

	// The fees above this link come from the Stripe account, so the link has to
	// describe the account's country. Not `connect.country` — that namespace is
	// the onboarding form's data (country, availableCountries, availableStates),
	// where the store address is the only country signal available because no
	// account exists yet.
	const country = wcpaySettings?.accountStatus?.country;
	const countryFeeDocsUrl = country
		? getCountryFeeDocsUrl( country )
		: undefined;
	// Use the un-anchored fees page when the country has no documented section.
	const feeDocsUrl = countryFeeDocsUrl || countryFeeDocsBaseLink;

	return (
		<div className={ 'wcpay-fees-tooltip' }>
			<div>
				<div>{ __( 'Base fee', 'woocommerce-payments' ) }</div>
				<div>
					{ getFeeDescriptionString(
						accountFees.base,
						discountAdjustedFeeRate
					) }
				</div>
			</div>
			{ hasFees( accountFees.additional ) ? (
				<div>
					<div>
						{ __(
							'International payment method fee',
							'woocommerce-payments'
						) }
					</div>
					<div>
						{ getFeeDescriptionString(
							accountFees.additional,
							discountAdjustedFeeRate
						) }
					</div>
				</div>
			) : (
				''
			) }
			{ hasFees( accountFees.fx ) ? (
				<div>
					<div>
						{ __(
							'Currency conversion fee',
							'woocommerce-payments'
						) }
					</div>
					<div>{ getFeeDescriptionString( accountFees.fx ) }</div>
				</div>
			) : (
				''
			) }
			<div>
				<div>
					{ __( 'Total per transaction', 'woocommerce-payments' ) }
				</div>
				<div className={ 'wcpay-fees-tooltip__bold' }>
					{ getFeeDescriptionString( total ) }
				</div>
			</div>
			{ /* No country gate: an account whose country is unknown still gets
			     the un-anchored fees page, the same as a country the page has
			     no section for. Fees are on screen either way. */ }
			<div className="wcpay-fees-tooltip__hint-text">
				<span>
					{ interpolateComponents( {
						mixedString: countryFeeDocsUrl
							? sprintf(
									/* translators: %s: WooPayments */
									__(
										'{{linkToStripePage}}Learn more{{/linkToStripePage}} about %s Fees in your country',
										'woocommerce-payments'
									),
									'WooPayments'
								)
							: sprintf(
									/* translators: %s: WooPayments */
									__(
										'{{linkToStripePage}}Learn more{{/linkToStripePage}} about %s Fees',
										'woocommerce-payments'
									),
									'WooPayments'
								),
						components: {
							linkToStripePage: (
								// @ts-expect-error: children is provided when interpolating the component
								<ExternalLink href={ feeDocsUrl } />
							),
						},
					} ) }
				</span>
			</div>
		</div>
	);
};

export const formatAccountFeesDescription = (
	accountFees: FeeStructure,
	customFormats = {}
): string | JSX.Element => {
	const baseFee = accountFees.base;
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

	const feeDescription = sprintf(
		formats.fee,
		formatFee( baseFee.percentage_rate ),
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

		if ( discountFee.discount && formats.discount.length > 0 ) {
			currentBaseFeeDescription +=
				' ' +
				sprintf( formats.discount, formatFee( discountFee.discount ) );
		}

		const conversionMap: Record< string, any > = {
			s: <s />,
		};

		return createInterpolateElement(
			currentBaseFeeDescription,
			conversionMap
		);
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
	paymentMethod: PAYMENT_METHOD_IDS
): string => {
	// Special cases that won't be in wooPaymentsPaymentMethodsConfig
	// `card` WILL be in that config, but it's title is "Cards" and we want to show "Card transactions."
	switch ( paymentMethod ) {
		case 'card':
			return __( 'Card transactions', 'woocommerce-payments' );
		case 'card_present':
			return __( 'In-person transactions', 'woocommerce-payments' );
	}

	// Try to get the title from wooPaymentsPaymentMethodsConfig
	const methodConfig = wooPaymentsPaymentMethodsConfig[ paymentMethod ];
	if ( methodConfig?.title ) {
		return sprintf(
			/* translators: %s: Payment method title */
			__( '%s transactions', 'woocommerce-payments' ),
			methodConfig.title
		);
	}

	// Fallback for unknown payment methods
	return __( 'Unknown transactions', 'woocommerce-payments' );
};

export const getDiscountBadgeText = ( discountFee: DiscountFee ): string => {
	if ( ! discountFee.discount ) {
		return '';
	}

	const discountPercentage = formatFee( discountFee.discount );

	if ( discountFee.end_time ) {
		return sprintf(
			/* translators: %1$s: discount percentage, %2$s: expiration date */
			__( '%1$s%% off fees through %2$s', 'woocommerce-payments' ),
			discountPercentage,
			formatDateTimeFromString( discountFee.end_time )
		);
	}

	return sprintf(
		/* translators: %s: discount percentage */
		__( '%s%% off fees', 'woocommerce-payments' ),
		discountPercentage
	);
};

export const getDiscountTooltipText = ( discountFee: DiscountFee ): string => {
	if ( ! discountFee.discount ) {
		return '';
	}

	const discountPercentage = formatFee( discountFee.discount );
	const currencyCode = discountFee.volume_currency ?? discountFee.currency;

	if ( discountFee.volume_allowance && discountFee.end_time ) {
		return sprintf(
			/* translators: %1$s: discount percentage, %2$s: total payment volume until this promotion expires, %3$s: End date of the promotion */
			__(
				'You are saving %1$s%% on processing fees for the first %2$s of total payment volume or through %3$s.',
				'woocommerce-payments'
			),
			discountPercentage,
			formatCurrency( discountFee.volume_allowance, currencyCode ),
			formatDateTimeFromString( discountFee.end_time )
		);
	} else if ( discountFee.volume_allowance ) {
		return sprintf(
			/* translators: %1$s: discount percentage, %2$s: total payment volume until this promotion expires */
			__(
				'You are saving %1$s%% on processing fees for the first %2$s of total payment volume.',
				'woocommerce-payments'
			),
			discountPercentage,
			formatCurrency( discountFee.volume_allowance, currencyCode )
		);
	} else if ( discountFee.end_time ) {
		return sprintf(
			/* translators: %1$s: discount percentage, %2$s: End date of the promotion */
			__(
				'You are saving %1$s%% on processing fees through %2$s.',
				'woocommerce-payments'
			),
			discountPercentage,
			formatDateTimeFromString( discountFee.end_time )
		);
	}

	return sprintf(
		/* translators: %s: discount percentage */
		__( 'You are saving %s%% on processing fees.', 'woocommerce-payments' ),
		discountPercentage
	);
};
