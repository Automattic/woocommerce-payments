/** @format */

import React, { useRef } from 'react';
import { act, render, waitFor } from '@testing-library/react';

/**
 * Internal dependencies
 */
import { DataViewsDateRangePresetPortal } from '../dataviews-date-range-preset-portal';
import type { DateFilterValue } from '../types';

jest.mock( '@wordpress/i18n', () => ( {
	// eslint-disable-next-line @typescript-eslint/naming-convention
	__: ( text: string, domain?: string ) =>
		`${ domain ?? 'default' }:${ text }`,
} ) );

const dateFilterNow = new Date( '2026-05-15T12:00:00.000Z' );
const previousYearValue: DateFilterValue = {
	operator: 'between',
	value: [ '2025-01-01', '2025-12-31' ],
};

const appendNativePresetButton = (
	parent: HTMLElement,
	label: string
): HTMLButtonElement => {
	const button = document.createElement( 'button' );
	button.type = 'button';
	button.className = 'dataviews-controls__date-preset';
	button.setAttribute( 'aria-pressed', 'true' );
	button.textContent = label;
	parent.appendChild( button );
	return button;
};

const appendDatePresetPopover = ( parent = document.body ) => {
	const popover = document.createElement( 'div' );
	popover.className = 'dataviews-filters__summary-popover';

	const presets = document.createElement( 'div' );
	popover.appendChild( presets );

	appendNativePresetButton( presets, 'default:Month to date' );
	const customButton = appendNativePresetButton( presets, 'default:Custom' );

	const rangeInputs = document.createElement( 'div' );
	rangeInputs.className = 'dataviews-controls__date-range-inputs';
	popover.appendChild( rangeInputs );

	parent.appendChild( popover );

	return { customButton };
};

const appendSingleDatePresetPopover = ( parent = document.body ) => {
	const popover = document.createElement( 'div' );
	popover.className = 'dataviews-filters__summary-popover';

	const presets = document.createElement( 'div' );
	popover.appendChild( presets );

	const todayButton = appendNativePresetButton( presets, 'default:Today' );
	const customButton = appendNativePresetButton( presets, 'default:Custom' );

	parent.appendChild( popover );

	return { customButton, todayButton };
};

const PortalHarness = ( {
	dateValue = previousYearValue,
	onDateChange = jest.fn(),
}: {
	dateValue?: DateFilterValue;
	onDateChange?: ( value: DateFilterValue, referenceDate?: Date ) => void;
} ) => {
	const rootRef = useRef< HTMLDivElement | null >( null );

	return (
		<>
			<div ref={ rootRef } />
			<DataViewsDateRangePresetPortal
				rootRef={ rootRef }
				dateValue={ dateValue }
				dateFilterNow={ dateFilterNow }
				onDateChange={ onDateChange }
			/>
		</>
	);
};

afterEach( () => {
	document.body.innerHTML = '';
	jest.useRealTimers();
	jest.restoreAllMocks();
} );

describe( 'DataViewsDateRangePresetPortal', () => {
	it( 'syncs the native Custom button when DataViews uses the default i18n domain', async () => {
		const { customButton } = appendDatePresetPopover();

		render( <PortalHarness /> );

		await waitFor( () =>
			expect( customButton ).toHaveAttribute( 'aria-pressed', 'false' )
		);
		expect( customButton ).not.toHaveAttribute( 'aria-disabled' );
		expect( customButton ).not.toHaveClass(
			'wcpay-reports-date-range-preset--custom-disabled'
		);
	} );

	it( 'sets injected preset button labels before inserting them into the DOM', async () => {
		appendDatePresetPopover();
		const originalAppendChild = HTMLDivElement.prototype.appendChild;
		const insertedPresetLabels: Array< string | null > = [];
		jest.spyOn(
			HTMLDivElement.prototype,
			'appendChild'
		).mockImplementation( function (
			this: HTMLDivElement,
			child: Node
		): Node {
			if (
				child instanceof HTMLButtonElement &&
				child.dataset.wcpayDateRangePreset
			) {
				insertedPresetLabels.push( child.textContent );
			}
			return originalAppendChild.call( this, child );
		} );

		render( <PortalHarness /> );

		await waitFor( () =>
			expect( insertedPresetLabels ).toEqual( [
				'woocommerce-payments:Previous month',
				'woocommerce-payments:Previous year',
			] )
		);
	} );

	it( 'resolves clicked presets against the current clock', async () => {
		jest.useFakeTimers();
		jest.setSystemTime( new Date( '2026-06-15T12:00:00.000Z' ) );
		const onDateChange = jest.fn();
		appendDatePresetPopover();

		render(
			<PortalHarness
				dateValue={ {
					operator: 'between',
					value: [ '2026-04-01', '2026-04-30' ],
				} }
				onDateChange={ onDateChange }
			/>
		);

		const previousMonthButton = await waitFor( () => {
			const button = document.querySelector< HTMLButtonElement >(
				'[data-wcpay-date-range-preset="last_month"]'
			);
			expect( button ).not.toBeNull();
			return button as HTMLButtonElement;
		} );

		act( () => {
			previousMonthButton.click();
		} );

		expect( onDateChange ).toHaveBeenCalledWith(
			{
				operator: 'between',
				value: [ '2026-05-01', '2026-05-31' ],
			},
			new Date( '2026-06-15T12:00:00.000Z' )
		);
	} );

	it( 'does not inject range presets into single-date popovers', async () => {
		const { customButton, todayButton } = appendSingleDatePresetPopover();

		render( <PortalHarness /> );

		await waitFor( () => {
			expect(
				document.querySelector(
					'[data-wcpay-date-range-preset="last_month"]'
				)
			).toBeNull();
		} );
		expect(
			document.querySelector(
				'[data-wcpay-date-range-preset="last_year"]'
			)
		).toBeNull();
		expect( todayButton ).toHaveAttribute( 'aria-pressed', 'true' );
		expect( customButton ).toHaveAttribute( 'aria-pressed', 'true' );
	} );

	it( 'observes the WordPress admin content container when present', () => {
		const originalMutationObserver = globalThis.MutationObserver;
		const observe = jest.fn();
		const disconnect = jest.fn();
		const MockMutationObserver = jest.fn().mockImplementation( () => ( {
			observe,
			disconnect,
			takeRecords: jest.fn(),
		} ) );
		globalThis.MutationObserver =
			MockMutationObserver as unknown as typeof MutationObserver;

		const wpBodyContent = document.createElement( 'div' );
		wpBodyContent.id = 'wpbody-content';
		document.body.appendChild( wpBodyContent );
		appendDatePresetPopover();

		try {
			const { unmount } = render( <PortalHarness /> );

			expect( observe ).toHaveBeenCalledWith( wpBodyContent, {
				childList: true,
				subtree: true,
			} );
			expect( observe ).toHaveBeenCalledWith( document.body, {
				childList: true,
			} );

			unmount();
			expect( disconnect ).toHaveBeenCalled();
		} finally {
			globalThis.MutationObserver = originalMutationObserver;
		}
	} );

	it( 'prefers the WordPress popover fallback container when present', () => {
		const originalMutationObserver = globalThis.MutationObserver;
		const observe = jest.fn();
		const disconnect = jest.fn();
		const MockMutationObserver = jest.fn().mockImplementation( () => ( {
			observe,
			disconnect,
			takeRecords: jest.fn(),
		} ) );
		globalThis.MutationObserver =
			MockMutationObserver as unknown as typeof MutationObserver;

		const wpBodyContent = document.createElement( 'div' );
		wpBodyContent.id = 'wpbody-content';
		document.body.appendChild( wpBodyContent );

		const popoverFallbackContainer = document.createElement( 'div' );
		popoverFallbackContainer.className =
			'components-popover__fallback-container';
		document.body.appendChild( popoverFallbackContainer );
		appendDatePresetPopover();

		try {
			const { unmount } = render( <PortalHarness /> );

			expect( observe ).toHaveBeenCalledWith( popoverFallbackContainer, {
				childList: true,
				subtree: true,
			} );
			expect( observe ).toHaveBeenCalledWith( wpBodyContent, {
				childList: true,
				subtree: true,
			} );
			expect( observe ).toHaveBeenCalledWith( document.body, {
				childList: true,
			} );

			unmount();
			expect( disconnect ).toHaveBeenCalled();
		} finally {
			globalThis.MutationObserver = originalMutationObserver;
		}
	} );

	it( 'updates selected preset state without rebuilding mutation observers', async () => {
		const originalMutationObserver = globalThis.MutationObserver;
		const observe = jest.fn();
		const disconnect = jest.fn();
		const MockMutationObserver = jest.fn().mockImplementation( () => ( {
			observe,
			disconnect,
			takeRecords: jest.fn(),
		} ) );
		globalThis.MutationObserver =
			MockMutationObserver as unknown as typeof MutationObserver;

		appendDatePresetPopover();

		try {
			const { rerender, unmount } = render( <PortalHarness /> );

			expect(
				document.querySelector(
					'[data-wcpay-date-range-preset="last_year"]'
				)
			).toHaveAttribute( 'aria-pressed', 'true' );
			expect(
				document.querySelector(
					'[data-wcpay-date-range-preset="last_month"]'
				)
			).toHaveAttribute( 'aria-pressed', 'false' );
			const observerCountAfterInitialRender =
				MockMutationObserver.mock.calls.length;
			const disconnectCountAfterInitialRender =
				disconnect.mock.calls.length;

			act( () => {
				rerender(
					<PortalHarness
						dateValue={ {
							operator: 'between',
							value: [ '2026-04-01', '2026-04-30' ],
						} }
					/>
				);
			} );

			expect(
				document.querySelector(
					'[data-wcpay-date-range-preset="last_month"]'
				)
			).toHaveAttribute( 'aria-pressed', 'true' );
			expect(
				document.querySelector(
					'[data-wcpay-date-range-preset="last_year"]'
				)
			).toHaveAttribute( 'aria-pressed', 'false' );

			expect( MockMutationObserver ).toHaveBeenCalledTimes(
				observerCountAfterInitialRender
			);
			expect( disconnect ).toHaveBeenCalledTimes(
				disconnectCountAfterInitialRender
			);

			unmount();
		} finally {
			globalThis.MutationObserver = originalMutationObserver;
		}
	} );

	it( 'falls back to the stable report clock when matching native date changes after a preset click', async () => {
		jest.useFakeTimers();
		jest.setSystemTime( new Date( '2026-06-15T12:00:00.000Z' ) );
		const onDateChange = jest.fn();
		appendDatePresetPopover();

		const { rerender } = render(
			<PortalHarness
				dateValue={ {
					operator: 'between',
					value: [ '2026-04-01', '2026-04-30' ],
				} }
				onDateChange={ onDateChange }
			/>
		);

		const previousMonthButton = await waitFor( () => {
			const button = document.querySelector< HTMLButtonElement >(
				'[data-wcpay-date-range-preset="last_month"]'
			);
			expect( button ).not.toBeNull();
			return button as HTMLButtonElement;
		} );

		act( () => {
			previousMonthButton.click();
		} );

		act( () => {
			rerender(
				<PortalHarness
					dateValue={ {
						operator: 'between',
						value: [ '2026-05-01', '2026-05-31' ],
					} }
					onDateChange={ onDateChange }
				/>
			);
		} );

		expect( previousMonthButton ).toHaveAttribute( 'aria-pressed', 'true' );

		act( () => {
			rerender(
				<PortalHarness
					dateValue={ {
						operator: 'between',
						value: [ '2026-04-01', '2026-04-30' ],
					} }
					onDateChange={ onDateChange }
				/>
			);
		} );

		expect( previousMonthButton ).toHaveAttribute( 'aria-pressed', 'true' );
	} );

	it( 'coalesces observer callbacks before syncing presets for a newly mounted fallback container', () => {
		const originalMutationObserver = globalThis.MutationObserver;
		const observe = jest.fn();
		const disconnect = jest.fn();
		let mutationCallback: MutationCallback | undefined;
		const animationFrameCallbacks: FrameRequestCallback[] = [];
		jest.spyOn( window, 'requestAnimationFrame' ).mockImplementation(
			( callback: FrameRequestCallback ) => {
				animationFrameCallbacks.push( callback );
				return animationFrameCallbacks.length;
			}
		);
		const MockMutationObserver = jest
			.fn()
			.mockImplementation( ( callback: MutationCallback ) => {
				mutationCallback = callback;
				return {
					observe,
					disconnect,
					takeRecords: jest.fn(),
				};
			} );
		globalThis.MutationObserver =
			MockMutationObserver as unknown as typeof MutationObserver;

		const wpBodyContent = document.createElement( 'div' );
		wpBodyContent.id = 'wpbody-content';
		document.body.appendChild( wpBodyContent );

		try {
			const { unmount } = render( <PortalHarness /> );

			const popoverFallbackContainer = document.createElement( 'div' );
			popoverFallbackContainer.className =
				'components-popover__fallback-container';
			document.body.appendChild( popoverFallbackContainer );
			const { customButton } = appendDatePresetPopover(
				popoverFallbackContainer
			);

			act( () => {
				mutationCallback?.( [], {} as MutationObserver );
				mutationCallback?.( [], {} as MutationObserver );
			} );

			expect( window.requestAnimationFrame ).toHaveBeenCalledTimes( 1 );
			expect( customButton ).toHaveAttribute( 'aria-pressed', 'true' );

			act( () => {
				animationFrameCallbacks[ 0 ]( 0 );
			} );

			expect( observe ).toHaveBeenCalledWith( popoverFallbackContainer, {
				childList: true,
				subtree: true,
			} );
			expect( customButton ).toHaveAttribute( 'aria-pressed', 'false' );
			expect( customButton ).not.toHaveAttribute( 'aria-disabled' );

			unmount();
			expect( disconnect ).toHaveBeenCalled();
		} finally {
			globalThis.MutationObserver = originalMutationObserver;
		}
	} );
} );
