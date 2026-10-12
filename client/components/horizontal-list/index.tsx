/** @format **/

/**
 * External dependencies
 */
import React from 'react';

/**
 * Internal dependencies.
 */
import './style.scss';

export interface HorizontalListItem {
	/**
	 * The title for the item, displayed above the content.
	 */
	title: string;
	/**
	 * The content that will be displayed in the list for this item.
	 */
	content: string | React.ReactNode;
}

interface Props {
	/**
	 * The items to display in the list.
	 */
	items: HorizontalListItem[];
}

export const HorizontalList: React.FunctionComponent< Props > = ( {
	items,
} ) => {
	return (
		<ul className="wcpay-horizontal-list">
			{ items.map( ( { title, content }, index ) => (
				<li key={ index } className="wcpay-horizontal-list__item">
					<div className="wcpay-horizontal-list__item-inner">
						<div className="wcpay-horizontal-list__item-text">
							<span className="wcpay-horizontal-list__item-title">
								{ title }
							</span>
							{ content && (
								<span className="wcpay-horizontal-list__item-content">
									{ content }
								</span>
							) }
						</div>
					</div>
				</li>
			) ) }
		</ul>
	);
};
