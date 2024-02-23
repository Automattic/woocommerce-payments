/**
 * External dependencies
 */
import tinycolor from 'tinycolor2';

/**
 * Generates hover colors from a background color and a text color.
 *
 * @param {string}  backgroundColor Background color, Any format accepted by tinyColor library
 * @param {string}  color Text color, any format accepted by tinyColor library
 * @return {Object} Object with new background color and text color.
 */
export const generateHoverColors = ( backgroundColor, color ) => {
	const hoverColors = {
		backgroundColor,
		color,
	};

	const tinyBackgroundColor = tinycolor( backgroundColor );
	const tinyTextColor = tinycolor( color );

	// If colors are not valid return empty strings.
	if ( ! tinyBackgroundColor.isValid() || ! tinyTextColor.isValid() ) {
		return {
			backgroundColor: '',
			color: '',
		};
	}

	// Darken if brightness > 50 (Storefront Button 51 ), else lighten
	const newBackgroundColor =
		tinyBackgroundColor.getBrightness() > 50
			? tinycolor( tinyBackgroundColor ).darken( 7 )
			: tinycolor( tinyBackgroundColor ).lighten( 7 );

	// Returns provided color if readable, otherwise black or white.
	const mostReadableColor = tinycolor.mostReadable(
		newBackgroundColor,
		[ tinyTextColor ],
		{ includeFallbackColors: true }
	);

	hoverColors.backgroundColor = newBackgroundColor.toRgbString();
	hoverColors.color = mostReadableColor.toRgbString();

	return hoverColors;
};

/**
 * Generates hover rules for UPE using a set of appearance rules as a basis.
 *
 * @param {Object}  baseRules UPE appearance rules to use as a base to generate hover colors
 * @return {Object} Object with generated hover rules.
 */
export const generateHoverRules = ( baseRules ) => {
	const hoverRules = Object.assign( {}, baseRules );

	// If there are no colors, return the same rules as we can not generate hover colors.
	if ( ! baseRules.backgroundColor || ! baseRules.color ) {
		return baseRules;
	}

	const hoverColors = generateHoverColors(
		baseRules.backgroundColor,
		baseRules.color
	);

	hoverRules.backgroundColor = hoverColors.backgroundColor;
	hoverRules.color = hoverColors.color;

	return hoverRules;
};

/**
 * Generates outline style for UPE using outline width, style and color.
 * UPE does not accept the individual properties, we need to concat them.
 *
 * @param {string}  outlineWidth Outline width from computed styles.
 * @param {string}  outlineStyle Outline width from computed styles.
 * @param {string}  outlineColor Outline width from computed styles.
 * @return {string} Object with generated hover rules.
 */
export const generateOutlineStyle = (
	outlineWidth,
	outlineStyle = 'solid',
	outlineColor
) => {
	return outlineWidth && outlineColor
		? [ outlineWidth, outlineStyle, outlineColor ].join( ' ' )
		: '';
};

/**
 * Converts CSS property from dashed format to camel case.
 *
 * @param {string} string CSS property.
 * @return {string} Camel case string.
 */
export const dashedToCamelCase = ( string ) => {
	return string.replace( /-([a-z])/g, function ( g ) {
		return g[ 1 ].toUpperCase();
	} );
};

/**
 * Converts rgba to rgb format, since Stripe Appearances API does not accept rgba format for background.
 *
 * @param {string} color CSS color value.
 * @return {string} Accepted CSS color value.
 */
export const maybeConvertRGBAtoRGB = ( color ) => {
	if ( color.match( /^rgba/ ) ) {
		const colorParts = color.match(
			/^rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*(\d+(?:\.\d+)?))?\)$/
		);
		color = `rgb(${ colorParts[ 1 ] }, ${ colorParts[ 2 ] }, ${ colorParts[ 3 ] })`;
	}
	return color;
};

/**
 * Searches through array of CSS selectors and returns first visible background color.
 *
 * @param {Array} selectors List of CSS selectors to check.
 * @return {string} CSS color value.
 */
export const getBackgroundColor = ( selectors ) => {
	const defaultColor = '#ffffff';
	let color = null;
	let i = 0;
	while ( ! color && i < selectors.length ) {
		const bgColor = window.getComputedStyle(
			document.querySelector( selectors[ i ] )
		).backgroundColor;
		if ( bgColor.match( /^rgba/ ) ) {
			const colorParts = bgColor.match(
				/^rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*(\d+(?:\.\d+)?))?\)$/
			);
			// Check if color is transparent.
			if ( parseInt( colorParts[ 5 ], 10 ) > 0 ) {
				color = bgColor;
			}
		} else {
			color = bgColor;
		}
		i++;
	}
	return color || defaultColor;
};

/**
 * Determines whether background color is light or dark.
 *
 * @param {string} color CSS color value.
 * @return {boolean} True, if background is light; false, if background is dark.
 */
export const isColorLight = ( color ) => {
	let r, g, b;
	if ( color.match( /^rgb/ ) ) {
		color = color.match(
			/^rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*(\d+(?:\.\d+)?))?\)$/
		);
		[ r, g, b ] = color.slice( 1 );
	} else {
		// Convert hex format to rgb
		color = +(
			'0x' + color.slice( 1 ).replace( color.length < 5 && /./g, '$&$&' )
		);

		// eslint-disable-next-line no-bitwise
		r = color >> 16;
		// eslint-disable-next-line no-bitwise
		g = ( color >> 8 ) & 255;
		// eslint-disable-next-line no-bitwise
		b = color & 255;
	}

	// HSP equation from http://alienryderflex.com/hsp.html
	const hsp = Math.sqrt(
		0.299 * ( r * r ) + 0.587 * ( g * g ) + 0.114 * ( b * b )
	);

	// Using the HSP value, determine whether the color is light or dark
	return hsp > 127.5;
};
