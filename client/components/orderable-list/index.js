/** @format */
/**
 * External dependencies
 */
import React from 'react';
import classNames from 'classnames';
import { Button } from '@wordpress/components';

/**
 * Internal dependencies
 */
import './style.scss';

const ListItem = ( { children, className } ) => {
	return (
		<li className={ classNames( 'orderable-list__item', className ) }>
			<Button className="orderable-list__drag-handle" />
			{ children }
		</li>
	);
};

const OrderableList = ( { className, children } ) => {
	const childrenArray = React.Children.toArray( children );
	const showDragHandles = 1 < childrenArray.length;

	return (
		<ul
			className={ classNames(
				'orderable-list',
				{ 'show-drag-handles': showDragHandles },
				className
			) }
		>
			{ childrenArray.map( ( child ) => (
				<ListItem key={ child.key } className={ child.props.className }>
					{ child }
				</ListItem>
			) ) }
		</ul>
	);
};

export default OrderableList;
