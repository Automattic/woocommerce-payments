/**
 * This is a copy of the CustomSelectControl component, found here:
 * https://github.com/WordPress/gutenberg/tree/7aa042605ff42bb437e650c39132c0aa8eb4ef95/packages/components/src/custom-select-control
 *
 * It has been modified to support using a custom component as option, and placeholder. A functionality
 * that was not possible within the current implementation of CustomSelectControl.
 */

/**
 * External Dependencies
 */
import React from 'react';
import { Button } from '@wordpress/components';
import { Icon, check, chevronDown } from '@wordpress/icons';
import { useCallback } from '@wordpress/element';
import classNames from 'classnames';
import { __, sprintf } from '@wordpress/i18n';
import { useSelect, UseSelectState } from 'downshift';

/**
 * Internal Dependencies
 */
import './style.scss';

export interface Item {
	key: string;
	name?: string;
	className?: string;
	style?: React.CSSProperties;
}

interface ControlProps< ItemType > {
	className?: string;
	label: string;
	describedBy?: string;
	options: ItemType[];
	value?: ItemType;
	placeholder?: string;
	onChange?: ( changes: Partial< UseSelectState< ItemType > > ) => void;
	children?: ( item: ItemType ) => JSX.Element;
}

const itemToString = ( item: { name?: string } ) => item?.name || '';
// This is needed so that in Windows, where
// the menu does not necessarily open on
// key up/down, you can still switch between
// options with the menu closed.
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

function CustomSelectControl< ItemType extends Item >( {
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
		className: 'components-custom-select-control__menu',
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
				'wcpay components-custom-select-control',
				className
			) }
		>
			{
				/* eslint-disable-next-line jsx-a11y/label-has-associated-control, jsx-a11y/label-has-for */
				<label
					{ ...getLabelProps( {
						className: 'components-custom-select-control__label',
					} ) }
				>
					{ label }
				</label>
			}
			<Button
				{ ...getToggleButtonProps( {
					// This is needed because some speech recognition software don't support `aria-labelledby`.
					'aria-label': label,
					'aria-labelledby': undefined,
					'aria-describedby': getDescribedBy(),
					className: classNames(
						'components-custom-select-control__button',
						{ placeholder: ! itemString }
					),
				} ) }
			>
				{ itemString || placeholder }
				<Icon
					icon={ chevronDown }
					className="components-custom-select-control__button-icon"
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
									'components-custom-select-control__item',
									{
										'is-highlighted':
											index === highlightedIndex,
									}
								),
								style: item.style,
							} ) }
						>
							{ children ? children( item ) : item.name }
							{ item === selectedItem && (
								<Icon
									icon={ check }
									className="components-custom-select-control__item-icon"
								/>
							) }
						</li>
					) ) }
			</ul>
		</div>
	);
}

export default CustomSelectControl;
