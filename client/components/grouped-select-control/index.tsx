/**
 * External Dependencies
 */
import React, { useRef, useState } from 'react';
import { Icon, check, chevronDown, chevronUp } from '@wordpress/icons';
import classNames from 'classnames';
import { __ } from '@wordpress/i18n';
import { useSelect } from 'downshift';

/**
 * Internal Dependencies
 */
import './style.scss';

export interface Item {
	key: string;
	name: string;
	group: string;
	context?: string;
	className?: string;
}

export interface Group {
	key: string;
	name: string;
	className?: string;
}

interface ListItem extends Omit< Item, 'group' > {
	group?: string;
	items?: string[];
}

export interface GroupedSelectControlProps< ItemType > {
	label: string;
	options: ItemType[];
	groups: Group[];
	value?: ItemType;
	placeholder?: string;
	searchable?: boolean;
	className?: string;
	onChange: ( value?: ItemType ) => void;
}

const GroupedSelectControl = < ItemType extends Item >( {
	label,
	options: items,
	value,
	groups,
	placeholder,
	searchable,
	className,
	onChange,
}: GroupedSelectControlProps< ItemType > ): JSX.Element => {
	const searchRef = useRef< HTMLInputElement >( null );
	const previousStateRef = useRef< {
		visibleItems: Set< string >;
	} >();
	const groupKeys = groups.map( ( group ) => group.key );
	const mergedList = groups.reduce( ( acc, group ) => {
		const groupItems = items.filter( ( item ) => item.group === group.key );
		return [
			...acc,
			{
				...group,
				items: groupItems.map( ( item ) => item.key ),
			},
			...groupItems,
		];
	}, [] as ListItem[] );

	const [ openedGroups, setOpenedGroups ] = useState(
		new Set( [ groups[ 0 ]?.key ] )
	);

	const [ visibleItems, setVisibleItems ] = useState(
		new Set( [ ...groupKeys, ...( mergedList[ 0 ]?.items || [] ) ] )
	);

	const [ searchText, setSearchText ] = useState( '' );

	const itemsToRender = mergedList.filter( ( item ) =>
		visibleItems.has( item.key )
	);

	const {
		isOpen,
		selectedItem,
		getToggleButtonProps,
		getMenuProps,
		getLabelProps,
		highlightedIndex,
		getItemProps,
	} = useSelect( {
		items: itemsToRender,
		itemToString: ( item ) => item.name,
		selectedItem: value || ( {} as ItemType ),
		onSelectedItemChange: ( changes ) =>
			onChange( changes.selectedItem as ItemType ),
		stateReducer: ( state, { changes, type } ) => {
			if (
				searchable &&
				type === useSelect.stateChangeTypes.MenuKeyDownCharacter
			) {
				return state;
			}

			if ( changes.selectedItem && changes.selectedItem.items ) {
				if ( searchText ) return state;
				const key = changes.selectedItem.key;
				if ( openedGroups.has( key ) ) {
					openedGroups.delete( key );
					changes.selectedItem.items.forEach( ( itemKey ) =>
						visibleItems.delete( itemKey )
					);
				} else {
					openedGroups.add( key );
					changes.selectedItem.items.forEach( ( itemKey ) =>
						visibleItems.add( itemKey )
					);
				}
				setOpenedGroups( openedGroups );
				setVisibleItems( visibleItems );
				return state;
			}

			return changes;
		},
	} );

	const handleSearch = ( {
		target,
	}: React.ChangeEvent< HTMLInputElement > ) => {
		if ( ! previousStateRef.current ) {
			previousStateRef.current = {
				visibleItems: visibleItems,
			};
		}

		if ( target.value === '' ) {
			setVisibleItems( previousStateRef.current.visibleItems );
			previousStateRef.current = undefined;
		} else {
			const filteredItems = items.filter( ( item ) =>
				`${ item.name }${ item.context || '' }`
					.toLowerCase()
					.includes( target.value.toLowerCase() )
			);
			const filteredGroups = filteredItems.map( ( item ) => item.group );
			const filteredVisibleItems = new Set( [
				...filteredItems.map( ( i ) => i.key ),
				...filteredGroups,
			] );
			setVisibleItems( filteredVisibleItems );
		}

		setSearchText( target.value );
	};

	const menuProps = getMenuProps( {
		className: 'wcpay-component-grouped-select-control__list',
		'aria-hidden': ! isOpen,
		onFocus: () => searchRef.current?.focus(),
		onBlur: ( event: any ) => {
			if ( event.relatedTarget === searchRef.current ) {
				event.nativeEvent.preventDownshiftDefault = true;
			}
		},
		onKeyDown: ( event: any ) => {
			if ( event.code === 'Space' ) {
				event.nativeEvent.preventDownshiftDefault = true;
			}
		},
	} );

	return (
		<div
			className={ classNames(
				'wcpay-component-grouped-select-control',
				className
			) }
		>
			<label
				{ ...getLabelProps( {
					className: 'wcpay-component-grouped-select-control__label',
				} ) }
			>
				{ label }
			</label>
			<button
				{ ...getToggleButtonProps( {
					type: 'button',
					className: classNames(
						'components-text-control__input wcpay-component-grouped-select-control__button',
						{ placeholder }
					),
				} ) }
			>
				<span className="wcpay-component-grouped-select-control__button-value">
					{ selectedItem.name || placeholder }
				</span>
				<Icon
					icon={ chevronDown }
					className="wcpay-component-grouped-select-control__button-icon"
				/>
			</button>
			<ul { ...menuProps }>
				{ isOpen && (
					<>
						{ searchable && (
							<input
								className="wcpay-component-grouped-select-control__search"
								ref={ searchRef }
								type="text"
								value={ searchText }
								onChange={ handleSearch }
								tabIndex={ -1 }
								placeholder={ __(
									'Search…',
									'woocommerce-payments'
								) }
							/>
						) }
						<div className="wcpay-component-grouped-select-control__list-container">
							{ itemsToRender.map( ( item, index ) => {
								const isGroup = !! item.items;

								return (
									// eslint-disable-next-line react/jsx-key
									<li
										{ ...getItemProps( {
											item,
											index,
											key: item.key,
											className: classNames(
												'wcpay-component-grouped-select-control__item',
												item.className,
												{
													'is-highlighted':
														index ===
														highlightedIndex,
												},
												{
													'is-group': isGroup,
												}
											),
										} ) }
									>
										<div className="wcpay-component-grouped-select-control__item-content">
											{ item.name }
										</div>
										{ item.key === selectedItem.key && (
											<Icon icon={ check } />
										) }
										{ ! searchText && isGroup && (
											<Icon
												icon={
													openedGroups.has( item.key )
														? chevronUp
														: chevronDown
												}
											/>
										) }
									</li>
								);
							} ) }
						</div>
					</>
				) }
			</ul>
		</div>
	);
};

export default GroupedSelectControl;
