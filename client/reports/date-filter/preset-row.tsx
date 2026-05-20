/** @format */

/**
 * External dependencies
 */
import React, { useMemo } from 'react';
import classNames from 'classnames';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { getPresetsForOperator } from './presets';
import type { DateOperator } from './types';

export interface PresetRowProps {
	operator: DateOperator;
	activePreset: string;
	onSelect: ( preset: string ) => void;
}

export const PresetRow: React.FC< PresetRowProps > = ( {
	operator,
	activePreset,
	onSelect,
} ) => {
	const presets = useMemo(
		() => getPresetsForOperator( operator ),
		[ operator ]
	);

	return (
		<div
			className="wcpay-date-filter__preset-row"
			role="group"
			aria-label={ __( 'Date presets', 'woocommerce-payments' ) }
		>
			{ presets.map( ( preset ) => {
				const isActive = preset.value === activePreset;
				return (
					<button
						key={ preset.value }
						type="button"
						className={ classNames( 'wcpay-date-filter__preset', {
							'is-active': isActive,
						} ) }
						aria-pressed={ isActive }
						onClick={ () => onSelect( preset.value ) }
					>
						{ preset.label }
					</button>
				);
			} ) }
		</div>
	);
};
