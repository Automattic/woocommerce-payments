/** @format */

/**
 * External dependencies
 */
import React, { useEffect, useId, useMemo, useRef, useState } from 'react';
import { Button, Icon } from '@wordpress/components';
import { calendar } from '@wordpress/icons';
import { __, sprintf } from '@wordpress/i18n';
import { speak } from '@wordpress/a11y';
import { DataViews } from '@wordpress/dataviews/wp';

/**
 * Internal dependencies
 */
import { useFeesView } from './use-fees-view';
import { useFeesData } from './use-fees-data';
import { getFeesFields } from './fields';
import { CustomDateFilterPopover } from './custom-date-filter-popover';
import { useDateFilterChipInterceptor } from './use-date-filter-chip-interceptor';
import './style.scss';

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

const customDatePopoverId = 'wcpay-fees-date-filter-popover';

export const FeesReport = ( {
	onReload = () => undefined,
}: FeesReportProps ): JSX.Element => {
	const [ view, setView ] = useFeesView();
	const [ dataViewsContainer, setDataViewsContainer ] =
		useState< HTMLDivElement | null >( null );
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

	const {
		anchor: customDateAnchor,
		isPopoverOpen: isCustomDatePopoverOpen,
		initialValue: customDateInitialValue,
		onPopoverChange: changeCustomDateFilter,
		onPopoverClose: closeCustomDatePopover,
		captureHandlers: dateFilterCaptureHandlers,
	} = useDateFilterChipInterceptor( {
		container: dataViewsContainer,
		view,
		setView,
		popoverId: customDatePopoverId,
	} );

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
				tabIndex={ -1 }
				{ ...dateFilterCaptureHandlers }
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
						fallbackFocus={ dataViewsContainer }
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
