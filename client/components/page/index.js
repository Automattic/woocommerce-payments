/**
 * External dependencies
 */
import { useEffect } from '@wordpress/element';

/**
 * Internal dependencies
 */
import enqueueFraudScripts from 'fraud-scripts';
import './style.scss';

const Page = ( { children, maxWidth, isNarrow, className = '' } ) => {
	const customStyle = maxWidth ? { maxWidth } : null;
	const classNames = [ className, 'woocommerce-payments-page' ];
	if ( isNarrow ) {
		classNames.push( 'is-narrow' );
	}

	const fraudScriptsConfig =
		'undefined' !== typeof wcpaySettings ? wcpaySettings.fraudServices : [];
	useEffect( () => enqueueFraudScripts( fraudScriptsConfig ), [] );

	return (
		<div className={ classNames.join( ' ' ) } style={ customStyle }>
			{ children }
		</div>
	);
};

export default Page;
