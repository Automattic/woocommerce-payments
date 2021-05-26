/**
 * Internal dependencies
 */
import { upeRestrictedProperties } from './upe-styles';
import { generateHoverRules } from './utils.js';

const dashedToCamelCase = ( string ) => {
	return string.replace( /-([a-z])/g, function ( g ) {
		return g[ 1 ].toUpperCase();
	} );
};

export const getFieldStyles = ( selector, upeElement ) => {
	if ( ! document.querySelector( selector ) ) {
		return {};
	}

	const validProperties = upeRestrictedProperties[ upeElement ];

	const elem = document.querySelector( selector );

	const styles = window.getComputedStyle( elem );

	const filteredStyles = {};

	for ( let i = 0; i < styles.length; i++ ) {
		const camelCase = dashedToCamelCase( styles[ i ] );
		if ( validProperties.includes( camelCase ) ) {
			filteredStyles[ camelCase ] = styles.getPropertyValue(
				styles[ i ]
			);
		}
	}

	return filteredStyles;
};

export const getFontRulesFromPage = () => {
	const fontRules = [],
		sheets = document.styleSheets,
		fontDomains = [
			'fonts.googleapis.com',
			'fonts.gstatic.com',
			'fast.fonts.com',
			'use.typekit.net',
		];
	for ( let i = 0; i < sheets.length; i++ ) {
		if ( ! sheets[ i ].href ) {
			continue;
		}
		const url = new URL( sheets[ i ].href );
		if ( -1 !== fontDomains.indexOf( url.hostname ) ) {
			fontRules.push( {
				cssSrc: sheets[ i ].href,
			} );
		}
	}

	return fontRules;
};

export const getAppearance = () => {
	const upeThemeInputSelector = '.woocommerce-checkout .form-row input';
	const upeThemeLabelSelector = '.woocommerce-checkout .form-row label';
	const upeThemeSelectedPaymentSelector =
		'.woocommerce-checkout .place-order .button.alt';

	const inputTabRules = getFieldStyles( upeThemeInputSelector, '.Input' );

	const labelRules = getFieldStyles( upeThemeLabelSelector, '.Label' );

	const selectedTabRules = getFieldStyles(
		upeThemeSelectedPaymentSelector,
		'.Tab--selected'
	);
	const tabHoverRules = generateHoverRules( inputTabRules );
	const selectedTabHoverRules = generateHoverRules( selectedTabRules );

	const tabIconHoverRules = {
		color: tabHoverRules.color,
	};
	const selectedTabIconRules = {
		color: selectedTabRules.color,
	};
	const selectedTabIconHoverRules = {
		color: selectedTabHoverRules.color,
	};

	const appearance = {
		rules: {
			'.Input': inputTabRules,
			'.Label': labelRules,
			'.Tab': inputTabRules,
			'.Tab:hover': tabHoverRules,
			'.Tab--selected': selectedTabRules,
			'.Tab--selected:hover': selectedTabHoverRules,
			'.TabIcon:hover': tabIconHoverRules,
			'.TabIcon--selected': selectedTabIconRules,
			'.TabIcon--selected:hover': selectedTabIconHoverRules,
		},
	};

	return appearance;
};
