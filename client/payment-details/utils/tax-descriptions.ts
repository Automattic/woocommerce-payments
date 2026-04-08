/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Tax description mapping for localization.
 * Keys are the tax descriptions from the API response.
 * Values are the translatable strings.
 *
 * @see Transaction_Fee_Tax_Service::format_tax_name_from_fee_detail()
 * for the server-side implementation of the tax descriptions.
 */
const taxDescriptions: Record< string, string > = {
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
	'NO VAT': __( 'NO VAT', 'woocommerce-payments' ), // Norway
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
		? taxDescriptions[ taxDescription ]
		: taxDescriptions.default;
};
