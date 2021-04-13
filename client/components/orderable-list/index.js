/** @format */
/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { Icon, trash } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import './style.scss';

const ListItemIcon = ( { icon } ) => {
	const classNames = [ 'orderable-list__icon-container' ];

	if ( icon ) {
		classNames.push( 'orderable-list__icon-container--has-icon' );
	}

	return (
		<div className={ classNames.join( ' ' ) }>{ icon ? icon : null }</div>
	);
};

const ListItemActions = ( { itemId, onManageClick, onDeleteClick } ) => {
	return (
		<div className="orderable-list__actions">
			<Button
				isLink
				className="orderable-list__action"
				onClick={ () => onManageClick( itemId ) }
			>
				{ __( 'Manage', 'woocommerce-payments' ) }
			</Button>
			<Button
				isLink
				aria-label="Delete"
				className="orderable-list__action"
				onClick={ () => onDeleteClick( itemId ) }
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
	icon,
	onManageClick,
	onDeleteClick,
} ) => {
	const domId = `orderable-list__item-${ id }`;

	return (
		<li id={ domId } className="orderable-list__item">
			<div className="orderable-list__drag-handle" />
			<ListItemIcon icon={ icon } />
			<div className="orderable-list__text">
				<Button
					isLink
					className="orderable-list__label"
					onClick={ () => onManageClick( id ) }
				>
					<strong>{ label }</strong>
				</Button>
				<div className="orderable-list__description">
					{ description }
				</div>
			</div>
			<ListItemActions
				itemId={ id }
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
	const classNames = [ 'orderable-list' ];
	const showDragHandles = 1 < items.length;

	if ( className ) {
		classNames.push( className );
	}

	if ( showDragHandles ) {
		classNames.push( 'show-drag-handles' );
	}

	return (
		<ul className={ classNames.join( ' ' ) }>
			{ items.map( ( item ) => (
				<ListItem
					key={ item.id }
					onManageClick={ onManageClick }
					onDeleteClick={ onDeleteClick }
					{ ...item }
				/>
			) ) }
		</ul>
	);
};

export default OrderableList;
