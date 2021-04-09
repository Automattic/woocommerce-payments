/** @format */
/**
 * External dependencies
 */
import React, { useCallback, useState } from 'react';
import { Icon } from '@wordpress/components';
import classNames from 'classnames';
import { CSS } from '@dnd-kit/utilities';
import {
	DndContext,
	closestCenter,
	KeyboardSensor,
	PointerSensor,
	useSensor,
	useSensors,
} from '@dnd-kit/core';
import {
	arrayMove,
	SortableContext,
	sortableKeyboardCoordinates,
	verticalListSortingStrategy,
	useSortable,
} from '@dnd-kit/sortable';

/**
 * Internal dependencies
 */
import './style.scss';

const ListItem = ( { id, children, isDraggable } ) => {
	const {
		attributes,
		listeners,
		transform,
		transition,
		setDraggableNodeRef,
		setDroppableNodeRef,
	} = useSortable( {
		id,
	} );

	const style = {
		transform: CSS.Translate.toString( transform ),
		transition,
	};

	return (
		<li
			className="orderable-list__item"
			ref={ setDroppableNodeRef }
			style={ style }
		>
			<div className="orderable-list__drag-handle-container">
				{ isDraggable && (
					<div
						className="orderable-list__drag-handle"
						{ ...attributes }
						{ ...listeners }
						ref={ setDraggableNodeRef }
					>
						<Icon icon="move" size={ 24 } />
					</div>
				) }
			</div>
			{ children }
		</li>
	);
};

const OrderableList = ( { className, children } ) => {
	const isDraggable = 1 < React.Children.count( children );
	const childrenArray = React.Children.toArray( children );

	// TODO: remove
	const [ childrenKeys, setChildrenKeys ] = useState(
		childrenArray.map( ( child ) => child.key )
	);

	const sensors = useSensors(
		useSensor( PointerSensor ),
		useSensor( KeyboardSensor, {
			coordinateGetter: sortableKeyboardCoordinates,
		} )
	);

	const handleDragEnd = useCallback(
		( event ) => {
			const { active, over } = event;

			if ( active.id !== over.id ) {
				setChildrenKeys( ( oldItems ) => {
					const oldIndex = oldItems.indexOf( active.id );
					const newIndex = oldItems.indexOf( over.id );

					return arrayMove( oldItems, oldIndex, newIndex );
				} );
			}
		},
		[ setChildrenKeys ]
	);

	return (
		<DndContext
			sensors={ sensors }
			collisionDetection={ closestCenter }
			onDragEnd={ handleDragEnd }
		>
			<SortableContext
				items={ childrenKeys }
				strategy={ verticalListSortingStrategy }
			>
				<ul className={ classNames( 'orderable-list', className ) }>
					{ childrenKeys.map( ( childKey ) => (
						<ListItem
							key={ childKey }
							id={ childKey }
							isDraggable={ isDraggable }
						>
							{ childrenArray.find(
								( el ) => el.key === childKey
							) }
						</ListItem>
					) ) }
				</ul>
			</SortableContext>
		</DndContext>
	);
};

export default OrderableList;
