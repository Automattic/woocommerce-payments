/** @format **/

/**
 * External dependencies
 */
import * as React from 'react';
import { ExperimentalList } from '@woocommerce/experimental/build/experimental-list/experimental-list';
import { ExperimentalListItem } from '@woocommerce/experimental/build/experimental-list/experimental-list-item';

/**
 * Internal dependencies.
 */
import './style.scss';

const HorizontalList = ( props ) => {
	const items = props.items.map( ( item ) => {
		return (
			<ExperimentalListItem key={ item.title }>
				<div className="woocommerce-experimental-list__item-inner">
					<span className="woocommerce-experimental-list__item-title">
						{ item.title }
					</span>
					<span className="woocommerce-experimental-list__item-content">
						{ item.content }
					</span>
				</div>
			</ExperimentalListItem>
		);
	} );

	return (
		<ExperimentalList
			className="woocommerce-experimental-list--horizontal wcpayments-horizontal-list"
			children={ items }
		/>
	);
};

export default HorizontalList;
