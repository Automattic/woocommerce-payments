/** @format */

/**
 * External dependencies
 */
import React from 'react';
import classNames from 'classnames';
import { __, sprintf } from '@wordpress/i18n';
import { Icon, closeSmall } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import {
	formatDateFilterSummary,
	formatDateFilterChipLabel,
	operatorLabel,
} from './formatters';
import type { DateFilterValue } from './types';

export interface ChipProps {
	value: DateFilterValue | undefined;
	label: string;
	isOpen: boolean;
	onToggle: () => void;
	onClear: () => void;
}

export const Chip = React.forwardRef< HTMLButtonElement, ChipProps >(
	( { value, label, isOpen, onToggle, onClear }, ref ) => {
		const isActive = !! value;
		const accessibleName = isActive
			? sprintf(
					/* translators: 1: filter label, 2: active filter description. */
					__( '%1$s filter: %2$s', 'woocommerce-payments' ),
					label,
					formatDateFilterChipLabel( value as DateFilterValue )
			  )
			: label;

		// When active, render as "<label> <operator>: <summary>" with the
		// "<label> <operator>:" segment bolded — matches DataViews' filter
		// chips, which bold the filter name.
		const prefix = isActive
			? sprintf(
					/* translators: 1: filter label, 2: operator. */
					__( '%1$s %2$s:', 'woocommerce-payments' ),
					label,
					operatorLabel( ( value as DateFilterValue ).operator )
			  )
			: label;
		const summary = isActive
			? formatDateFilterSummary( value as DateFilterValue )
			: '';

		return (
			<div
				className={ classNames( 'wcpay-date-filter__chip', {
					'has-values': isActive,
					'is-open': isOpen,
				} ) }
			>
				<button
					type="button"
					ref={ ref }
					className="wcpay-date-filter__chip-trigger"
					aria-haspopup="dialog"
					aria-expanded={ isOpen }
					aria-label={ accessibleName }
					onClick={ onToggle }
				>
					<span className="wcpay-date-filter__chip-prefix">
						{ prefix }
					</span>
					{ summary && (
						<span className="wcpay-date-filter__chip-summary">
							{ ' ' }
							{ summary }
						</span>
					) }
				</button>
				{ isActive && (
					<button
						type="button"
						className="wcpay-date-filter__chip-clear"
						aria-label={ sprintf(
							/* translators: %s: filter label (e.g., "Date"). */
							__( 'Clear %s filter', 'woocommerce-payments' ),
							label
						) }
						onClick={ onClear }
					>
						<Icon icon={ closeSmall } size={ 16 } />
					</button>
				) }
			</div>
		);
	}
);
