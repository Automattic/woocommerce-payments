/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { act, render, screen } from '@testing-library/react';
import { userEvent } from 'jest-utils/user-event-timers';

/**
 * Internal dependencies
 */
import TooltipBase from '../tooltip-base';

jest.useFakeTimers();

describe( 'TooltipBase', () => {
	it( 'does not render its content when hidden', () => {
		const handleHideMock = jest.fn();
		render(
			<TooltipBase
				isVisible={ false }
				content="Tooltip content"
				onHide={ handleHideMock }
			>
				<span>Trigger element</span>
			</TooltipBase>
		);

		jest.runAllTimers();

		expect(
			screen.queryByText( 'Tooltip content' )
		).not.toBeInTheDocument();
		expect( screen.queryByText( 'Trigger element' ) ).toBeInTheDocument();
		expect( handleHideMock ).not.toHaveBeenCalled();
	} );

	it( 'renders its content when opened', () => {
		const handleHideMock = jest.fn();
		render(
			<TooltipBase
				isVisible
				content="Tooltip content"
				onHide={ handleHideMock }
			>
				<span>Trigger element</span>
			</TooltipBase>
		);

		jest.runAllTimers();

		expect( screen.queryByText( 'Tooltip content' ) ).toBeInTheDocument();
		expect( screen.queryByText( 'Trigger element' ) ).toBeInTheDocument();
		expect( handleHideMock ).not.toHaveBeenCalled();
	} );

	it( 'does not call onHide when an internal element is clicked', async () => {
		const handleHideMock = jest.fn();
		render(
			<TooltipBase
				isVisible
				content="Tooltip content"
				onHide={ handleHideMock }
			>
				<span>Trigger element</span>
			</TooltipBase>
		);

		await userEvent.click( screen.getByText( 'Tooltip content' ) );
		jest.runAllTimers();

		expect( screen.queryByText( 'Trigger element' ) ).toBeInTheDocument();
		expect( handleHideMock ).not.toHaveBeenCalled();
	} );

	it( 'shows the tooltip synchronously on the render after isVisible flips to true', () => {
		// Pin for WOOPMNT-6292: useHideDelay must mirror the isVisible prop to
		// state in the same render tick so the tooltip DOM is present on the
		// first render after the prop flips (no cascading effect render).
		const { rerender } = render(
			<TooltipBase isVisible={ false } content="Tooltip content">
				<span>Trigger element</span>
			</TooltipBase>
		);

		expect( screen.queryByRole( 'tooltip' ) ).not.toBeInTheDocument();

		act( () => {
			rerender(
				<TooltipBase isVisible content="Tooltip content">
					<span>Trigger element</span>
				</TooltipBase>
			);
		} );

		// Must be present synchronously after the rerender — no findBy / no
		// awaiting of effects. The role query is used (not text) because the
		// test does not need to be coupled to the content string.
		expect( screen.getByRole( 'tooltip' ) ).toBeInTheDocument();
	} );

	it( 'calls onHide when an external element is clicked', async () => {
		const handleHideMock = jest.fn();
		render(
			<>
				<TooltipBase
					isVisible
					content="Tooltip content"
					onHide={ handleHideMock }
				>
					<span>Trigger element</span>
				</TooltipBase>
				<button type="button">External element</button>
			</>
		);

		await userEvent.click( screen.getByText( 'External element' ) );
		jest.runAllTimers();

		expect( screen.queryByText( 'Trigger element' ) ).toBeInTheDocument();
		expect( handleHideMock ).toHaveBeenCalled();
	} );
} );
