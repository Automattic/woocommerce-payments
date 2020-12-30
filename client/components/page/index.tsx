import React from 'react';

/**
 * Internal dependencies
 */
import includeStripeJS from 'hooks/include-stripe-js';
import './style.scss';

type pageProps = {
	maxWidth?: string;
	isNarrow?: boolean;
	className?: string;
};

const Page: React.FunctionComponent< pageProps > = ( {
	children,
	maxWidth,
	isNarrow,
	className = '',
} ) => {
	const customStyle: React.CSSProperties | undefined = maxWidth
		? { maxWidth }
		: undefined;
	const classNames = [ className, 'woocommerce-payments-page' ];
	if ( isNarrow ) {
		classNames.push( 'is-narrow' );
	}

	includeStripeJS();

	return (
		<div className={ classNames.join( ' ' ) } style={ customStyle }>
			{ children }
		</div>
	);
};

export default Page;
