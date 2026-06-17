/** @format */

/**
 * External dependencies
 */
import React, { useCallback, useEffect, useRef } from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import type { DateFilterValue } from './types';
import type { RangePreset } from './presets';
import { matchPreset, resolvePreset } from './presets';

const injectedDateRangePresetClass = 'wcpay-reports-date-range-preset';
const injectedDateRangePresetInsertClass =
	'wcpay-reports-date-range-preset-insert';
const forcedCustomDatePresetDisabledClass =
	'wcpay-reports-date-range-preset--custom-disabled';
const dateRangePresetButtonClass = `components-button is-tertiary is-small dataviews-controls__date-preset ${ injectedDateRangePresetClass }`;
const customDatePresetLabel = __( 'Custom', 'woocommerce-payments' );

const dataViewsDateRangePresets = [
	{
		preset: 'last_month',
		label: __( 'Previous month', 'woocommerce-payments' ),
	},
	{
		preset: 'last_year',
		label: __( 'Previous year', 'woocommerce-payments' ),
	},
] as const;

type ReportDateRangePreset = Extract<
	RangePreset,
	( typeof dataViewsDateRangePresets )[ number ][ 'preset' ]
>;

type DateRangePresetInsertionPoint = {
	parent: HTMLElement;
	before: Element;
};

const getDateRangePresetInsertionPoint = (
	ownerDocument: Document
): DateRangePresetInsertionPoint | null => {
	const popovers = ownerDocument.querySelectorAll< HTMLElement >(
		'.dataviews-filters__summary-popover'
	);

	for ( const popover of Array.from( popovers ) ) {
		const firstNativePreset = popover.querySelector< HTMLElement >(
			`.dataviews-controls__date-preset:not(.${ injectedDateRangePresetClass })`
		);
		if ( firstNativePreset?.parentElement ) {
			return {
				parent: firstNativePreset.parentElement,
				before: firstNativePreset,
			};
		}

		const rangeInputs = popover.querySelector< HTMLElement >(
			'.dataviews-controls__date-range-inputs'
		);
		if ( rangeInputs?.parentElement ) {
			return {
				parent: rangeInputs.parentElement,
				before: rangeInputs,
			};
		}
	}

	return null;
};

const getSelectedDateRangePreset = (
	dateValue: DateFilterValue | undefined,
	now: Date
): ReportDateRangePreset | null => {
	if ( ! dateValue || dateValue.operator !== 'between' ) {
		return null;
	}

	const preset = matchPreset( dateValue, now );
	return preset === 'last_month' || preset === 'last_year' ? preset : null;
};

const syncNativeDatePresetPressedState = (
	ownerDocument: Document,
	selectedPreset: ReportDateRangePreset | null
): void => {
	const nativePresetButtons =
		ownerDocument.querySelectorAll< HTMLButtonElement >(
			`.dataviews-filters__summary-popover .dataviews-controls__date-preset:not(.${ injectedDateRangePresetClass })`
		);
	const buttons = Array.from( nativePresetButtons );
	const customPresetButton = buttons.find(
		( button ) => button.textContent?.trim() === customDatePresetLabel
	);
	const hasNativePresetSelected = buttons.some(
		( button ) =>
			button !== customPresetButton &&
			button.getAttribute( 'aria-pressed' ) === 'true'
	);

	for ( const button of buttons ) {
		if ( selectedPreset ) {
			if ( button.getAttribute( 'aria-pressed' ) !== 'false' ) {
				button.setAttribute( 'aria-pressed', 'false' );
			}
			if ( button.classList.contains( 'is-pressed' ) ) {
				button.classList.remove( 'is-pressed' );
			}
		}
	}

	if ( ! customPresetButton ) {
		return;
	}

	if ( selectedPreset ) {
		if ( customPresetButton.getAttribute( 'aria-disabled' ) !== 'true' ) {
			customPresetButton.setAttribute( 'aria-disabled', 'true' );
		}
		if (
			! customPresetButton.classList.contains(
				forcedCustomDatePresetDisabledClass
			)
		) {
			customPresetButton.classList.add(
				forcedCustomDatePresetDisabledClass
			);
		}
		return;
	}

	if (
		customPresetButton.classList.contains(
			forcedCustomDatePresetDisabledClass
		)
	) {
		customPresetButton.classList.remove(
			forcedCustomDatePresetDisabledClass
		);

		if ( ! hasNativePresetSelected ) {
			customPresetButton.removeAttribute( 'aria-disabled' );
		}
	}
};

const syncDateRangePresetButtons = (
	portalNode: HTMLElement,
	selectedPreset: ReportDateRangePreset | null,
	onPresetClick: ( preset: ReportDateRangePreset ) => void
): void => {
	for ( const { preset, label } of dataViewsDateRangePresets ) {
		const selector = `[data-wcpay-date-range-preset="${ preset }"]`;
		let button = portalNode.querySelector< HTMLButtonElement >( selector );
		if ( ! button ) {
			button = portalNode.ownerDocument.createElement( 'button' );
			button.type = 'button';
			button.className = dateRangePresetButtonClass;
			button.dataset.wcpayDateRangePreset = preset;
			button.onclick = () => onPresetClick( preset );
			portalNode.appendChild( button );
		}

		const isSelected = selectedPreset === preset;
		const ariaPressed = isSelected ? 'true' : 'false';
		if ( button.textContent !== label ) {
			button.textContent = label;
		}
		if ( button.getAttribute( 'aria-pressed' ) !== ariaPressed ) {
			button.setAttribute( 'aria-pressed', ariaPressed );
		}
		if ( button.classList.contains( 'is-pressed' ) !== isSelected ) {
			button.classList.toggle( 'is-pressed', isSelected );
		}
	}
};

export const DataViewsDateRangePresetPortal = ( {
	rootRef,
	dateValue,
	dateFilterNow,
	onDateChange,
}: {
	rootRef: React.RefObject< HTMLElement | null >;
	dateValue: DateFilterValue | undefined;
	dateFilterNow?: Date;
	onDateChange: ( next: DateFilterValue ) => void;
} ): null => {
	const portalNodeRef = useRef< HTMLDivElement | null >( null );
	const stableDateFilterNow = useRef( dateFilterNow ?? new Date() ).current;
	const selectedPreset = getSelectedDateRangePreset(
		dateValue,
		stableDateFilterNow
	);
	const applyDatePreset = useCallback(
		( preset: ReportDateRangePreset ) => {
			const nextValue = resolvePreset(
				preset,
				'between',
				stableDateFilterNow
			);
			if ( nextValue ) {
				onDateChange( nextValue );
			}
		},
		[ onDateChange, stableDateFilterNow ]
	);
	const applyDatePresetRef = useRef( applyDatePreset );
	applyDatePresetRef.current = applyDatePreset;

	const syncPortalNode = useCallback( () => {
		const ownerDocument = rootRef.current?.ownerDocument ?? document;
		const insertionPoint =
			getDateRangePresetInsertionPoint( ownerDocument );

		if ( ! insertionPoint ) {
			syncNativeDatePresetPressedState( ownerDocument, selectedPreset );
			if (
				portalNodeRef.current &&
				! portalNodeRef.current.isConnected
			) {
				portalNodeRef.current = null;
			}
			return;
		}

		syncNativeDatePresetPressedState( ownerDocument, selectedPreset );

		if (
			portalNodeRef.current?.parentElement === insertionPoint.parent &&
			portalNodeRef.current.nextElementSibling === insertionPoint.before
		) {
			syncDateRangePresetButtons(
				portalNodeRef.current,
				selectedPreset,
				( preset ) => applyDatePresetRef.current( preset )
			);
			return;
		}

		portalNodeRef.current?.remove();

		const nextPortalNode = ownerDocument.createElement( 'div' );
		nextPortalNode.className = injectedDateRangePresetInsertClass;
		nextPortalNode.style.display = 'contents';
		insertionPoint.parent.insertBefore(
			nextPortalNode,
			insertionPoint.before
		);

		portalNodeRef.current = nextPortalNode;
		syncDateRangePresetButtons(
			nextPortalNode,
			selectedPreset,
			( preset ) => applyDatePresetRef.current( preset )
		);
	}, [ rootRef, selectedPreset ] );

	useEffect( () => {
		const ownerDocument = rootRef.current?.ownerDocument ?? document;
		syncPortalNode();

		const observer = new MutationObserver( syncPortalNode );
		observer.observe( ownerDocument.body, {
			childList: true,
			subtree: true,
		} );

		return () => {
			observer.disconnect();
			portalNodeRef.current?.remove();
			portalNodeRef.current = null;
		};
	}, [ rootRef, syncPortalNode ] );

	return null;
};
