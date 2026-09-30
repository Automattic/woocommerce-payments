/** @format */

/**
 * External dependencies
 */
import React, { useCallback, useEffect, useRef, useState } from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import type { DateFilterValue } from './types';
import type { RangePreset } from './presets';
import { getRangePresetLabel, matchPreset, resolvePreset } from './presets';

const injectedDateRangePresetClass = 'wcpay-reports-date-range-preset';
const injectedDateRangePresetInsertClass =
	'wcpay-reports-date-range-preset-insert';
const dateRangePresetButtonClass = `components-button is-tertiary is-small dataviews-controls__date-preset ${ injectedDateRangePresetClass }`;
// DataViews owns this native label and translates it with the default domain.
// eslint-disable-next-line @wordpress/i18n-text-domain
const customDatePresetLabel = __( 'Custom' );

type ReportDateRangePreset = Extract< RangePreset, 'last_month' | 'last_year' >;
const dataViewsDateRangePresetKeys: ReportDateRangePreset[] = [
	'last_month',
	'last_year',
];
const dataViewsDateRangePresets = dataViewsDateRangePresetKeys.map(
	( preset ) => ( {
		preset,
		label: getRangePresetLabel( preset ),
	} )
);

type DateRangePresetInsertionPoint = {
	popover: HTMLElement;
	parent: HTMLElement;
	before: Element;
};

type SyncPortalNodeOptions = {
	force?: boolean;
};

// DataViews does not expose a public preset-extension API. These selectors are
// private DataViews markup verified against the `@wordpress/dataviews` version
// bundled with this branch; if upstream renames them, the injected presets will
// silently stop rendering while the native date filter continues to work.
const getDateRangePresetInsertionPoint = (
	ownerDocument: Document
): DateRangePresetInsertionPoint | null => {
	const popovers = ownerDocument.querySelectorAll< HTMLElement >(
		'.dataviews-filters__summary-popover'
	);

	for ( const popover of Array.from( popovers ) ) {
		const rangeInputs = popover.querySelector< HTMLElement >(
			'.dataviews-controls__date-range-inputs'
		);
		if ( ! rangeInputs ) {
			continue;
		}

		const firstNativePreset = popover.querySelector< HTMLElement >(
			`.dataviews-controls__date-preset:not(.${ injectedDateRangePresetClass })`
		);
		if ( firstNativePreset?.parentElement ) {
			return {
				popover,
				parent: firstNativePreset.parentElement,
				before: firstNativePreset,
			};
		}

		if ( rangeInputs?.parentElement ) {
			return {
				popover,
				parent: rangeInputs.parentElement,
				before: rangeInputs,
			};
		}
	}

	return null;
};

const getDateRangePresetPopoverFallbackContainer = (
	ownerDocument: Document
): HTMLElement | null =>
	ownerDocument.querySelector< HTMLElement >(
		'.components-popover__fallback-container'
	);

const isDataViewsDateRangePreset = (
	preset: string
): preset is ReportDateRangePreset =>
	dataViewsDateRangePresetKeys.includes( preset as ReportDateRangePreset );

const getSelectedDateRangePreset = (
	dateValue: DateFilterValue | undefined,
	now: Date
): ReportDateRangePreset | null => {
	if ( ! dateValue || dateValue.operator !== 'between' ) {
		return null;
	}

	const preset = matchPreset( dateValue, now );
	return isDataViewsDateRangePreset( preset ) ? preset : null;
};

const syncNativeDatePresetState = (
	popover: HTMLElement,
	selectedPreset: ReportDateRangePreset | null
): void => {
	const nativePresetButtons = popover.querySelectorAll< HTMLButtonElement >(
		`.dataviews-controls__date-preset:not(.${ injectedDateRangePresetClass })`
	);
	const buttons = Array.from( nativePresetButtons );
	const customPresetButton = buttons.find(
		( button ) => button.textContent?.trim() === customDatePresetLabel
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

	customPresetButton.removeAttribute( 'aria-disabled' );
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
			button.textContent = label;
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
	onDateChange: ( next: DateFilterValue, referenceDate: Date ) => void;
} ): null => {
	const portalNodeRef = useRef< HTMLDivElement | null >( null );
	const stableDateFilterNowRef = useRef< Date | null >( null );
	if ( ! stableDateFilterNowRef.current ) {
		stableDateFilterNowRef.current = dateFilterNow ?? new Date();
	}
	const [ clickedDateFilterNow, setClickedDateFilterNow ] =
		useState< Date | null >( null );
	const selectedPreset =
		( clickedDateFilterNow &&
			getSelectedDateRangePreset( dateValue, clickedDateFilterNow ) ) ||
		getSelectedDateRangePreset( dateValue, stableDateFilterNowRef.current );
	const applyDatePreset = useCallback(
		( preset: ReportDateRangePreset ) => {
			const currentDateFilterNow = new Date();
			const nextValue = resolvePreset(
				preset,
				'between',
				currentDateFilterNow
			);
			if ( nextValue ) {
				setClickedDateFilterNow( currentDateFilterNow );
				onDateChange( nextValue, currentDateFilterNow );
			}
		},
		[ onDateChange ]
	);
	const applyDatePresetRef = useRef( applyDatePreset );
	applyDatePresetRef.current = applyDatePreset;
	const selectedPresetRef = useRef( selectedPreset );
	selectedPresetRef.current = selectedPreset;

	const syncPortalNode = useCallback(
		( { force = false }: SyncPortalNodeOptions = {} ) => {
			const ownerDocument = rootRef.current?.ownerDocument ?? document;
			const insertionPoint =
				getDateRangePresetInsertionPoint( ownerDocument );
			const currentSelectedPreset = selectedPresetRef.current;

			if ( ! insertionPoint ) {
				portalNodeRef.current?.remove();
				portalNodeRef.current = null;
				return;
			}

			if (
				portalNodeRef.current?.parentElement ===
					insertionPoint.parent &&
				portalNodeRef.current.nextElementSibling ===
					insertionPoint.before
			) {
				if ( ! force ) {
					return;
				}
				syncNativeDatePresetState(
					insertionPoint.popover,
					currentSelectedPreset
				);
				syncDateRangePresetButtons(
					portalNodeRef.current,
					currentSelectedPreset,
					( preset ) => applyDatePresetRef.current( preset )
				);
				return;
			}

			syncNativeDatePresetState(
				insertionPoint.popover,
				currentSelectedPreset
			);
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
				currentSelectedPreset,
				( preset ) => applyDatePresetRef.current( preset )
			);
		},
		[ rootRef ]
	);

	useEffect( () => {
		syncPortalNode( { force: true } );
	}, [ selectedPreset, syncPortalNode ] );

	useEffect( () => {
		const ownerDocument = rootRef.current?.ownerDocument ?? document;
		const ownerWindow = ownerDocument.defaultView ?? window;
		syncPortalNode();
		let syncAnimationFrame: number | null = null;

		const observedTargets = new Set< HTMLElement >();

		function observeTarget(
			observer: MutationObserver,
			target: HTMLElement | null,
			options: MutationObserverInit
		) {
			if ( ! target || observedTargets.has( target ) ) {
				return;
			}
			observer.observe( target, options );
			observedTargets.add( target );
		}

		function observePopoverFallbackContainer( observer: MutationObserver ) {
			observeTarget(
				observer,
				getDateRangePresetPopoverFallbackContainer( ownerDocument ),
				{
					childList: true,
					subtree: true,
				}
			);
		}

		const observer = new MutationObserver( () => {
			if ( syncAnimationFrame !== null ) {
				return;
			}
			syncAnimationFrame = ownerWindow.requestAnimationFrame( () => {
				syncAnimationFrame = null;
				syncPortalNode();
				observePopoverFallbackContainer( observer );
			} );
		} );

		observePopoverFallbackContainer( observer );
		observeTarget(
			observer,
			ownerDocument.getElementById( 'wpbody-content' ),
			{
				childList: true,
				subtree: true,
			}
		);
		observeTarget( observer, ownerDocument.body, {
			childList: true,
		} );

		if ( ! observedTargets.size ) {
			return () => {
				if ( syncAnimationFrame !== null ) {
					ownerWindow.cancelAnimationFrame( syncAnimationFrame );
				}
				portalNodeRef.current?.remove();
				portalNodeRef.current = null;
			};
		}

		return () => {
			if ( syncAnimationFrame !== null ) {
				ownerWindow.cancelAnimationFrame( syncAnimationFrame );
			}
			observer.disconnect();
			portalNodeRef.current?.remove();
			portalNodeRef.current = null;
		};
	}, [ rootRef, syncPortalNode ] );

	return null;
};
