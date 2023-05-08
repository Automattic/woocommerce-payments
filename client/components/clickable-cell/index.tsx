/** @format **/

/**
 * External dependencies
 */
import React from 'react';

/**
 * Internal dependencies
 */
import './style.scss';
import { Link } from '@woocommerce/components';

interface Props {
	/**
	 * The URL this clickable cell should take the user to.
	 */
	href: string;
}

const ClickableCell: React.FunctionComponent< React.PropsWithChildren<
	Props
> > = ( { href, children } ) => {
	if ( href ) {
		return (
			<Link
				href={ href }
				className="woocommerce-table__clickable-cell"
				tabIndex="-1"
			>
				{ children }
			</Link>
		);
	}

	return <>{ children }</>;
};

export default ClickableCell;
