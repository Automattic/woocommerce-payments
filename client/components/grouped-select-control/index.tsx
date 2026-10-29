/**
 * External Dependencies
 */
import React, { useCallback, useRef, useState } from 'react';
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

	// Hoisted so the ref reads happen inside DOM event handlers (post-commit),
	// not in the render body where react-hooks/refs flags them.
	const handleMenuFocus = useCallback( () => {
		searchRef.current?.focus();
	}, [] );

	const handleMenuBlur = useCallback( ( event: any ) => {
		if ( event.relatedTarget === searchRef.current ) {
			event.nativeEvent.preventDownshiftDefault = true;
		}
	}, [] );

	// `getMenuProps` is a downshift accessor that just merges the handlers
	// into the spread menu props — it does not read `searchRef` itself; the
	// ref is only read inside the hoisted DOM-event handlers (post-commit).
	// The rule flags the shape of the call rather than an actual render-time
	// ref read, so a focused carve-out is warranted here.
	// eslint-disable-next-line react-hooks/refs
	const menuProps = getMenuProps( {
		className: 'wcpay-component-grouped-select-control__list',
		'aria-hidden': ! isOpen,
		onFocus: handleMenuFocus,
		onBlur: handleMenuBlur,
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
