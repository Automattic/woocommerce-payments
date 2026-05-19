/** @format */

/**
 * External dependencies
 */
import React, {
	useCallback,
	useEffect,
	useLayoutEffect,
	useMemo,
	useRef,
	useState,
} from 'react';
import { Button, Icon } from '@wordpress/components';
import { calendar } from '@wordpress/icons';
import { useDispatch } from '@wordpress/data';
import { __, sprintf } from '@wordpress/i18n';
import { speak } from '@wordpress/a11y';
import { DataViews } from '@wordpress/dataviews';
import type { Filter, View } from '@wordpress/dataviews';

/**
 * Internal dependencies
 */
import DownloadButton from 'wcpay/components/download-button';
import { useReportExport } from 'wcpay/hooks/use-report-export';
import {
	getReportsFeesCSVRequestURL,
	reportsFeesDownloadEndpoint,
} from 'wcpay/data/reports/resolvers';
import { recordEvent } from 'tracks';
import type { DateFilterValue } from 'wcpay/reports/date-filter';
import { useFeesView } from './use-fees-view';
import { useFeesData } from './use-fees-data';
import { getFeesFields } from './fields';
import { CustomDateFilterPopover } from './custom-date-filter-popover';
import {
	encodeCustomDateFilterValue,
	resolveFeesDateFilterValue,
} from './date-filter-values';
import type { ReportsPeriodRange } from '../period-selector';

interface FeesReportProps {
	period: ReportsPeriodRange;
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

/**
 * Returns true when the user's visible-fields configuration has changed.
 * Compared as joined strings — the field list is small and order-significant.
 */
const haveFieldsChanged = (
	prev: ReadonlyArray< string > = [],
	next: ReadonlyArray< string > = []
): boolean => prev.join( '|' ) !== next.join( '|' );

/**
 * Returns true when the DataViews date filter (operator + value) differs.
 * Used to scope the `wcpay_reports_date_range_changed` analytics event.
 */
const hasDateFilterChanged = (
	prev: DateFilterValue | undefined,
	next: DateFilterValue | undefined
): boolean => {
	if ( ! prev && ! next ) {
		return false;
	}
	if ( ! prev || ! next ) {
		return true;
	}
	return (
		prev.operator !== next.operator ||
		JSON.stringify( prev.value ) !== JSON.stringify( next.value )
	);
};

const findDateFilter = ( filters: Filter[] = [] ): Filter | undefined =>
	filters.find( ( filter ) => filter.field === 'date' );

const customDatePopoverId = 'wcpay-fees-date-filter-popover';

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
	return (
		chips.find( ( chip ) =>
			chip.textContent?.trim().toLowerCase().startsWith( 'date' )
		) ?? null
	);
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

	return chip.textContent?.trim().toLowerCase().startsWith( 'date' )
		? chip
		: null;
};

const replaceDateFilter = (
	filters: Filter[] = [],
	nextDateFilter: Filter | undefined
): Filter[] => {
	const withoutDate = filters.filter( ( filter ) => filter.field !== 'date' );
	return nextDateFilter ? [ ...withoutDate, nextDateFilter ] : withoutDate;
};

export const FeesReport = ( {
	period,
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
	const {
		feesQuery,
		rows,
		totalItems,
		totalPages,
		dateElements,
		methodElements,
		typeElements,
		isLoading,
		error,
	} = useFeesData( view, period );
	const { requestReportExport, isExportInProgress } = useReportExport();
	const { createNotice } = useDispatch( 'core/notices' );

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
	const previousLoadingRef = useRef( isLoading );
	useEffect( () => {
		if ( previousLoadingRef.current && ! isLoading && ! hasError ) {
			speak(
				sprintf(
					/* translators: %d: number of fees loaded into the report table. */
					__( '%d fees loaded.', 'woocommerce-payments' ),
					totalItems
				)
			);
		}
		previousLoadingRef.current = isLoading;
	}, [ isLoading, hasError, totalItems ] );

	useLayoutEffect( () => {
		if ( isCustomDatePopoverOpen ) {
			setCustomDateAnchor( findDateFilterAnchor( dataViewsContainer ) );
		}
	}, [ dataViewsContainer, isCustomDatePopoverOpen, view.filters ] );

	useLayoutEffect( () => {
		if ( ! customDateAnchor ) {
			return;
		}

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
			return;
		}

		customDateAnchor.removeAttribute( 'aria-controls' );
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
			requestAnimationFrame( () => anchor?.focus() );
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

	const handleViewChange = useCallback(
		( next: View ) => {
			if ( haveFieldsChanged( view.fields, next.fields ) ) {
				recordEvent( 'wcpay_reports_view_options_opened', {
					report: 'fees',
				} );
			}

			if (
				hasDateFilterChanged(
					getResolvedDateFilter( view ),
					getResolvedDateFilter( next )
				)
			) {
				recordEvent( 'wcpay_reports_date_range_changed', {
					report: 'fees',
				} );
			}

			setView( next );
		},
		[ setView, view ]
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
			handleViewChange( nextView );
		},
		[ handleViewChange, view ]
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
			<FeesReportState
				title={ __( 'No fees yet', 'woocommerce-payments' ) }
				className="wcpay-reports-state--empty wcpay-reports-state--fees-empty"
				description={ __(
					'Fees will appear here once you start receiving payments.',
					'woocommerce-payments'
				) }
			/>
		);
	}

	const handleExport = () => {
		recordEvent( 'wcpay_reports_export_click', {
			report: 'fees',
			exported_row_count: totalItems,
		} );

		const userEmail = wcpaySettings.currentUserEmail;
		const locale = wcSettings.locale.userLocale;
		const exportRequestURL = getReportsFeesCSVRequestURL( {
			dateBefore: feesQuery.date_before,
			dateAfter: feesQuery.date_after,
			dateBetween: feesQuery.date_between,
			paymentMethodType: feesQuery.payment_method_type,
			type: feesQuery.type,
			search: feesQuery.search,
			orderby: feesQuery.orderby || 'date',
			order: feesQuery.order || 'desc',
			userEmail,
			locale,
		} );

		const confirmThreshold = 10000;
		const confirmMessage = sprintf(
			__(
				"You are about to export %d fees. If you'd like to reduce the size of your export, you can use one or more filters. Would you like to continue?",
				'woocommerce-payments'
			),
			totalItems
		);

		if (
			hasFilters ||
			totalItems < confirmThreshold ||
			window.confirm( confirmMessage )
		) {
			requestReportExport( {
				exportRequestURL,
				exportFileAvailabilityEndpoint: reportsFeesDownloadEndpoint,
				userEmail,
			} );

			createNotice(
				'success',
				sprintf(
					__(
						"🎉 We're processing your export. The file will download automatically and be emailed to %s.",
						'woocommerce-payments'
					),
					userEmail
				)
			);
		}
	};

	return (
		<div className="wcpay-reports-fees">
			<div className="wcpay-reports-fees__toolbar">
				<DownloadButton
					isDisabled={
						isLoading || isExportInProgress || rows.length === 0
					}
					isBusy={ isExportInProgress }
					onClick={ handleExport }
				/>
			</div>
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
					onChangeView={ handleViewChange }
					fields={ fields }
					paginationInfo={ { totalItems, totalPages } }
					isLoading={ isLoading }
					defaultLayouts={ { table: {} } }
					search
					searchLabel={ __( 'Search', 'woocommerce-payments' ) }
					getItemId={ ( item ) => item.transaction_id }
				/>
				{ isFilteredEmpty && (
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
