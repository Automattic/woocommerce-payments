/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

type TaxDescriptionKey =
	| 'AT VAT'
	| 'BE VAT'
	| 'BG VAT'
	| 'CY VAT'
	| 'CZ VAT'
	| 'DE VAT'
	| 'DK VAT'
	| 'EE VAT'
	| 'ES VAT'
	| 'FI VAT'
	| 'FR VAT'
	| 'GB VAT'
	| 'GR VAT'
	| 'HR VAT'
	| 'HU VAT'
	| 'IE VAT'
	| 'IT VAT'
	| 'LT VAT'
	| 'LU VAT'
	| 'LV VAT'
	| 'MT VAT'
	| 'NL VAT'
	| 'PL VAT'
	| 'PT VAT'
	| 'RO VAT'
	| 'SE VAT'
	| 'SI VAT'
	| 'SK VAT'
	| 'AU GST'
	| 'NZ GST'
	| 'SG GST'
	| 'CH VAT'
	| 'JP JCT'
	| 'default';

/**
 * Tax description mapping for localization.
 * Keys are the tax descriptions from the API response.
 * Values are the translatable strings.
 *
 * Note: Country codes are intentionally left untranslated as they are standardized ISO codes.
 */
export const taxDescriptions: Record< TaxDescriptionKey, string > = {
	// European Union VAT
	'AT VAT': __( 'AT VAT', 'woocommerce-payments' ), // Austria
	'BE VAT': __( 'BE TVA/BTW', 'woocommerce-payments' ), // Belgium (has 2 official names)
	'BG VAT': __( 'BG ДДС', 'woocommerce-payments' ), // Bulgaria
	'CY VAT': __( 'CY ΦΠΑ', 'woocommerce-payments' ), // Cyprus
	'CZ VAT': __( 'CZ DPH', 'woocommerce-payments' ), // Czech Republic
	'DE VAT': __( 'DE MwSt', 'woocommerce-payments' ), // Germany
	'DK VAT': __( 'DK MOMS', 'woocommerce-payments' ), // Denmark
	'EE VAT': __( 'EE KM', 'woocommerce-payments' ), // Estonia
	'ES VAT': __( 'ES IVA', 'woocommerce-payments' ), // Spain
	'FI VAT': __( 'FI ALV', 'woocommerce-payments' ), // Finland
	'FR VAT': __( 'FR TVA', 'woocommerce-payments' ), // France
	'GB VAT': __( 'GB VAT', 'woocommerce-payments' ), // United Kingdom
	'GR VAT': __( 'GR ΦΠΑ', 'woocommerce-payments' ), // Greece
	'HR VAT': __( 'HR PDV', 'woocommerce-payments' ), // Croatia
	'HU VAT': __( 'HU ÁFA', 'woocommerce-payments' ), // Hungary
	'IE VAT': __( 'IE VAT', 'woocommerce-payments' ), // Ireland
	'IT VAT': __( 'IT IVA', 'woocommerce-payments' ), // Italy
	'LT VAT': __( 'LT PVM', 'woocommerce-payments' ), // Lithuania
	'LU VAT': __( 'LU TVA', 'woocommerce-payments' ), // Luxembourg
	'LV VAT': __( 'LV PVN', 'woocommerce-payments' ), // Latvia
	'MT VAT': __( 'MT VAT', 'woocommerce-payments' ), // Malta
	'NL VAT': __( 'NL BTW', 'woocommerce-payments' ), // Netherlands
	'PL VAT': __( 'PL VAT', 'woocommerce-payments' ), // Poland
	'PT VAT': __( 'PT IVA', 'woocommerce-payments' ), // Portugal
	'RO VAT': __( 'RO TVA', 'woocommerce-payments' ), // Romania
	'SE VAT': __( 'SE MOMS', 'woocommerce-payments' ), // Sweden
	'SI VAT': __( 'SI DDV', 'woocommerce-payments' ), // Slovenia
	'SK VAT': __( 'SK DPH', 'woocommerce-payments' ), // Slovakia

	// GST Countries
	'AU GST': __( 'AU GST', 'woocommerce-payments' ), // Australia
	'NZ GST': __( 'NZ GST', 'woocommerce-payments' ), // New Zealand
	'SG GST': __( 'SG GST', 'woocommerce-payments' ), // Singapore

	// Other Tax Systems
	'CH VAT': __( 'CH MWST/TVA/IVA', 'woocommerce-payments' ), // Switzerland (has 3 official names)
	'JP JCT': __( 'JP 消費税', 'woocommerce-payments' ), // Japan - JCT stands for Japanese Consumption Tax

	// Fallback for unknown tax descriptions
	default: __( 'Tax', 'woocommerce-payments' ),
};

/**
 * Get the localized tax description.
 *
 * @param {string} taxDescription - The tax description from the API
 * @return {string} The localized tax description
 */
export const getLocalizedTaxDescription = (
	taxDescription: string
): string => {
	return taxDescription in taxDescriptions
		? taxDescriptions[ taxDescription as TaxDescriptionKey ]
		: taxDescriptions.default;
};
