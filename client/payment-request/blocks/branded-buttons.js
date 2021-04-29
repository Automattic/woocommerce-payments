/**
 * External dependencies
 */
import { useState, useEffect } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { getPaymentRequestData } from '../utils';

const useImageOrDefault = ( url, defaultUrl ) => {
	const [ state, setState ] = useState( url );
	useEffect( () => {
		// eslint-disable-next-line no-unused-vars
		const _img = (
			<img src={ url } onError={ () => setState( defaultUrl ) } alt="" />
		);
	}, [ url, defaultUrl ] );
	return [ state ];
};

export const GooglePayButton = ( { onClick } ) => {
	const {
		height,
		locale,
		// eslint-disable-next-line camelcase
		branded_type,
	} = getPaymentRequestData( 'button' );
	// Allowed themes for Google Pay button image are 'dark' and 'light'.
	const theme =
		'dark' === getPaymentRequestData( 'button' )?.theme ? 'dark' : 'light';

	// If we're using the short button type (i.e. logo only) make sure we get the logo only SVG.
	const googlePlaySvg =
		// eslint-disable-next-line camelcase
		'long' === branded_type
			? `https://www.gstatic.com/instantbuy/svg/${ theme }/${ locale }.svg`
			: `https://www.gstatic.com/instantbuy/svg/${ theme }_gpay.svg`;

	// Make sure the localized Google Pay button SVG exists, otherwise we fall back to the English version.
	const [ backgroundUrl ] = useImageOrDefault(
		googlePlaySvg,
		`https://www.gstatic.com/instantbuy/svg/${ theme }/en.svg`
	);

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
