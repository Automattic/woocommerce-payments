/**
 * External dependencies
 */
import { useState, useEffect } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { getPaymentRequestData } from '../utils';

export const shouldUseGooglePayBrand = () => {
	const ua = window.navigator.userAgent.toLowerCase();
	const isChrome =
		/chrome/.test( ua ) &&
		! /edge|edg|opr|brave\//.test( ua ) &&
		'Google Inc.' === window.navigator.vendor;
	// newer versions of Brave do not have the userAgent string
	const isBrave = isChrome && window.navigator.brave;
	return isChrome && ! isBrave;
};

const useImageOrDefault = ( url, defaultUrl ) => {
	const [ state, setState ] = useState( url );
	useEffect( () => {
		// eslint-disable-next-line no-unused-vars
		const _img = (
			// eslint-disable-next-line jsx-a11y/alt-text
			<img src={ url } onError={ () => setState( defaultUrl ) } />
		);
	}, [ url, defaultUrl ] );
	return [ state ];
};

export const GooglePayButton = ( { onClick } ) => {
	const {
		theme,
		height,
		locale,
		// eslint-disable-next-line camelcase
		branded_type,
	} = getPaymentRequestData( 'button' );

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
			// eslint-disable-next-line camelcase
			className={ `gpay-button ${ theme } ${ branded_type }` }
			style={ {
				backgroundImage: `url(${ backgroundUrl })`,
				height: height + 'px',
			} }
			onClick={ onClick }
		></button>
	);
};
