/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Supported tax description codes for different countries and tax types.
 * These codes are used to identify and localize tax descriptions in the UI.
 * The codes follow the format [Country Code] [Tax Type].
 *
 * Format: [Country Code] [Tax Type]
 * Examples:
 * - "US VAT" for United States Value Added Tax
 * - "JP JCT" for Japan Consumption Tax
 * - "AU GST" for Australian Goods and Services Tax
 *
 * @see Transaction_Fee_Tax_Service::format_tax_name_from_fee_detail()
 * for the server-side implementation of these tax codes.
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
	| 'GB VAT' // United Kingdom VAT
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
	| 'default';

/**
 * Tax description mapping for localization.
 * Keys are the tax descriptions from the API response.
 * Values are the translatable strings.
 *
 * Note: Country codes are intentionally left untranslated as they are standardized ISO codes.
 */
const taxDescriptions: Record< TaxDescriptionKey, string > = {
	// European Union VAT
	'AT VAT': __( 'AT VAT', 'woocommerce-payments' ), // Austria
	'BE VAT': __( 'BE VAT', 'woocommerce-payments' ), // Belgium
	'BG VAT': __( 'BG VAT', 'woocommerce-payments' ), // Bulgaria
	'CY VAT': __( 'CY VAT', 'woocommerce-payments' ), // Cyprus
	'CZ VAT': __( 'CZ VAT', 'woocommerce-payments' ), // Czech Republic
	'DE VAT': __( 'DE VAT', 'woocommerce-payments' ), // Germany
	'DK VAT': __( 'DK VAT', 'woocommerce-payments' ), // Denmark
	'EE VAT': __( 'EE VAT', 'woocommerce-payments' ), // Estonia
	'ES VAT': __( 'ES VAT', 'woocommerce-payments' ), // Spain
	'FI VAT': __( 'FI VAT', 'woocommerce-payments' ), // Finland
	'FR VAT': __( 'FR VAT', 'woocommerce-payments' ), // France
	'GB VAT': __( 'UK VAT', 'woocommerce-payments' ), // United Kingdom
	'GR VAT': __( 'GR VAT', 'woocommerce-payments' ), // Greece
	'HR VAT': __( 'HR VAT', 'woocommerce-payments' ), // Croatia
	'HU VAT': __( 'HU VAT', 'woocommerce-payments' ), // Hungary
	'IE VAT': __( 'IE VAT', 'woocommerce-payments' ), // Ireland
	'IT VAT': __( 'IT VAT', 'woocommerce-payments' ), // Italy
	'LT VAT': __( 'LT VAT', 'woocommerce-payments' ), // Lithuania
	'LU VAT': __( 'LU VAT', 'woocommerce-payments' ), // Luxembourg
	'LV VAT': __( 'LV VAT', 'woocommerce-payments' ), // Latvia
	'MT VAT': __( 'MT VAT', 'woocommerce-payments' ), // Malta
	'NL VAT': __( 'NL VAT', 'woocommerce-payments' ), // Netherlands
	'PL VAT': __( 'PL VAT', 'woocommerce-payments' ), // Poland
	'PT VAT': __( 'PT VAT', 'woocommerce-payments' ), // Portugal
	'RO VAT': __( 'RO VAT', 'woocommerce-payments' ), // Romania
	'SE VAT': __( 'SE VAT', 'woocommerce-payments' ), // Sweden
	'SI VAT': __( 'SI VAT', 'woocommerce-payments' ), // Slovenia
	'SK VAT': __( 'SK VAT', 'woocommerce-payments' ), // Slovakia

	// GST Countries
	'AU GST': __( 'AU GST', 'woocommerce-payments' ), // Australia
	'NZ GST': __( 'NZ GST', 'woocommerce-payments' ), // New Zealand
	'SG GST': __( 'SG GST', 'woocommerce-payments' ), // Singapore

	// Other Tax Systems
	'CH VAT': __( 'CH VAT', 'woocommerce-payments' ), // Switzerland
	'JP JCT': __( 'JP JCT', 'woocommerce-payments' ), // Japan Consumption Tax

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
