/** @format */

/**
 * External dependencies
 */
import React, { useCallback, useEffect, useRef, useState } from 'react';
import { Popover } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
// react-day-picker resolves ./style.css via its package.json "exports" map
// (./src/style.css); webpack reads exports, but eslint-plugin-import does not.
// eslint-disable-next-line import/no-unresolved
import 'react-day-picker/style.css';

/**
 * Internal dependencies
 */
import { Chip } from './chip';
import { PopoverBody } from './popover-body';
import './style.scss';
import type { DateFilterValue, DateOperator } from './types';

export type { DateFilterValue, DateOperator } from './types';
export { parseDateFilterFromQuery, serializeDateFilterToQuery } from './url';

/*
 * NOTE: the DateFilter component below is slated for removal, but parts of
 * its UI are still live today: the Balance report uses the native DataViews
 * date filter (no consumers left there), while the Fees report still imports
 * PopoverBody until its native-filters PR lands. The module's utilities
 * (types.ts, url.ts, presets.ts, formatters.ts) stay either way: both
 * reports build on them. Once the Fees PR is merged, delete the component UI
 * (DateFilter, chip, popover, calendar) in a follow-up rather than letting
 * it linger unused — but not before.
 */
export interface DateFilterProps {
	value: DateFilterValue | undefined;
	onChange: ( next: DateFilterValue | undefined ) => void;
	/**
	 * When provided, replaces the default clear behavior (`onChange( undefined )`).
	 * On chip clear `onClear` runs instead and `onChange` is NOT called.
	 */
	onClear?: () => void;
	label?: string;
	defaultOperator?: DateOperator;
	now?: Date;
}

export const DateFilter: React.FC< DateFilterProps > = ( {
	value,
	onChange,
	onClear,
	label,
	defaultOperator = 'between',
	now,
} ) => {
	const chipLabel = label ?? __( 'Date', 'woocommerce-payments' );
	const referenceNow = now ?? new Date();

	const [ isOpen, setIsOpen ] = useState( false );
	const [ operator, setOperator ] = useState< DateOperator >(
		value?.operator ?? defaultOperator
	);
	const triggerRef = useRef< HTMLButtonElement >( null );

	// Keep the operator state aligned with the active value so the popover
	// opens against the right operator after URL/back-button navigation.
	useEffect( () => {
		if ( value && value.operator !== operator ) {
			setOperator( value.operator );
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ value?.operator ] );

	const handleToggle = useCallback( () => {
		setIsOpen( ( open ) => ! open );
	}, [] );

	const handleClose = useCallback( () => {
		setIsOpen( false );
	}, [] );

	const handleClear = useCallback( () => {
		if ( onClear ) {
			onClear();
		} else {
			onChange( undefined );
		}
		setIsOpen( false );
	}, [ onChange, onClear ] );

	const handleOperatorChange = useCallback(
		( next: DateOperator ) => {
			setOperator( next );
			// When the operator switches, drop any value that no longer fits
			// the new shape (e.g., a single date when switching to `between`).
			if ( value && value.operator !== next ) {
				onChange( undefined );
			}
		},
		[ onChange, value ]
	);

	const handleValueChange = useCallback(
		( next: DateFilterValue ) => {
			onChange( next );
		},
		[ onChange ]
	);

	return (
		<div className="wcpay-date-filter">
			<Chip
				ref={ triggerRef }
				value={ value }
				label={ chipLabel }
				isOpen={ isOpen }
				onToggle={ handleToggle }
				onClear={ handleClear }
			/>
			{ isOpen && (
				<Popover
					className="wcpay-date-filter__popover"
					role="dialog"
					aria-label={ __( 'Date filter', 'woocommerce-payments' ) }
					anchor={ triggerRef.current ?? undefined }
					onClose={ handleClose }
					placement="bottom-start"
					focusOnMount="firstElement"
				>
					<PopoverBody
						operator={ operator }
						value={ value }
						onChange={ handleValueChange }
						onOperatorChange={ handleOperatorChange }
						now={ referenceNow }
					/>
				</Popover>
			) }
		</div>
	);
};

export default DateFilter;
