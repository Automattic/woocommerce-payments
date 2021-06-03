/**
 * External dependencies
 */
import { useState, useEffect } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { getPaymentRequestData } from '../utils';

const useLocalizedGoogleSvg = ( type, theme, locale ) => {
	// If we're using the short button type (i.e. logo only) make sure we get the logo only SVG.
	const googlePlaySvg =
		'long' === type
			? `https://www.gstatic.com/instantbuy/svg/${ theme }/${ locale }.svg`
			: `https://www.gstatic.com/instantbuy/svg/${ theme }_gpay.svg`;

	const [ url, setUrl ] = useState( googlePlaySvg );

	useEffect( () => {
		const im = document.createElement( 'img' );
		im.addEventListener( 'error', () => {
			setUrl(
				`https://www.gstatic.com/instantbuy/svg/${ theme }/en.svg`
			);
		} );
		im.src = url;
	}, [ url, theme ] );

	return url;
};

export const GooglePayButton = ( { onClick } ) => {
	// TODO: migrate button data
	const {
		height,
		locale,
		// eslint-disable-next-line camelcase
		branded_type,
	} = getPaymentRequestData( 'button' );
	// Allowed themes for Google Pay button image are 'dark' and 'light'.
	// TODO: migrate button data
	const theme =
		'dark' === getPaymentRequestData( 'button' )?.theme ? 'dark' : 'light';

	// Let's make sure the localized Google Pay button exists, otherwise we fall back to the
	// english version. This test element is not used on purpose.
	const backgroundUrl = useLocalizedGoogleSvg( branded_type, theme, locale );

	return (
		<button
			type="button"
			id="wcpay-branded-button"
			aria-label="Google Pay"
			className={ `gpay-button ${
				// For the button class, `light-outline` is also supported.
				getPaymentRequestData( 'button' )?.theme
				// eslint-disable-next-line camelcase
			} ${ branded_type }` }
			style={ {
				backgroundImage: `url(${ backgroundUrl })`,
				height: height + 'px',
			} }
			onClick={ onClick }
		></button>
	);
};
