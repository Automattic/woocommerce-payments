/** @format */
/**
 * External dependencies
 */
import React from 'react';
import { Button } from '@wordpress/components';
import classNames from 'classnames';
import {
	DndContext,
	closestCenter,
	KeyboardSensor,
	TouchSensor,
	MouseSensor,
	useSensor,
	useSensors,
} from '@dnd-kit/core';
import {
	SortableContext,
	sortableKeyboardCoordinates,
	verticalListSortingStrategy,
	useSortable,
} from '@dnd-kit/sortable';
import {
	restrictToWindowEdges,
	restrictToVerticalAxis,
} from '@dnd-kit/modifiers';

/**
 * Internal dependencies
 */
import './style.scss';

const ListItem = ( { id, children, className } ) => {
	const {
		attributes,
		listeners,
		transform,
		transition,
		setDraggableNodeRef,
		setDroppableNodeRef,
		isDragging,
	} = useSortable( {
		id,
	} );

	const style = {
		transform: transform
			? `translate3d(0, ${
					transform.y ? Math.round( transform.y ) : 0
			  }px, ${ isDragging ? '100' : '-100' }px) ${
					isDragging ? 'scale(1.02)' : ''
			  }`
			: undefined,
		transition,
	};

	return (
		<li
			className={ classNames( 'orderable-list__item', className, {
				'is-dragged': isDragging,
			} ) }
			ref={ setDroppableNodeRef }
			style={ style }
		>
			<div className="orderable-list__drag-handle-wrapper">
				<Button
					className="orderable-list__drag-handle"
					{ ...attributes }
					{ ...listeners }
					ref={ setDraggableNodeRef }
				/>
			</div>
			{ children }
		</li>
	);
};

const modifiers = [ restrictToWindowEdges, restrictToVerticalAxis ];

const OrderableList = ( { className, children, onDragEnd } ) => {
	const hasDragHandles = 1 < React.Children.count( children );

	const childrenKeys = React.Children.map( children, ( child ) => child.key );

	const sensors = useSensors(
		useSensor( MouseSensor ),
		useSensor( TouchSensor ),
		useSensor( KeyboardSensor, {
			coordinateGetter: sortableKeyboardCoordinates,
		} )
	);

	return (
		<DndContext
			sensors={ sensors }
			collisionDetection={ closestCenter }
			onDragEnd={ onDragEnd }
			modifiers={ modifiers }
		>
			<SortableContext
				items={ childrenKeys }
				strategy={ verticalListSortingStrategy }
			>
				<ul
					className={ classNames( 'orderable-list', className, {
						'has-drag-handles': hasDragHandles,
					} ) }
				>
					{ React.Children.map( children, ( child ) => {
						return (
							<ListItem
								key={ child.key }
								id={ child.key }
								className={ child.props.className }
							>
								{ child }
							</ListItem>
						);
					} ) }
				</ul>
			</SortableContext>
		</DndContext>
	);
};

export default OrderableList;
