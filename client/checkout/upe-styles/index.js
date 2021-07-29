/**
 * Internal dependencies
 */
import { upeRestrictedProperties } from './upe-styles';
import { generateHoverRules, generateOutlineStyle } from './utils.js';

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

	if ( '.Input' === upeElement || '.Tab--selected' === upeElement ) {
		const outline = generateOutlineStyle(
			filteredStyles.outlineWidth,
			filteredStyles.outlineStyle,
			filteredStyles.outlineColor
		);
		if ( '' !== outline ) {
			filteredStyles.outline = outline;
		}
		delete filteredStyles.outlineWidth;
		delete filteredStyles.outlineColor;
		delete filteredStyles.outlineStyle;
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
	const upeThemeInputSelector = '#billing_first_name';
	const upeThemeLabelSelector = '.woocommerce-checkout .form-row label';
	const upeThemeInvalidInputSelector = '#wcpay-hidden-invalid-input';
	const upeThemeFocusInputSelector = '#wcpay-hidden-input';

	const inputRules = getFieldStyles( upeThemeInputSelector, '.Input' );
	const inputFocusRules = getFieldStyles(
		upeThemeFocusInputSelector,
		'.Input'
	);
	const inputInvalidRules = getFieldStyles(
		upeThemeInvalidInputSelector,
		'.Input'
	);

	const labelRules = getFieldStyles( upeThemeLabelSelector, '.Label' );

	const tabRules = getFieldStyles( upeThemeInputSelector, '.Tab' );
	const selectedTabRules = getFieldStyles(
		upeThemeFocusInputSelector,
		'.Tab--selected'
	);
	const tabHoverRules = generateHoverRules( tabRules );

	const tabIconHoverRules = {
		color: tabHoverRules.color,
	};
	const selectedTabIconRules = {
		color: selectedTabRules.color,
	};

	const appearance = {
		rules: {
			'.Input': inputRules,
			'.Input:focus': inputFocusRules,
			'.Input--invalid': inputInvalidRules,
			'.Label': labelRules,
			'.Tab': tabRules,
			'.Tab:hover': tabHoverRules,
			'.Tab--selected': selectedTabRules,
			'.TabIcon:hover': tabIconHoverRules,
			'.TabIcon--selected': selectedTabIconRules,
			'.Text': labelRules,
			'.Text--redirect': labelRules,
		},
	};

	return appearance;
};
