/**
 * Internal dependencies
 */
import useStripeJS from 'hooks/use-stripe-js';
import './style.scss';

const Page = ( { children, maxWidth, isNarrow, className = '' } ) => {
	const customStyle = maxWidth ? { maxWidth } : null;
	const classNames = [ className, 'woocommerce-payments-page' ];
	if ( isNarrow ) {
		classNames.push( 'is-narrow' );
	}

	useStripeJS();

	return (
		<div className={ classNames.join( ' ' ) } style={ customStyle }>
			{ children }
		</div>
	);
};

export default Page;
