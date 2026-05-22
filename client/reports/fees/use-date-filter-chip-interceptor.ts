/** @format */

/**
 * External dependencies
 */
import React, { useCallback, useLayoutEffect, useRef, useState } from 'react';
import type { Filter, View } from '@wordpress/dataviews/wp';

/**
 * Internal dependencies
 */
import type { DateFilterValue } from 'wcpay/reports/date-filter';
import {
	encodeCustomDateFilterValue,
	resolveFeesDateFilterValue,
} from './date-filter-values';

// DataViews does not expose a field id or data attribute on summary chips.
// It does, however, sort primary filters before secondary filters. The Fees
// Date filter is the only primary filter, so the first visible summary chip
// is the Date chip without relying on translated text content.
//
// TODO: replace once @wordpress/dataviews exposes a supported API for
// custom filter triggers (no upstream issue filed yet — see PR review for
// PR #11708).
const filtersContainerSelector = '.dataviews-filters__container';
const dateFilterChipSelector = '.dataviews-filters__summary-chip';

const findDateFilterAnchor = (
	container: HTMLElement | null
): HTMLElement | null => {
	if ( ! container ) {
		return null;
	}
	return (
		container
			.querySelector< HTMLElement >( filtersContainerSelector )
			?.querySelector< HTMLElement >( dateFilterChipSelector ) ?? null
	);
};

const findDateFilterAnchorFromEvent = (
	target: EventTarget | null,
	container: HTMLElement | null
): HTMLElement | null => {
	if ( ! container || ! ( target instanceof HTMLElement ) ) {
		return null;
	}

	const chip = target.closest< HTMLElement >( dateFilterChipSelector );
	if ( ! chip || ! container.contains( chip ) ) {
		return null;
	}

	return chip === findDateFilterAnchor( container ) ? chip : null;
};

const findDateFilter = ( filters: Filter[] = [] ): Filter | undefined =>
	filters.find( ( filter ) => filter.field === 'date' );

const replaceDateFilter = (
	filters: Filter[] = [],
	nextDateFilter: Filter | undefined
): Filter[] => {
	const withoutDate = filters.filter( ( filter ) => filter.field !== 'date' );
	return nextDateFilter ? [ ...withoutDate, nextDateFilter ] : withoutDate;
};

const getResolvedDateFilter = ( view: View ): DateFilterValue | undefined =>
	resolveFeesDateFilterValue( findDateFilter( view.filters )?.value );

export interface UseDateFilterChipInterceptorOptions {
	container: HTMLElement | null;
	view: View;
	setView: ( next: View ) => void;
	popoverId: string;
}

export interface DateFilterChipInterceptor {
	anchor: HTMLElement | null;
	isPopoverOpen: boolean;
	initialValue: DateFilterValue | undefined;
	onPopoverChange: ( next: DateFilterValue ) => void;
	onPopoverClose: () => void;
	captureHandlers: {
		onPointerDownCapture: React.PointerEventHandler< HTMLDivElement >;
		onClickCapture: React.MouseEventHandler< HTMLDivElement >;
		onKeyDownCapture: React.KeyboardEventHandler< HTMLDivElement >;
	};
}

/**
 * Routes activations of the DataViews "Date" summary chip into a custom
 * popover. DataViews doesn't expose a supported API for swapping out the
 * built-in filter popover, so this hook reaches into the chip markup
 * directly. The brittleness is intentionally isolated here so the rest of
 * the report doesn't depend on DataViews internals.
 */
export const useDateFilterChipInterceptor = ( {
	container,
	view,
	setView,
	popoverId,
}: UseDateFilterChipInterceptorOptions ): DateFilterChipInterceptor => {
	const [ anchor, setAnchor ] = useState< HTMLElement | null >( null );
	const [ isPopoverOpen, setIsPopoverOpen ] = useState( false );
	const [ initialValue, setInitialValue ] = useState<
		DateFilterValue | undefined
	>( undefined );
	const isPopoverOpenRef = useRef( isPopoverOpen );
	const ignoreNextClickRef = useRef( false );

	useLayoutEffect( () => {
		isPopoverOpenRef.current = isPopoverOpen;
	}, [ isPopoverOpen ] );

	useLayoutEffect( () => {
		setAnchor( findDateFilterAnchor( container ) );
	}, [ container, view.filters ] );

	useLayoutEffect( () => {
		if ( ! anchor ) {
			return;
		}

		// DataViews owns the summary-chip markup and does not expose trigger
		// props for a custom filter popover. Keep the intercepted Date chip's
		// dialog semantics synchronized with our custom popover state here.
		//
		// React's reconciliation can overwrite `aria-expanded` (and strip
		// `aria-haspopup`/`aria-controls`) whenever DataViews re-renders the
		// chip — its own Dropdown writes `aria-expanded` and we don't control
		// that prop. Re-apply on filter changes (covered by deps) AND watch
		// the chip for attribute mutations so we restore our values even when
		// the re-render isn't driven by `view.filters`.
		const applyAriaAttributes = (): void => {
			anchor.setAttribute( 'aria-haspopup', 'dialog' );
			anchor.setAttribute( 'aria-expanded', String( isPopoverOpen ) );
			if ( isPopoverOpen ) {
				anchor.setAttribute( 'aria-controls', popoverId );
			} else {
				anchor.removeAttribute( 'aria-controls' );
			}
		};

		applyAriaAttributes();

		const observer = new MutationObserver( ( mutations ) => {
			// Re-apply only when an attribute we care about drifted from
			// what we want, so we don't fight DataViews on unrelated edits
			// (e.g. class changes for `has-values`/`has-reset`).
			const desiredExpanded = String( isPopoverOpen );
			const desiredControls = isPopoverOpen ? popoverId : null;
			const drifted = mutations.some( ( mutation ) => {
				if ( mutation.type !== 'attributes' ) return false;
				switch ( mutation.attributeName ) {
					case 'aria-haspopup':
						return (
							anchor.getAttribute( 'aria-haspopup' ) !== 'dialog'
						);
					case 'aria-expanded':
						return (
							anchor.getAttribute( 'aria-expanded' ) !==
							desiredExpanded
						);
					case 'aria-controls':
						return (
							anchor.getAttribute( 'aria-controls' ) !==
							desiredControls
						);
					default:
						return false;
				}
			} );
			if ( drifted ) {
				applyAriaAttributes();
			}
		} );
		observer.observe( anchor, {
			attributes: true,
			attributeFilter: [
				'aria-haspopup',
				'aria-expanded',
				'aria-controls',
			],
		} );

		return () => observer.disconnect();
	}, [ anchor, isPopoverOpen, popoverId ] );

	const openPopover = useCallback(
		( nextAnchor: HTMLElement | null ) => {
			setAnchor( nextAnchor );
			setInitialValue( getResolvedDateFilter( view ) );
			isPopoverOpenRef.current = true;
			setIsPopoverOpen( true );
		},
		[ view ]
	);

	const closePopoverFromTrigger = useCallback(
		( nextAnchor: HTMLElement | null ) => {
			isPopoverOpenRef.current = false;
			setIsPopoverOpen( false );
			setInitialValue( undefined );
			// Guard against the anchor having been removed from the DOM by
			// DataViews between toggle and the next frame (matches the same
			// pattern used in CustomDateFilterPopover.returnFocus).
			requestAnimationFrame( () => {
				if ( nextAnchor && document.contains( nextAnchor ) ) {
					nextAnchor.focus();
				}
			} );
		},
		[]
	);

	const togglePopover = useCallback(
		( nextAnchor: HTMLElement ) => {
			if ( isPopoverOpenRef.current ) {
				closePopoverFromTrigger( nextAnchor );
				return;
			}
			openPopover( nextAnchor );
		},
		[ closePopoverFromTrigger, openPopover ]
	);

	const onPointerDownCapture = useCallback(
		( event: React.PointerEvent< HTMLDivElement > ) => {
			const dateFilterAnchor = findDateFilterAnchorFromEvent(
				event.target,
				container
			);
			if (
				! dateFilterAnchor ||
				( event.button !== 0 && event.button !== undefined )
			) {
				return;
			}

			event.preventDefault();
			event.stopPropagation();
			ignoreNextClickRef.current = true;
			togglePopover( dateFilterAnchor );
		},
		[ container, togglePopover ]
	);

	const onClickCapture = useCallback(
		( event: React.MouseEvent< HTMLDivElement > ) => {
			const dateFilterAnchor = findDateFilterAnchorFromEvent(
				event.target,
				container
			);
			if ( ! dateFilterAnchor ) {
				return;
			}

			event.preventDefault();
			event.stopPropagation();
			if ( ignoreNextClickRef.current ) {
				ignoreNextClickRef.current = false;
				return;
			}

			togglePopover( dateFilterAnchor );
		},
		[ container, togglePopover ]
	);

	const onKeyDownCapture = useCallback(
		( event: React.KeyboardEvent< HTMLDivElement > ) => {
			if ( event.key !== 'Enter' && event.key !== ' ' ) {
				return;
			}

			const dateFilterAnchor = findDateFilterAnchorFromEvent(
				event.target,
				container
			);
			if ( ! dateFilterAnchor ) {
				return;
			}

			event.preventDefault();
			event.stopPropagation();
			togglePopover( dateFilterAnchor );
		},
		[ container, togglePopover ]
	);

	const onPopoverClose = useCallback( () => {
		isPopoverOpenRef.current = false;
		setIsPopoverOpen( false );
		setInitialValue( undefined );
		const dateFilter = findDateFilter( view.filters );
		if ( dateFilter && dateFilter.value === undefined ) {
			setView( {
				...view,
				filters: replaceDateFilter( view.filters, undefined ),
			} );
		}
	}, [ setView, view ] );

	const onPopoverChange = useCallback(
		( nextDateFilter: DateFilterValue ) => {
			setView( {
				...view,
				page: 1,
				filters: replaceDateFilter( view.filters, {
					field: 'date',
					operator: 'is',
					value: encodeCustomDateFilterValue( nextDateFilter ),
				} ),
			} );
		},
		[ setView, view ]
	);

	return {
		anchor,
		isPopoverOpen,
		initialValue,
		onPopoverChange,
		onPopoverClose,
		captureHandlers: {
			onPointerDownCapture,
			onClickCapture,
			onKeyDownCapture,
		},
	};
};
