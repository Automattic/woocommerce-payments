/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { act, renderHook } from '@testing-library/react-hooks';
import type { View } from '@wordpress/dataviews/wp';

/**
 * Internal dependencies
 */
import { useDateFilterChipInterceptor } from '../use-date-filter-chip-interceptor';
import {
	encodeCustomDateFilterValue,
	resolveFeesDateFilterValue,
} from '../date-filter-values';

const popoverId = 'wcpay-fees-date-filter-popover';

const baseView = ( overrides: Partial< View > = {} ): View =>
	( {
		type: 'table',
		page: 1,
		perPage: 25,
		sort: { field: 'date', direction: 'desc' },
		search: '',
		filters: [],
		fields: [],
		...overrides,
	} as View );

const buildContainerWithChip = (): {
	container: HTMLDivElement;
	chip: HTMLButtonElement;
} => {
	const container = document.createElement( 'div' );
	container.className = 'wcpay-reports-fees__main';

	const filtersContainer = document.createElement( 'div' );
	filtersContainer.className = 'dataviews-filters__container';

	const chip = document.createElement( 'button' );
	chip.className = 'dataviews-filters__summary-chip';
	chip.textContent = 'Date';

	filtersContainer.append( chip );
	container.append( filtersContainer );
	document.body.append( container );

	return { container, chip };
};

const fakePointerEvent = ( target: HTMLElement ) =>
	( {
		button: 0,
		target,
		preventDefault: jest.fn(),
		stopPropagation: jest.fn(),
	} as unknown as React.PointerEvent< HTMLDivElement > );

const fakeClickEvent = ( target: HTMLElement ) =>
	( {
		target,
		preventDefault: jest.fn(),
		stopPropagation: jest.fn(),
	} as unknown as React.MouseEvent< HTMLDivElement > );

const fakeKeyEvent = ( target: HTMLElement, key: string ) =>
	( {
		key,
		target,
		preventDefault: jest.fn(),
		stopPropagation: jest.fn(),
	} as unknown as React.KeyboardEvent< HTMLDivElement > );

afterEach( () => {
	document.body.innerHTML = '';
} );

describe( 'useDateFilterChipInterceptor', () => {
	it( 'returns a null anchor when the container is null', () => {
		const { result } = renderHook( () =>
			useDateFilterChipInterceptor( {
				container: null,
				view: baseView(),
				setView: jest.fn(),
				popoverId,
			} )
		);
		expect( result.current.anchor ).toBeNull();
		expect( result.current.isPopoverOpen ).toBe( false );
	} );

	it( 'resolves the anchor as the first chip in the filters container', () => {
		const { container, chip } = buildContainerWithChip();
		const { result } = renderHook( () =>
			useDateFilterChipInterceptor( {
				container,
				view: baseView(),
				setView: jest.fn(),
				popoverId,
			} )
		);
		expect( result.current.anchor ).toBe( chip );
	} );

	it( 'mirrors dialog ARIA attributes onto the anchor', () => {
		const { container, chip } = buildContainerWithChip();
		renderHook( () =>
			useDateFilterChipInterceptor( {
				container,
				view: baseView(),
				setView: jest.fn(),
				popoverId,
			} )
		);
		expect( chip.getAttribute( 'aria-haspopup' ) ).toBe( 'dialog' );
		expect( chip.getAttribute( 'aria-expanded' ) ).toBe( 'false' );
		expect( chip.hasAttribute( 'aria-controls' ) ).toBe( false );
	} );

	it( 'restores ARIA attributes when DataViews mutates them away', async () => {
		const { container, chip } = buildContainerWithChip();
		renderHook( () =>
			useDateFilterChipInterceptor( {
				container,
				view: baseView(),
				setView: jest.fn(),
				popoverId,
			} )
		);

		chip.removeAttribute( 'aria-haspopup' );
		chip.setAttribute( 'aria-expanded', 'true' );

		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

		expect( chip.getAttribute( 'aria-haspopup' ) ).toBe( 'dialog' );
		expect( chip.getAttribute( 'aria-expanded' ) ).toBe( 'false' );
	} );

	it( 'opens the popover on chip pointerdown and exposes aria-controls', () => {
		const { container, chip } = buildContainerWithChip();
		const { result } = renderHook( () =>
			useDateFilterChipInterceptor( {
				container,
				view: baseView(),
				setView: jest.fn(),
				popoverId,
			} )
		);

		act( () => {
			result.current.captureHandlers.onPointerDownCapture(
				fakePointerEvent( chip )
			);
		} );

		expect( result.current.isPopoverOpen ).toBe( true );
		expect( chip.getAttribute( 'aria-expanded' ) ).toBe( 'true' );
		expect( chip.getAttribute( 'aria-controls' ) ).toBe( popoverId );
	} );

	it( 'ignores the click that follows a chip pointerdown', () => {
		const { container, chip } = buildContainerWithChip();
		const { result } = renderHook( () =>
			useDateFilterChipInterceptor( {
				container,
				view: baseView(),
				setView: jest.fn(),
				popoverId,
			} )
		);

		act( () => {
			result.current.captureHandlers.onPointerDownCapture(
				fakePointerEvent( chip )
			);
		} );
		act( () => {
			result.current.captureHandlers.onClickCapture(
				fakeClickEvent( chip )
			);
		} );

		expect( result.current.isPopoverOpen ).toBe( true );
	} );

	it( 'toggles closed when the chip is activated while open', () => {
		const { container, chip } = buildContainerWithChip();
		const { result } = renderHook( () =>
			useDateFilterChipInterceptor( {
				container,
				view: baseView(),
				setView: jest.fn(),
				popoverId,
			} )
		);

		act( () => {
			result.current.captureHandlers.onPointerDownCapture(
				fakePointerEvent( chip )
			);
		} );
		act( () => {
			result.current.captureHandlers.onClickCapture(
				fakeClickEvent( chip )
			);
		} );
		act( () => {
			result.current.captureHandlers.onPointerDownCapture(
				fakePointerEvent( chip )
			);
		} );

		expect( result.current.isPopoverOpen ).toBe( false );
	} );

	it( 'opens the popover via keyboard activation', () => {
		const { container, chip } = buildContainerWithChip();
		const { result } = renderHook( () =>
			useDateFilterChipInterceptor( {
				container,
				view: baseView(),
				setView: jest.fn(),
				popoverId,
			} )
		);

		act( () => {
			result.current.captureHandlers.onKeyDownCapture(
				fakeKeyEvent( chip, 'Enter' )
			);
		} );

		expect( result.current.isPopoverOpen ).toBe( true );
	} );

	it( 'does not act on keys other than Enter and Space', () => {
		const { container, chip } = buildContainerWithChip();
		const { result } = renderHook( () =>
			useDateFilterChipInterceptor( {
				container,
				view: baseView(),
				setView: jest.fn(),
				popoverId,
			} )
		);

		act( () => {
			result.current.captureHandlers.onKeyDownCapture(
				fakeKeyEvent( chip, 'Tab' )
			);
		} );

		expect( result.current.isPopoverOpen ).toBe( false );
	} );

	it( 'leaves clicks outside the chip alone', () => {
		const { container } = buildContainerWithChip();
		const outside = document.createElement( 'span' );
		container.append( outside );

		const { result } = renderHook( () =>
			useDateFilterChipInterceptor( {
				container,
				view: baseView(),
				setView: jest.fn(),
				popoverId,
			} )
		);

		const event = fakePointerEvent( outside );
		act( () => {
			result.current.captureHandlers.onPointerDownCapture( event );
		} );

		expect( result.current.isPopoverOpen ).toBe( false );
		expect( event.preventDefault ).not.toHaveBeenCalled();
	} );

	it( 'exposes the resolved date filter as the popover initial value when opened', () => {
		const { container, chip } = buildContainerWithChip();
		const dateValue = encodeCustomDateFilterValue( {
			operator: 'between',
			value: [ '2026-03-01', '2026-03-31' ],
		} );
		const view = baseView( {
			filters: [
				{
					field: 'date',
					operator: 'is',
					value: dateValue,
				},
			],
		} );

		const { result } = renderHook( () =>
			useDateFilterChipInterceptor( {
				container,
				view,
				setView: jest.fn(),
				popoverId,
			} )
		);

		act( () => {
			result.current.captureHandlers.onPointerDownCapture(
				fakePointerEvent( chip )
			);
		} );

		expect( result.current.initialValue ).toEqual(
			resolveFeesDateFilterValue( dateValue )
		);
	} );

	it( 'commits the new date filter, replaces existing date filters, and resets the page', () => {
		const { container } = buildContainerWithChip();
		const setView = jest.fn();
		const view = baseView( {
			page: 4,
			filters: [
				{
					field: 'date',
					operator: 'is',
					value: 'date_preset:month_to_date',
				},
				{ field: 'payment_method', operator: 'is', value: 'card' },
			],
		} );

		const { result } = renderHook( () =>
			useDateFilterChipInterceptor( {
				container,
				view,
				setView,
				popoverId,
			} )
		);

		act( () => {
			result.current.onPopoverChange( {
				operator: 'after',
				value: '2026-01-01',
			} );
		} );

		expect( setView ).toHaveBeenCalledTimes( 1 );
		const next = setView.mock.calls[ 0 ][ 0 ] as View;
		expect( next.page ).toBe( 1 );
		expect( next.filters ).toEqual( [
			{ field: 'payment_method', operator: 'is', value: 'card' },
			{
				field: 'date',
				operator: 'is',
				value: encodeCustomDateFilterValue( {
					operator: 'after',
					value: '2026-01-01',
				} ),
			},
		] );
	} );

	it( 'removes a value-less date filter on popover close', () => {
		const { container } = buildContainerWithChip();
		const setView = jest.fn();
		const view = baseView( {
			filters: [
				{ field: 'date', operator: 'is', value: undefined },
				{ field: 'payment_method', operator: 'is', value: 'card' },
			],
		} );

		const { result } = renderHook( () =>
			useDateFilterChipInterceptor( {
				container,
				view,
				setView,
				popoverId,
			} )
		);

		act( () => {
			result.current.onPopoverClose();
		} );

		expect( setView ).toHaveBeenCalledTimes( 1 );
		const next = setView.mock.calls[ 0 ][ 0 ] as View;
		expect( next.filters ).toEqual( [
			{ field: 'payment_method', operator: 'is', value: 'card' },
		] );
		expect( result.current.isPopoverOpen ).toBe( false );
	} );

	it( 'leaves the view untouched when closing without a value-less date filter', () => {
		const { container, chip } = buildContainerWithChip();
		const setView = jest.fn();

		const { result } = renderHook( () =>
			useDateFilterChipInterceptor( {
				container,
				view: baseView(),
				setView,
				popoverId,
			} )
		);

		// Open then close via the regular path.
		act( () => {
			result.current.captureHandlers.onPointerDownCapture(
				fakePointerEvent( chip )
			);
		} );
		act( () => {
			result.current.onPopoverClose();
		} );

		expect( setView ).not.toHaveBeenCalled();
		expect( result.current.isPopoverOpen ).toBe( false );
	} );
} );
