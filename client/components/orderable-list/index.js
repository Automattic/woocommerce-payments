/** @format */
/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Button, Icon } from '@wordpress/components';

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
				className="orderable-list__action"
				onClick={ () => onDeleteClick( itemId ) }
			>
				<Icon icon="trash" size={ 24 } />
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
	showDragHandle,
} ) => {
	const domId = `orderable-list__item-${ id }`;

	return (
		<li id={ domId } className="orderable-list__item">
			<div className="orderable-list__drag-handle-container">
				{ showDragHandle ? (
					<div className="orderable-list__drag-handle">
						<Icon icon="move" size={ 24 } />
					</div>
				) : null }
			</div>
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

	if ( className ) {
		classNames.push( className );
	}

	const showDragHandle = 1 < items.length;

	return (
		<ul className={ classNames.join( ' ' ) }>
			{ items.map( ( item ) => (
				<ListItem
					key={ item.id }
					onManageClick={ onManageClick }
					onDeleteClick={ onDeleteClick }
					showDragHandle={ showDragHandle }
					{ ...item }
				/>
			) ) }
		</ul>
	);
};

export default OrderableList;
