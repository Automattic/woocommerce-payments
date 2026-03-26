/**
 * Darkens a hex colour by a percentage (matching WooPay's PHP darken_color).
 *
 * @param {string} hex   A hex colour string (e.g. "#ffffff").
 * @param {number} pct   Percentage to darken (0–100).
 * @return {string} The darkened hex colour.
 */
export const darkenColor = ( hex, pct ) => {
	const h = hex.replace( '#', '' );
	const factor = 1 - Math.max( 0, Math.min( 100, pct ) ) / 100;
	const r = Math.round( parseInt( h.substring( 0, 2 ), 16 ) * factor );
	const g = Math.round( parseInt( h.substring( 2, 4 ), 16 ) * factor );
	const b = Math.round( parseInt( h.substring( 4, 6 ), 16 ) * factor );
	return `#${ r.toString( 16 ).padStart( 2, '0' ) }${ g
		.toString( 16 )
		.padStart( 2, '0' ) }${ b.toString( 16 ).padStart( 2, '0' ) }`;
};

/**
 * Lightens a hex colour by a percentage (matching WooPay's PHP lighten_color).
 *
 * @param {string} hex   A hex colour string (e.g. "#000000").
 * @param {number} pct   Percentage to lighten (0–100).
 * @return {string} The lightened hex colour.
 */
export const lightenColor = ( hex, pct ) => {
	const h = hex.replace( '#', '' );
	const factor = Math.max( 0, Math.min( 100, pct ) ) / 100;
	const r = Math.round(
		parseInt( h.substring( 0, 2 ), 16 ) +
			( 255 - parseInt( h.substring( 0, 2 ), 16 ) ) * factor
	);
	const g = Math.round(
		parseInt( h.substring( 2, 4 ), 16 ) +
			( 255 - parseInt( h.substring( 2, 4 ), 16 ) ) * factor
	);
	const b = Math.round(
		parseInt( h.substring( 4, 6 ), 16 ) +
			( 255 - parseInt( h.substring( 4, 6 ), 16 ) ) * factor
	);
	return `#${ r.toString( 16 ).padStart( 2, '0' ) }${ g
		.toString( 16 )
		.padStart( 2, '0' ) }${ b.toString( 16 ).padStart( 2, '0' ) }`;
};

/**
 * Returns true if the hex colour is considered "dark" (luminance < 128).
 *
 * @param {string} hex A hex colour string.
 * @return {boolean} Whether the colour is dark.
 */
export const isDarkColor = ( hex ) => {
	const h = hex.replace( '#', '' );
	const r = parseInt( h.substring( 0, 2 ), 16 );
	const g = parseInt( h.substring( 2, 4 ), 16 );
	const b = parseInt( h.substring( 4, 6 ), 16 );
	return ( r * 299 + g * 587 + b * 114 ) / 1000 < 128;
};

/**
 * Derives the card border colour from a background hex value.
 * Matches WooPay's PHP logic: lighten 6 % for dark (night) backgrounds,
 * darken 6 % for light (day) backgrounds.
 *
 * @param {string|undefined} bg  A hex colour string, or undefined.
 * @return {string|undefined} The derived border colour, or undefined.
 */
export const getCardBorderColor = ( bg ) => {
	if ( ! bg ) {
		return undefined;
	}

	// Normalize 3-digit hex (#RGB) to 6-digit (#RRGGBB).
	let hex = bg;
	const shortMatch = /^#([0-9a-f])([0-9a-f])([0-9a-f])$/i.exec( hex );
	if ( shortMatch ) {
		hex = `#${ shortMatch[ 1 ] }${ shortMatch[ 1 ] }${ shortMatch[ 2 ] }${ shortMatch[ 2 ] }${ shortMatch[ 3 ] }${ shortMatch[ 3 ] }`;
	}

	if ( ! /^#[0-9a-f]{6}$/i.test( hex ) ) {
		return undefined;
	}
	return isDarkColor( hex ) ? lightenColor( hex, 6 ) : darkenColor( hex, 6 );
};
