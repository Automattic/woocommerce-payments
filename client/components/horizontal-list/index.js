/** @format **/

/**
 * External dependencies
 */
import { List } from '@woocommerce/components';

/**
 * Internal dependencies.
 */
import './style.scss';

const HorizontalList = ( props ) => {
	const { items } = props;
	return (
		<List className="woocommerce-list--horizontal" items={ items } />
	);
};

export default HorizontalList;
