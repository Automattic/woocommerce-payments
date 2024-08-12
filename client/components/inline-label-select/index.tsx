/**
 * This is a copy of Gutenberg's CustomSelectControl component, found here:
 * https://github.com/WordPress/gutenberg/tree/7aa042605ff42bb437e650c39132c0aa8eb4ef95/packages/components/src/custom-select-control
 *
 * It has been forked from the existing WooPayments copy of this component (client/components/custom-select-control)
 * to match this specific select input design with an inline label and option hints.
 */

/**
 * External Dependencies
 */
import React from 'react';
import { Button } from '@wordpress/components';
import { check, chevronDown, Icon } from '@wordpress/icons';
import { useCallback } from '@wordpress/element';
import classNames from 'classnames';
import { __, sprintf } from '@wordpress/i18n';
import { useSelect, UseSelectState } from 'downshift';

/**
 * Internal Dependencies
 */
import './style.scss';

export interface SelectItem {
	/** The unique key for the item. */
	key: string;
	/** The display name of the item. */
	name?: string;
	/** Descriptive hint for the item, displayed to the right of the name. */
	hint?: string;
	/** Additional class name to apply to the item. */
	className?: string;
	/** Additional inline styles to apply to the item. */
	style?: React.CSSProperties;
}

export interface ControlProps< SelectItemType > {
	/** The name attribute for the select input. */
	name?: string;
	/** Additional class name to apply to the select control. */
	className?: string;
	/** The label for the select control. */
	label: string;
	/** The ID of an element that describes the select control. */
	describedBy?: string;
	/** A list of options/items for the select control. */
	options: SelectItemType[];
	/** The currently selected option/item. */
	value?: SelectItemType | null;
	/** A placeholder to display when no item is selected. */
	placeholder?: string;
	/** Callback function to run when the selected item changes. */
	onChange?: ( changes: Partial< UseSelectState< SelectItemType > > ) => void;
	/** A function to render the children of the item. Takes an item as an argument, must return a JSX element. */
	children?: ( item: SelectItemType ) => JSX.Element;
}

/**
 * Converts a select option/item object to a string.
 */
const itemToString = ( item: { name?: string } | null ) => item?.name || '';

/**
 * State reducer for the select component.
 * This is needed so that in Windows, where the menu does not necessarily open on
 * key up/down, you can still switch between options with the menu closed.
 */
const stateReducer = (
	{ selectedItem }: any,
	{ type, changes, props: { items } }: any
) => {
	switch ( type ) {
		case useSelect.stateChangeTypes.ToggleButtonKeyDownArrowDown:
			// If we already have a selected item, try to select the next one,
			// without circular navigation. Otherwise, select the first item.
			return {
				selectedItem:
					items[
						selectedItem
							? Math.min(
									items.indexOf( selectedItem ) + 1,
									items.length - 1
							  )
							: 0
					],
			};
		case useSelect.stateChangeTypes.ToggleButtonKeyDownArrowUp:
			// If we already have a selected item, try to select the previous one,
			// without circular navigation. Otherwise, select the last item.
			return {
				selectedItem:
					items[
						selectedItem
							? Math.max( items.indexOf( selectedItem ) - 1, 0 )
							: items.length - 1
					],
			};
		default:
			return changes;
	}
};

/**
 * InlineLabelSelect component.
 * A select control with a list of options, inline label, and option hints.
 */
function InlineLabelSelect< ItemType extends SelectItem >( {
	name,
	className,
	label,
	describedBy,
	options: items,
	onChange: onSelectedItemChange,
	value,
	placeholder,
	children,
}: ControlProps< ItemType > ): JSX.Element {
	const {
		getLabelProps,
		getToggleButtonProps,
		getMenuProps,
		getItemProps,
		isOpen,
		highlightedIndex,
		selectedItem,
	} = useSelect( {
		initialSelectedItem: items[ 0 ],
		items,
		itemToString,
		onSelectedItemChange,
		selectedItem: value || ( {} as ItemType ),
		stateReducer,
	} );

	const itemString = itemToString( selectedItem );

	function getDescribedBy() {
		if ( describedBy ) {
			return describedBy;
		}

		if ( ! itemString ) {
			return __( 'No selection' );
		}

		// translators: %s: The selected option.
		return sprintf( __( 'Currently selected: %s' ), itemString );
	}

	const menuProps = getMenuProps( {
		className: 'wcpay-filter components-custom-select-control__menu',
		'aria-hidden': ! isOpen,
	} );

	const onKeyDownHandler = useCallback(
		( e ) => {
			e.stopPropagation();
			menuProps?.onKeyDown?.( e );
		},
		[ menuProps ]
	);

	// We need this here, because the null active descendant is not fully ARIA compliant.
	if (
		menuProps[ 'aria-activedescendant' ]?.startsWith( 'downshift-null' )
	) {
		delete menuProps[ 'aria-activedescendant' ];
	}
	return (
		<div
			className={ classNames(
				'wcpay-filter components-custom-select-control',
				className
			) }
		>
			<Button
				{ ...getToggleButtonProps( {
					// This is needed because some speech recognition software don't support `aria-labelledby`.
					'aria-label': label,
					'aria-labelledby': undefined,
					'aria-describedby': getDescribedBy(),
					className: classNames(
						'wcpay-filter components-custom-select-control__button',
						{ placeholder: ! itemString }
					),
					name,
				} ) }
			>
				{
					/* eslint-disable-next-line jsx-a11y/label-has-associated-control, jsx-a11y/label-has-for */
					<label
						{ ...getLabelProps( {
							className:
								'wcpay-filter components-custom-select-control__label',
						} ) }
					>
						{ label }
					</label>
				}
				<span className="wcpay-filter components-custom-select-control__button-value">
					{ itemString || placeholder }
				</span>
				<Icon
					icon={ chevronDown }
					className="wcpay-filter components-custom-select-control__button-icon"
				/>
			</Button>
			{ /* eslint-disable-next-line jsx-a11y/no-noninteractive-element-interactions */ }
			<ul { ...menuProps } onKeyDown={ onKeyDownHandler }>
				{ isOpen &&
					items.map( ( item, index ) => (
						// eslint-disable-next-line react/jsx-key
						<li
							{ ...getItemProps( {
								item,
								index,
								key: item.key,
								className: classNames(
									item.className,
									'wcpay-filter components-custom-select-control__item',
									{
										'is-highlighted':
											index === highlightedIndex,
									}
								),
								style: item.style,
							} ) }
						>
							<Icon
								icon={ check }
								className="wcpay-filter components-custom-select-control__item-icon"
								visibility={
									item === selectedItem ? 'visible' : 'hidden'
								}
							/>
							{ children ? children( item ) : item.name }
							{ item.hint && (
								<span className="wcpay-filter components-custom-select-control__item-hint">
									{ item.hint }
								</span>
							) }
						</li>
					) ) }
			</ul>
		</div>
	);
}

export default InlineLabelSelect;
