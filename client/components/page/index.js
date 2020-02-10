/**
 * External dependencies
 */

/**
 * Internal dependencies.
 */

import './style.scss';

const Page = ( { children, maxWidth, isNarrow } ) => {
	const customStyle = maxWidth ? { maxWidth } : null;
	const classNames = [ 'woocommerce-payments-page' ];
	if ( isNarrow ) {
		classNames.push( 'is-narrow' );
	}

	return <div className={ classNames.join( ' ' ) } style={ customStyle }>{children}</div>;
};

export default Page;
