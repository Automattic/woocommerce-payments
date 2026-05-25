/** @format */

/**
 * External dependencies
 */
import React, { useCallback, useId, useMemo } from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { Calendar } from './calendar';
import { DateInput } from './date-input';
import { OperatorSelect } from './operator-select';
import { PresetRow } from './preset-row';
import { matchPreset, resolvePreset } from './presets';
import type { DateFilterValue, DateOperator } from './types';

export interface PopoverBodyProps {
	operator: DateOperator;
	value: DateFilterValue | undefined;
	onChange: ( next: DateFilterValue ) => void;
	onOperatorChange: ( next: DateOperator ) => void;
	now: Date;
}

const todayYmd = ( now: Date ): string => {
	const today = new Date(
		Date.UTC( now.getUTCFullYear(), now.getUTCMonth(), now.getUTCDate() )
	);
	const y = today.getUTCFullYear();
	const m = String( today.getUTCMonth() + 1 ).padStart( 2, '0' );
	const d = String( today.getUTCDate() ).padStart( 2, '0' );
	return `${ y }-${ m }-${ d }`;
};

export const PopoverBody: React.FC< PopoverBodyProps > = ( {
	operator,
	value,
	onChange,
	onOperatorChange,
	now,
} ) => {
	const operatorSelectId = useId();

	const activePreset = useMemo( () => {
		if ( ! value || value.operator !== operator ) {
			return 'custom';
		}
		return matchPreset( value, now );
	}, [ operator, value, now ] );

	const handlePresetSelect = useCallback(
		( preset: string ) => {
			if ( preset === 'custom' ) {
				// Keep current value if shape already matches operator;
				// otherwise seed an empty-ish value so the inputs render.
				if ( value && value.operator === operator ) {
					return;
				}
				const today = todayYmd( now );
				if ( operator === 'between' ) {
					onChange( {
						operator: 'between',
						value: [ today, today ],
					} );
					return;
				}
				onChange( {
					operator,
					value: today,
				} as DateFilterValue );
				return;
			}
			const resolved = resolvePreset( preset, operator, now );
			if ( resolved ) {
				onChange( resolved );
			}
		},
		[ now, onChange, operator, value ]
	);

	const handleSingleInputChange = useCallback(
		( next: string ) => {
			if ( next === '' ) {
				return;
			}
			onChange( {
				operator,
				value: next,
			} as DateFilterValue );
		},
		[ onChange, operator ]
	);

	const handleRangeStartChange = useCallback(
		( next: string ) => {
			if ( next === '' ) {
				return;
			}
			const end = value?.operator === 'between' ? value.value[ 1 ] : next;
			onChange( {
				operator: 'between',
				value: [ next, end ],
			} );
		},
		[ onChange, value ]
	);

	const handleRangeEndChange = useCallback(
		( next: string ) => {
			if ( next === '' ) {
				return;
			}
			const start =
				value?.operator === 'between' ? value.value[ 0 ] : next;
			onChange( {
				operator: 'between',
				value: [ start, next ],
			} );
		},
		[ onChange, value ]
	);

	const isBetween = operator === 'between';
	const singleValue =
		value && value.operator !== 'between' && value.operator === operator
			? value.value
			: '';
	const rangeStart = value?.operator === 'between' ? value.value[ 0 ] : '';
	const rangeEnd = value?.operator === 'between' ? value.value[ 1 ] : '';

	return (
		<div className="wcpay-date-filter__popover-body">
			<div className="wcpay-date-filter__operator-row">
				<label
					className="wcpay-date-filter__operator-label"
					htmlFor={ operatorSelectId }
				>
					{ __( 'Date', 'woocommerce-payments' ) }
				</label>
				<OperatorSelect
					id={ operatorSelectId }
					value={ operator }
					onChange={ onOperatorChange }
				/>
			</div>
			<PresetRow
				operator={ operator }
				activePreset={ activePreset }
				onSelect={ handlePresetSelect }
			/>
			<div className="wcpay-date-filter__inputs-row">
				{ isBetween ? (
					<>
						<DateInput
							label={ __( 'Start', 'woocommerce-payments' ) }
							value={ rangeStart }
							onChange={ handleRangeStartChange }
						/>
						<DateInput
							label={ __( 'End', 'woocommerce-payments' ) }
							value={ rangeEnd }
							min={ rangeStart || undefined }
							onChange={ handleRangeEndChange }
						/>
					</>
				) : (
					<DateInput
						label={ __( 'Date', 'woocommerce-payments' ) }
						value={ singleValue }
						onChange={ handleSingleInputChange }
					/>
				) }
			</div>
			<Calendar
				operator={ operator }
				value={ value }
				onChange={ onChange }
			/>
		</div>
	);
};
