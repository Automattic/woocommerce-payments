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
		const element = document.querySelector( selectors[ i ] );
		if ( ! element ) {
			i++;
			continue;
		}

		const bgColor = window.getComputedStyle( element ).backgroundColor;
		// If backgroundColor property present and alpha > 0.
		if ( bgColor && tinycolor( bgColor ).getAlpha() > 0 ) {
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
	return tinycolor( color ).getBrightness() > 125;
};

/**
 * Modifies the appearance object to include styles for floating label.
 *
 * @param {Object} appearance object to modify.
 * @param {Object} floatingLabelStyles Floating label styles.
 * @return {Object} Modified appearance object.
 */
export const handleAppearanceForFloatingLabel = (
	appearance,
	floatingLabelStyles
) => {
	// Add floating label styles.
	appearance.rules[ '.Label--floating' ] = floatingLabelStyles;

	// Update line-height for floating label to account for scaling.
	if (
		appearance.rules[ '.Label--floating' ].transform &&
		appearance.rules[ '.Label--floating' ].transform !== 'none'
	) {
		// Extract the scaling factors from the matrix
		const transformMatrix =
			appearance.rules[ '.Label--floating' ].transform;
		const matrixValues = transformMatrix.match( /matrix\((.+)\)/ )[ 1 ];
		if ( matrixValues ) {
			const splitMatrixValues = matrixValues.split( ', ' );
			const scaleX = parseFloat( splitMatrixValues[ 0 ] );
			const scaleY = parseFloat( splitMatrixValues[ 3 ] );
			const scale = ( scaleX + scaleY ) / 2;

			const lineHeight = parseFloat(
				appearance.rules[ '.Label--floating' ].lineHeight
			);
			const newLineHeight = Math.floor( lineHeight * scale );
			appearance.rules[
				'.Label--floating'
			].lineHeight = `${ newLineHeight }px`;
			appearance.rules[
				'.Label--floating'
			].fontSize = `${ newLineHeight }px`;
		}
		delete appearance.rules[ '.Label--floating' ].transform;
	}

	// Subtract the label's lineHeight from padding-top to account for floating label height.
	// Minus 4px which is a constant value added by stripe to the padding-top.
	// Minus 1px for each vertical padding to account for the unpredictable input height
	// (see https://github.com/Automattic/woocommerce-payments/issues/9476#issuecomment-2374766540).
	// When the result is less than 0, it will automatically use 0.
	if ( appearance.rules[ '.Input' ].paddingTop ) {
		appearance.rules[
			'.Input'
			// eslint-disable-next-line max-len
		].paddingTop = `calc(${ appearance.rules[ '.Input' ].paddingTop } - ${ appearance.rules[ '.Label--floating' ].lineHeight } - 4px - 1px)`;
	}
	if ( appearance.rules[ '.Input' ].paddingBottom ) {
		const originalPaddingBottom = parseFloat(
			appearance.rules[ '.Input' ].paddingBottom
		);
		appearance.rules[
			'.Input'
			// eslint-disable-next-line max-len
		].paddingBottom = `${ originalPaddingBottom - 1 }px`;

		const originalLabelMarginTop =
			appearance.rules[ '.Label' ].marginTop ?? '0';
		appearance.rules[ '.Label' ].marginTop = `${ Math.floor(
			( originalPaddingBottom - 1 ) / 3
		) }px`;
		appearance.rules[
			'.Label--floating'
		].marginTop = originalLabelMarginTop;
	}

	return appearance;
};
