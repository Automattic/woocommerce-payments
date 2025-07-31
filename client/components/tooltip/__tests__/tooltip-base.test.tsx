/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';
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
