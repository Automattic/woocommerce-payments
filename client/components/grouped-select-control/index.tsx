/**
 * External Dependencies
 */
import React, { useId, useRef, useState } from 'react';
import { check, chevronDown, chevronUp, Icon } from '@wordpress/icons';
import clsx from 'clsx';
import { __ } from '@wordpress/i18n';
import { useSelect, UseSelectState } from 'downshift';

/**
 * Internal Dependencies
 */
import './style.scss';

export interface ListItem {
	key: string;
	name: string;
	group?: string;
	context?: string;
	className?: string;
	items?: string[];
}

export interface GroupedSelectControlProps< ItemType > {
	label: string;
	options: ItemType[];
	value?: ItemType | null;
	placeholder?: string;
	searchable?: boolean;
	name?: string;
	className?: string;
	onChange?: ( changes: Partial< UseSelectState< ItemType > > ) => void;
}

const GroupedSelectControl = < ItemType extends ListItem >( {
	name,
	className,
	label,
	options: listItems,
	onChange: onSelectedItemChange,
	value,
	placeholder,
	searchable,
}: GroupedSelectControlProps< ItemType > ): JSX.Element => {
	const searchRef = useRef< HTMLInputElement >( null );
	const instanceId = useId();
	const previousStateRef = useRef< {
		visibleItems: Set< string >;
	} >();
	const groupKeys = listItems
		.filter( ( item ) => item.items?.length )
		.map( ( group ) => group.key );

	const [ openedGroups, setOpenedGroups ] = useState(
		new Set( [ groupKeys[ 0 ] ] )
	);

	const [ visibleItems, setVisibleItems ] = useState(
		new Set( [ ...groupKeys, ...( listItems[ 0 ]?.items || [] ) ] )
	);

	const [ searchText, setSearchText ] = useState( '' );

	const itemsToRender = listItems.filter( ( item ) =>
		visibleItems.has( item.key )
	);

	// Groups only toggle their items, which search has already expanded, so
	// search results show them as headings that can't be highlighted or picked.
	const isSearching = searchText !== '';
	const selectableItems = isSearching
		? itemsToRender.filter( ( item ) => ! item.items )
		: itemsToRender;
	const getGroupHeadingId = ( groupKey: string ) =>
		`${ instanceId }-group-${ groupKey }`;
	const labelId = `${ instanceId }-label`;
	const menuId = `${ instanceId }-menu`;
	const getItemId = ( index: number ) => `${ instanceId }-item-${ index }`;

	const {
		isOpen,
		selectedItem,
		getToggleButtonProps,
		getMenuProps,
		getLabelProps,
		highlightedIndex,
		getItemProps,
	} = useSelect( {
		items: selectableItems,
		labelId,
		menuId,
		getItemId,
		itemToString: ( item ) => item?.name || '',
		selectedItem: value || ( {} as ItemType ),
		onSelectedItemChange,
		stateReducer: ( state, { changes, type } ) => {
			if (
				searchable &&
				type === useSelect.stateChangeTypes.MenuKeyDownCharacter
			) {
				return state;
			}

			if ( changes.selectedItem && changes.selectedItem.items ) {
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
			const filteredItems = listItems.filter(
				( item ) =>
					item?.group &&
					`${ item.name } ${ item.context || '' }`
						.toLowerCase()
						.includes( target.value.toLowerCase() )
			);
			const filteredGroups = filteredItems.map(
				( item ): string => item?.group || ''
			);
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
			className={ clsx(
				'wcpay-component-grouped-select-control',
				className
			) }
		>
			{ /* eslint-disable-next-line jsx-a11y/label-has-associated-control */ }
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
					className: clsx(
						'components-text-control__input wcpay-component-grouped-select-control__button',
						{ placeholder }
					),
					name,
				} ) }
			>
				<span className="wcpay-component-grouped-select-control__button-value">
					{ selectedItem?.name || placeholder }
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
							// Focus moves here while the menu is open, so the
							// input has to announce the highlighted option;
							// Downshift only marks it on the unfocused menu.
							<input
								className="wcpay-component-grouped-select-control__search"
								ref={ searchRef }
								type="text"
								role="combobox"
								aria-labelledby={ labelId }
								aria-controls={ menuId }
								aria-expanded={ isOpen }
								aria-autocomplete="list"
								aria-activedescendant={
									selectableItems[ highlightedIndex ]
										? getItemId( highlightedIndex )
										: undefined
								}
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
							{ itemsToRender.map( ( item ) => {
								const isGroup = !! item.items;

								if ( isSearching && isGroup ) {
									return (
										<li
											key={ item.key }
											id={ getGroupHeadingId( item.key ) }
											role="presentation"
											className="wcpay-component-grouped-select-control__item is-group"
										>
											<div className="wcpay-component-grouped-select-control__item-content">
												{ item.name }
											</div>
											<Icon
												icon={ chevronUp }
												size={ 18 }
											/>
										</li>
									);
								}

								const index = selectableItems.indexOf( item );

								return (
									// eslint-disable-next-line react/jsx-key
									<li
										{ ...getItemProps( {
											item,
											index,
											key: item.key,
											'aria-describedby':
												isSearching && item.group
													? getGroupHeadingId(
															item.group
														)
													: undefined,
											className: clsx(
												'wcpay-component-grouped-select-control__item',
												item.className,
												{
													'is-highlighted':
														index ===
														highlightedIndex,
												},
												{
													'is-group': isGroup,
													'is-item': ! isGroup,
												}
											),
										} ) }
									>
										<div className="wcpay-component-grouped-select-control__item-content">
											{ item.name }
										</div>
										{ item.key === selectedItem?.key && (
											<Icon icon={ check } />
										) }
										{ isGroup && (
											<Icon
												icon={
													openedGroups.has( item.key )
														? chevronUp
														: chevronDown
												}
												size={ 18 }
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
