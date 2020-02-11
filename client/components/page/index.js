/**
 * Internal dependencies
 */
import './style.scss';

const Page = ( { children, maxWidth, isNarrow, className = '' } ) => {
	const customStyle = maxWidth ? { maxWidth } : null;
	const classNames = [ className, 'woocommerce-payments-page' ];
	if ( isNarrow ) {
		classNames.push( 'is-narrow' );
	}

	return <div className={ classNames.join( ' ' ) } style={ customStyle }>{ children }</div>;
};

export default Page;
