/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { List } from '@woocommerce/components';

/**
 * Internal dependencies.
 */
import './style.scss';

interface HorizontalListItem {
	title: string;
	content: string | React.ReactNode;
}

interface Props {
	items: ( false | HorizontalListItem )[];
}

const HorizontalList: React.FunctionComponent< Props > = ( props ) => {
	const { items } = props;
	return <List className="woocommerce-list--horizontal" items={ items } />;
};

export default HorizontalList;
