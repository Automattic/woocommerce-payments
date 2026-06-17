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

	parent.appendChild( popover );

	return { customButton };
};

const PortalHarness = ( {
	dateValue = previousYearValue,
	onDateChange = jest.fn(),
}: {
	dateValue?: DateFilterValue;
	onDateChange?: ( value: DateFilterValue ) => void;
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
} );

describe( 'DataViewsDateRangePresetPortal', () => {
	it( 'syncs the native Custom button when DataViews uses the default i18n domain', async () => {
		const { customButton } = appendDatePresetPopover();

		render( <PortalHarness /> );

		await waitFor( () =>
			expect( customButton ).toHaveAttribute( 'aria-disabled', 'true' )
		);
		expect( customButton ).toHaveAttribute( 'aria-pressed', 'false' );
		expect( customButton ).toHaveClass(
			'wcpay-reports-date-range-preset--custom-disabled'
		);
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

	it( 'syncs presets when the WordPress popover fallback container is added after mount', () => {
		const originalMutationObserver = globalThis.MutationObserver;
		const observe = jest.fn();
		const disconnect = jest.fn();
		let mutationCallback: MutationCallback | undefined;
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
			} );

			expect( observe ).toHaveBeenCalledWith( popoverFallbackContainer, {
				childList: true,
				subtree: true,
			} );
			expect( customButton ).toHaveAttribute( 'aria-disabled', 'true' );
			expect( customButton ).toHaveAttribute( 'aria-pressed', 'false' );

			unmount();
			expect( disconnect ).toHaveBeenCalled();
		} finally {
			globalThis.MutationObserver = originalMutationObserver;
		}
	} );
} );
