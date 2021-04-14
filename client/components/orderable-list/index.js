/** @format */
/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { Icon, trash } from '@wordpress/icons';
import classNames from 'classnames';

/**
 * Internal dependencies
 */
import './style.scss';

const ListItemActions = ( { onManageClick, onDeleteClick } ) => {
	return (
		<div className="orderable-list__actions">
			<Button
				isLink
				className="orderable-list__action manage"
				onClick={ onManageClick }
			>
				{ __( 'Manage', 'woocommerce-payments' ) }
			</Button>
			<Button
				isLink
				aria-label="Delete"
				className="orderable-list__action delete"
				onClick={ onDeleteClick }
			>
				<Icon icon={ trash } size={ 24 } />
			</Button>
		</div>
	);
};

const ListItem = ( {
	id,
	label,
	description,
	onManageClick,
	onDeleteClick,
} ) => {
	return (
		<li className={ classNames( 'orderable-list__item', id ) }>
			<div className="orderable-list__drag-handle" />
			<div className="orderable-list__icon" />
			<div className="orderable-list__text">
				<Button
					isLink
					className="orderable-list__label"
					onClick={ onManageClick }
				>
					<strong>{ label }</strong>
				</Button>
				<div className="orderable-list__description">
					{ description }
				</div>
			</div>
			<ListItemActions
				onManageClick={ onManageClick }
				onDeleteClick={ onDeleteClick }
			/>
		</li>
	);
};

const OrderableList = ( {
	className,
	items,
	onManageClick = () => {},
	onDeleteClick = () => {},
} ) => {
	const showDragHandles = 1 < items.length;

	const handleManageClick = ( e, itemId ) => {
		e.preventDefault();
		onManageClick( itemId );
	};

	const handleDeleteClick = ( e, itemId ) => {
		e.preventDefault();
		onDeleteClick( itemId );
	};

	return (
		<ul
			className={ classNames(
				'orderable-list',
				{ 'show-drag-handles': showDragHandles },
				className
			) }
		>
			{ items.map( ( item ) => (
				<ListItem
					key={ item.id }
					onManageClick={ ( e ) => handleManageClick( e, item.id ) }
					onDeleteClick={ ( e ) => handleDeleteClick( e, item.id ) }
					{ ...item }
				/>
			) ) }
		</ul>
	);
};

export default OrderableList;
