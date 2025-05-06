/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Supported tax description codes for different countries and tax types.
 * These codes are used to identify and localize tax descriptions in the UI.
 *
 * Format: [Country Code] [Tax Type]
 * Examples:
 * - "US VAT" for United States Value Added Tax
 * - "JP JCT" for Japan Consumption Tax
 * - "AU GST" for Australian Goods and Services Tax
 */
type TaxDescriptionKey =
	| 'AT VAT' // Austria VAT
	| 'BE VAT' // Belgium VAT
	| 'BG VAT' // Bulgaria VAT
	| 'CY VAT' // Cyprus VAT
	| 'CZ VAT' // Czech Republic VAT
	| 'DE VAT' // Germany VAT
	| 'DK VAT' // Denmark VAT
	| 'EE VAT' // Estonia VAT
	| 'ES VAT' // Spain VAT
	| 'FI VAT' // Finland VAT
	| 'FR VAT' // France VAT
	| 'GB VAT' // Great Britain VAT
	| 'GR VAT' // Greece VAT
	| 'HR VAT' // Croatia VAT
	| 'HU VAT' // Hungary VAT
	| 'IE VAT' // Ireland VAT
	| 'IT VAT' // Italy VAT
	| 'LT VAT' // Lithuania VAT
	| 'LU VAT' // Luxembourg VAT
	| 'LV VAT' // Latvia VAT
	| 'MT VAT' // Malta VAT
	| 'NL VAT' // Netherlands VAT
	| 'PL VAT' // Poland VAT
	| 'PT VAT' // Portugal VAT
	| 'RO VAT' // Romania VAT
	| 'SE VAT' // Sweden VAT
	| 'SI VAT' // Slovenia VAT
	| 'SK VAT' // Slovakia VAT
	| 'AU GST' // Australia GST
	| 'NZ GST' // New Zealand GST
	| 'SG GST' // Singapore GST
	| 'CH VAT' // Switzerland VAT
	| 'JP JCT' // Japan Consumption Tax
	| 'default'; // Default tax description when no specific code matches

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
