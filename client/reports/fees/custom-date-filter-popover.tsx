/** @format */

/**
 * External dependencies
 */
import React, { useCallback, useMemo, useState } from 'react';
import { Popover } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
// react-day-picker resolves ./style.css via its package.json "exports" map
// (./src/style.css); webpack reads exports, but eslint-plugin-import does not.
// eslint-disable-next-line import/no-unresolved
import 'react-day-picker/style.css';

/**
 * Internal dependencies
 */
import { PopoverBody } from 'wcpay/reports/date-filter/popover-body';
import type { DateFilterValue, DateOperator } from 'wcpay/reports/date-filter';
import '../date-filter/style.scss';

interface CustomDateFilterPopoverProps {
	anchor: HTMLElement | null;
	fallbackFocus?: HTMLElement | null;
	id?: string;
	initialValue: DateFilterValue | undefined;
	onChange: ( next: DateFilterValue ) => void;
	onClose: () => void;
}

const toYmd = ( d: Date ): string =>
	`${ d.getFullYear() }-${ String( d.getMonth() + 1 ).padStart(
		2,
		'0'
	) }-${ String( d.getDate() ).padStart( 2, '0' ) }`;

const getDefaultDateValue = (
	operator: DateOperator,
	now: Date
): DateFilterValue => {
	const today = toYmd( now );
	if ( operator === 'between' ) {
		return {
			operator: 'between',
			value: [ today, today ],
		};
	}
	return {
		operator,
		value: today,
	} as DateFilterValue;
};

export const CustomDateFilterPopover: React.FC<
	CustomDateFilterPopoverProps
> = ( { anchor, fallbackFocus, id, initialValue, onChange, onClose } ) => {
	const now = useMemo( () => new Date(), [] );
	const [ operator, setOperator ] = useState< DateOperator >(
		initialValue?.operator ?? 'between'
	);
	const [ draft, setDraft ] = useState< DateFilterValue >(
		initialValue ?? getDefaultDateValue( 'between', now )
	);

	const returnFocus = useCallback( () => {
		window.requestAnimationFrame( () => {
			if ( anchor && document.contains( anchor ) ) {
				anchor.focus();
			} else if ( fallbackFocus && document.contains( fallbackFocus ) ) {
				fallbackFocus.focus();
			}
		} );
	}, [ anchor, fallbackFocus ] );

	const handleClose = useCallback( () => {
		onClose();
		returnFocus();
	}, [ onClose, returnFocus ] );

	const handleOperatorChange = useCallback(
		( next: DateOperator ) => {
			setOperator( next );
			setDraft( getDefaultDateValue( next, now ) );
		},
		[ now ]
	);

	const handleChange = useCallback(
		( next: DateFilterValue ) => {
			setDraft( next );
			setOperator( next.operator );
			onChange( next );
		},
		[ onChange ]
	);

	return (
		<Popover
			id={ id }
			className="wcpay-date-filter__popover"
			anchor={ anchor ?? undefined }
			onClose={ handleClose }
			placement="bottom-start"
			focusOnMount="firstElement"
			role="dialog"
			aria-label={ __( 'Custom date filter', 'woocommerce-payments' ) }
		>
			<PopoverBody
				operator={ operator }
				value={ draft }
				onChange={ handleChange }
				onOperatorChange={ handleOperatorChange }
				now={ now }
			/>
		</Popover>
	);
};
