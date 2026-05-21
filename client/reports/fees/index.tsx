/** @format */

/**
 * External dependencies
 */
import React, {
	useCallback,
	useEffect,
	useId,
	useLayoutEffect,
	useMemo,
	useRef,
	useState,
} from 'react';
import { Button, Icon } from '@wordpress/components';
import { calendar } from '@wordpress/icons';
import { __, sprintf } from '@wordpress/i18n';
import { speak } from '@wordpress/a11y';
import { DataViews } from '@wordpress/dataviews/wp';
import type { Filter, View } from '@wordpress/dataviews/wp';

/**
 * Internal dependencies
 */
import type { DateFilterValue } from 'wcpay/reports/date-filter';
import { useFeesView } from './use-fees-view';
import { useFeesData } from './use-fees-data';
import { getFeesFields } from './fields';
import { CustomDateFilterPopover } from './custom-date-filter-popover';
import {
	encodeCustomDateFilterValue,
	resolveFeesDateFilterValue,
} from './date-filter-values';

interface FeesReportProps {
	onReload?: () => void;
}

interface FeesReportStateProps {
	title: string;
	description: React.ReactNode;
	action?: React.ReactNode;
	className?: string;
	descriptionId?: string;
	headingId?: string;
	headingRef?: React.Ref< HTMLHeadingElement >;
	headingTabIndex?: number;
	role?: string;
}

const FeesReportState = ( {
	title,
	description,
	action,
	className,
	descriptionId,
	headingId,
	headingRef,
	headingTabIndex,
	role,
}: FeesReportStateProps ): JSX.Element => (
	<div
		className={ [
			'wcpay-reports-state',
			'wcpay-reports-state--fees-illustrated',
			className,
		]
			.filter( Boolean )
			.join( ' ' ) }
		role={ role }
		aria-labelledby={ headingId }
		aria-describedby={ descriptionId }
	>
		<span className="wcpay-reports-state__icon" aria-hidden="true">
			<Icon icon={ calendar } size={ 48 } />
		</span>
		<div className="wcpay-reports-state__copy">
			<h2
				id={ headingId }
				ref={ headingRef }
				tabIndex={ headingTabIndex }
			>
				{ title }
			</h2>
			<p id={ descriptionId }>{ description }</p>
		</div>
		{ action }
	</div>
);

const findDateFilter = ( filters: Filter[] = [] ): Filter | undefined =>
	filters.find( ( filter ) => filter.field === 'date' );

const customDatePopoverId = 'wcpay-fees-date-filter-popover';

// DataViews does not expose a structural per-field hook on its summary chips
// (no `data-field-id`, no field-id-keyed `aria-label` — see
// @wordpress/dataviews dataviews-filters/filter-summary). The chip's leading
// text content is the field's `name`, which is our own `label`. Comparing
// against `__('Date', ...)` is locale-robust because both sides resolve to
// the same translated string. We normalize whitespace and case, and require
// the label to be followed by a non-word character (or end-of-string) so we
// don't accidentally pick up a chip whose name happens to start with the
// literal label (e.g. "Date range"). We use `\W` instead of `\b` because
// `\b` is ASCII-only in JS regex — in CJK locales the label (e.g. `日付`)
// contains no `\w` characters, so `\b` would never match. `\W` works in both
// cases: it accepts whitespace/punctuation after the label and still rejects
// adjacent word characters.
const dateFilterLabelPattern = ( (): RegExp => {
	const raw = __( 'Date', 'woocommerce-payments' )
		.trim()
		.toLowerCase()
		// Escape regex metacharacters that might appear in translated labels.
		.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );
	return new RegExp( `^${ raw }(?:\\W|$)` );
} )();

const isDateFilterAnchor = ( element: HTMLElement ): boolean => {
	const text = element.textContent?.trim().toLowerCase() ?? '';
	return dateFilterLabelPattern.test( text );
};

const getResolvedDateFilter = ( view: View ): DateFilterValue | undefined =>
	resolveFeesDateFilterValue( findDateFilter( view.filters )?.value );

const findDateFilterAnchor = (
	container: HTMLElement | null
): HTMLElement | null => {
	if ( ! container ) {
		return null;
	}

	const chips = Array.from(
		container.querySelectorAll< HTMLElement >(
			'.dataviews-filters__summary-chip'
		)
	);
	return chips.find( ( chip ) => isDateFilterAnchor( chip ) ) ?? null;
};

const findDateFilterAnchorFromEvent = (
	target: EventTarget | null,
	container: HTMLElement | null
): HTMLElement | null => {
	if ( ! container || ! ( target instanceof HTMLElement ) ) {
		return null;
	}

	const chip = target.closest< HTMLElement >(
		'.dataviews-filters__summary-chip'
	);
	if ( ! chip || ! container.contains( chip ) ) {
		return null;
	}

	return isDateFilterAnchor( chip ) ? chip : null;
};

const replaceDateFilter = (
	filters: Filter[] = [],
	nextDateFilter: Filter | undefined
): Filter[] => {
	const withoutDate = filters.filter( ( filter ) => filter.field !== 'date' );
	return nextDateFilter ? [ ...withoutDate, nextDateFilter ] : withoutDate;
};

export const FeesReport = ( {
	onReload = () => undefined,
}: FeesReportProps ): JSX.Element => {
	const [ view, setView ] = useFeesView();
	const [ dataViewsContainer, setDataViewsContainer ] =
		useState< HTMLDivElement | null >( null );
	const [ customDateAnchor, setCustomDateAnchor ] =
		useState< HTMLElement | null >( null );
	const [ isCustomDatePopoverOpen, setIsCustomDatePopoverOpen ] =
		useState( false );
	const [ customDateInitialValue, setCustomDateInitialValue ] = useState<
		DateFilterValue | undefined
	>( undefined );
	const isCustomDatePopoverOpenRef = useRef( isCustomDatePopoverOpen );
	const ignoreNextDateFilterClickRef = useRef( false );
	const initialEmptyHeadingId = useId();
	const initialEmptyDescriptionId = useId();
	const filteredEmptyHeadingId = useId();
	const filteredEmptyDescriptionId = useId();
	const {
		rows,
		totalItems,
		totalPages,
		dateElements,
		methodElements,
		typeElements,
		isLoading,
		error,
	} = useFeesData( view );

	const fields = useMemo(
		() =>
			getFeesFields( {
				dateElements,
				methodElements,
				typeElements,
			} ),
		[ dateElements, methodElements, typeElements ]
	);
	const hasError = Object.keys( error ).length > 0;
	const hasFilters = ( view.filters ?? [] ).length > 0 || !! view.search;
	const hasNoRows = ! isLoading && ! hasError && rows.length === 0;
	const isInitialEmpty = hasNoRows && ! hasFilters;
	const isFilteredEmpty = hasNoRows && hasFilters;

	useEffect( () => {
		isCustomDatePopoverOpenRef.current = isCustomDatePopoverOpen;
	}, [ isCustomDatePopoverOpen ] );

	// Move focus to the error region and announce when an error surfaces, so
	// keyboard/AT users notice the table disappearing. `role="alert"` on the
	// container takes care of automatic announcement; the focus move handles
	// keyboard context.
	const errorHeadingRef = useRef< HTMLHeadingElement >( null );
	const previousErrorRef = useRef( hasError );
	useEffect( () => {
		if ( hasError && ! previousErrorRef.current ) {
			errorHeadingRef.current?.focus();
		}
		previousErrorRef.current = hasError;
	}, [ hasError ] );

	// Announce "Fees report loaded" to AT users on every loading→ready edge.
	// Debounced (500ms) and de-duplicated so rapid filter changes — which can
	// cause loading→ready→loading→ready bursts — collapse into a single
	// announcement instead of spamming AT users.
	const previousLoadingRef = useRef( isLoading );
	const speakTimerRef = useRef< ReturnType< typeof setTimeout > | null >(
		null
	);
	const lastSpokenRef = useRef< string | null >( null );
	useEffect( () => {
		if ( previousLoadingRef.current && ! isLoading && ! hasError ) {
			const message = sprintf(
				/* translators: %d: number of fees loaded into the report table. */
				__( '%d fees loaded.', 'woocommerce-payments' ),
				totalItems
			);
			if ( speakTimerRef.current ) {
				clearTimeout( speakTimerRef.current );
			}
			speakTimerRef.current = setTimeout( () => {
				speakTimerRef.current = null;
				if ( lastSpokenRef.current === message ) {
					return;
				}
				lastSpokenRef.current = message;
				speak( message );
			}, 500 );
		}
		previousLoadingRef.current = isLoading;
	}, [ isLoading, hasError, totalItems ] );

	useEffect(
		() => () => {
			if ( speakTimerRef.current ) {
				clearTimeout( speakTimerRef.current );
				speakTimerRef.current = null;
			}
		},
		[]
	);

	useLayoutEffect( () => {
		setCustomDateAnchor( findDateFilterAnchor( dataViewsContainer ) );
	}, [ dataViewsContainer, view.filters ] );

	useLayoutEffect( () => {
		if ( ! customDateAnchor ) {
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
			customDateAnchor.setAttribute( 'aria-haspopup', 'dialog' );
			customDateAnchor.setAttribute(
				'aria-expanded',
				String( isCustomDatePopoverOpen )
			);
			if ( isCustomDatePopoverOpen ) {
				customDateAnchor.setAttribute(
					'aria-controls',
					customDatePopoverId
				);
			} else {
				customDateAnchor.removeAttribute( 'aria-controls' );
			}
		};

		applyAriaAttributes();

		const observer = new MutationObserver( ( mutations ) => {
			// Re-apply only when an attribute we care about drifted from
			// what we want, so we don't fight DataViews on unrelated edits
			// (e.g. class changes for `has-values`/`has-reset`).
			const desiredExpanded = String( isCustomDatePopoverOpen );
			const desiredControls = isCustomDatePopoverOpen
				? customDatePopoverId
				: null;
			const drifted = mutations.some( ( mutation ) => {
				if ( mutation.type !== 'attributes' ) return false;
				switch ( mutation.attributeName ) {
					case 'aria-haspopup':
						return (
							customDateAnchor.getAttribute( 'aria-haspopup' ) !==
							'dialog'
						);
					case 'aria-expanded':
						return (
							customDateAnchor.getAttribute( 'aria-expanded' ) !==
							desiredExpanded
						);
					case 'aria-controls':
						return (
							customDateAnchor.getAttribute( 'aria-controls' ) !==
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
		observer.observe( customDateAnchor, {
			attributes: true,
			attributeFilter: [
				'aria-haspopup',
				'aria-expanded',
				'aria-controls',
			],
		} );

		return () => {
			observer.disconnect();
		};
	}, [ customDateAnchor, isCustomDatePopoverOpen ] );

	const openCustomDatePopover = useCallback(
		( anchor: HTMLElement | null ) => {
			setCustomDateAnchor( anchor );
			setCustomDateInitialValue( getResolvedDateFilter( view ) );
			isCustomDatePopoverOpenRef.current = true;
			setIsCustomDatePopoverOpen( true );
		},
		[ view ]
	);

	const closeCustomDatePopoverFromTrigger = useCallback(
		( anchor: HTMLElement | null ) => {
			isCustomDatePopoverOpenRef.current = false;
			setIsCustomDatePopoverOpen( false );
			setCustomDateInitialValue( undefined );
			// Guard against the anchor having been removed from the DOM by
			// DataViews between toggle and the next frame (matches the same
			// pattern used in CustomDateFilterPopover.returnFocus).
			requestAnimationFrame( () => {
				if ( anchor && document.contains( anchor ) ) {
					anchor.focus();
				}
			} );
		},
		[]
	);

	const toggleCustomDatePopover = useCallback(
		( anchor: HTMLElement ) => {
			if ( isCustomDatePopoverOpenRef.current ) {
				closeCustomDatePopoverFromTrigger( anchor );
				return;
			}

			openCustomDatePopover( anchor );
		},
		[ closeCustomDatePopoverFromTrigger, openCustomDatePopover ]
	);

	const handleDataViewsPointerDownCapture = useCallback(
		( event: React.PointerEvent< HTMLDivElement > ) => {
			const dateFilterAnchor = findDateFilterAnchorFromEvent(
				event.target,
				dataViewsContainer
			);
			if (
				! dateFilterAnchor ||
				( event.button !== 0 && event.button !== undefined )
			) {
				return;
			}

			event.preventDefault();
			event.stopPropagation();
			ignoreNextDateFilterClickRef.current = true;
			toggleCustomDatePopover( dateFilterAnchor );
		},
		[ dataViewsContainer, toggleCustomDatePopover ]
	);

	const handleDataViewsClickCapture = useCallback(
		( event: React.MouseEvent< HTMLDivElement > ) => {
			const dateFilterAnchor = findDateFilterAnchorFromEvent(
				event.target,
				dataViewsContainer
			);
			if ( ! dateFilterAnchor ) {
				return;
			}

			event.preventDefault();
			event.stopPropagation();
			if ( ignoreNextDateFilterClickRef.current ) {
				ignoreNextDateFilterClickRef.current = false;
				return;
			}

			toggleCustomDatePopover( dateFilterAnchor );
		},
		[ dataViewsContainer, toggleCustomDatePopover ]
	);

	const handleDataViewsKeyDownCapture = useCallback(
		( event: React.KeyboardEvent< HTMLDivElement > ) => {
			if ( event.key !== 'Enter' && event.key !== ' ' ) {
				return;
			}

			const dateFilterAnchor = findDateFilterAnchorFromEvent(
				event.target,
				dataViewsContainer
			);
			if ( ! dateFilterAnchor ) {
				return;
			}

			event.preventDefault();
			event.stopPropagation();
			toggleCustomDatePopover( dateFilterAnchor );
		},
		[ dataViewsContainer, toggleCustomDatePopover ]
	);

	const closeCustomDatePopover = useCallback( () => {
		isCustomDatePopoverOpenRef.current = false;
		setIsCustomDatePopoverOpen( false );
		setCustomDateInitialValue( undefined );
		const dateFilter = findDateFilter( view.filters );
		if ( dateFilter && dateFilter.value === undefined ) {
			setView( {
				...view,
				filters: replaceDateFilter( view.filters, undefined ),
			} );
		}
	}, [ setView, view ] );

	const changeCustomDateFilter = useCallback(
		( nextDateFilter: DateFilterValue ) => {
			const nextView = {
				...view,
				page: 1,
				filters: replaceDateFilter( view.filters, {
					field: 'date',
					operator: 'is',
					value: encodeCustomDateFilterValue( nextDateFilter ),
				} ),
			};
			setView( nextView );
		},
		[ setView, view ]
	);

	if ( hasError ) {
		return (
			<FeesReportState
				title={ __(
					'Fees report unavailable',
					'woocommerce-payments'
				) }
				description={
					<>
						<span>
							{ __(
								"We couldn't load your fees data.",
								'woocommerce-payments'
							) }
						</span>{ ' ' }
						<span>
							{ __(
								'Try again in a few minutes.',
								'woocommerce-payments'
							) }
						</span>
					</>
				}
				action={
					<Button variant="secondary" onClick={ onReload }>
						{ __( 'Reload report', 'woocommerce-payments' ) }
					</Button>
				}
				className="wcpay-reports-state--error wcpay-reports-state--fees-error"
				descriptionId="wcpay-reports-fees-error-description"
				headingId="wcpay-reports-fees-error"
				headingRef={ errorHeadingRef }
				headingTabIndex={ -1 }
				role="alert"
			/>
		);
	}

	if ( isInitialEmpty ) {
		return (
			/* role="status" is implicitly aria-live="polite". Safe here because the
			   empty states do not shift focus — keep this in sync if you add focus
			   management later. */
			<FeesReportState
				title={ __( 'No fees yet', 'woocommerce-payments' ) }
				className="wcpay-reports-state--empty wcpay-reports-state--fees-empty"
				description={ __(
					'Fees will appear here once you start receiving payments.',
					'woocommerce-payments'
				) }
				descriptionId={ initialEmptyDescriptionId }
				headingId={ initialEmptyHeadingId }
				role="status"
			/>
		);
	}

	return (
		<div className="wcpay-reports-fees">
			<div
				className={
					isFilteredEmpty
						? 'wcpay-reports-fees__main wcpay-reports-fees__main--filtered-empty'
						: 'wcpay-reports-fees__main'
				}
				ref={ setDataViewsContainer }
				onPointerDownCapture={ handleDataViewsPointerDownCapture }
				onClickCapture={ handleDataViewsClickCapture }
				onKeyDownCapture={ handleDataViewsKeyDownCapture }
			>
				<DataViews
					data={ rows }
					view={ view }
					onChangeView={ setView }
					fields={ fields }
					paginationInfo={ { totalItems, totalPages } }
					isLoading={ isLoading }
					defaultLayouts={ { table: {} } }
					search
					searchLabel={ __( 'Search fees', 'woocommerce-payments' ) }
					getItemId={ ( item ) => item.transaction_id }
				/>
				{ isFilteredEmpty && (
					/* role="status" is implicitly aria-live="polite". Safe here because the
					   empty states do not shift focus — keep this in sync if you add focus
					   management later. */
					<FeesReportState
						title={ __(
							'No fees to display',
							'woocommerce-payments'
						) }
						className="wcpay-reports-state--empty wcpay-reports-state--fees-empty"
						description={ __(
							'Fees will appear here.',
							'woocommerce-payments'
						) }
						descriptionId={ filteredEmptyDescriptionId }
						headingId={ filteredEmptyHeadingId }
						role="status"
					/>
				) }
				{ isCustomDatePopoverOpen && (
					<CustomDateFilterPopover
						anchor={ customDateAnchor }
						id={ customDatePopoverId }
						initialValue={ customDateInitialValue }
						onChange={ changeCustomDateFilter }
						onClose={ closeCustomDatePopover }
					/>
				) }
			</div>
		</div>
	);
};

export default FeesReport;
