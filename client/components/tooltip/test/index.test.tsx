/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { render, screen, act, fireEvent } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import Tooltip from '..';

describe( 'Tooltip', () => {
	beforeEach( () => {
		jest.useFakeTimers();
	} );

	afterEach( () => {
		jest.useRealTimers();
	} );

	it( 'does not render its content when hidden', () => {
		const handleHideMock = jest.fn();
		render(
			<Tooltip
				isVisible={ false }
				content="Tooltip content"
				onHide={ handleHideMock }
			>
				<span>Trigger element</span>
			</Tooltip>
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
			<Tooltip
				isVisible
				content="Tooltip content"
				onHide={ handleHideMock }
			>
				<span>Trigger element</span>
			</Tooltip>
		);

		jest.runAllTimers();

		expect( screen.queryByText( 'Tooltip content' ) ).toBeInTheDocument();
		expect( screen.queryByText( 'Trigger element' ) ).toBeInTheDocument();
		expect( handleHideMock ).not.toHaveBeenCalled();
	} );

	it( 'renders its content when clicked', () => {
		const handleHideMock = jest.fn();
		render(
			<Tooltip content="Tooltip content" onHide={ handleHideMock }>
				<span>Trigger element</span>
			</Tooltip>
		);

		jest.runAllTimers();

		expect(
			screen.queryByText( 'Tooltip content' )
		).not.toBeInTheDocument();

		userEvent.click( screen.getByText( 'Trigger element' ) );

		jest.runAllTimers();

		expect( screen.queryByText( 'Tooltip content' ) ).toBeInTheDocument();
		expect( handleHideMock ).not.toHaveBeenCalled();

		userEvent.click( screen.getByText( 'Trigger element' ) );
		jest.runAllTimers();

		expect( handleHideMock ).toHaveBeenCalled();
	} );

	it( 'renders and hides its content when clicked and ignoreMouseHover = true', () => {
		const handleHideMock = jest.fn();
		render(
			<Tooltip
				content="Tooltip content"
				onHide={ handleHideMock }
				ignoreMouseHover
			>
				<span>Trigger element</span>
			</Tooltip>
		);

		jest.runAllTimers();

		expect(
			screen.queryByText( 'Tooltip content' )
		).not.toBeInTheDocument();

		act( () => {
			userEvent.click( screen.getByText( 'Trigger element' ) );
			jest.runAllTimers();
		} );

		expect( screen.queryByText( 'Tooltip content' ) ).toBeInTheDocument();
		expect( handleHideMock ).not.toHaveBeenCalled();

		act( () => {
			userEvent.click( screen.getByText( 'Trigger element' ) );
			jest.runAllTimers();
		} );

		expect(
			screen.queryByText( 'Tooltip content' )
		).not.toBeInTheDocument();
		expect( handleHideMock ).toHaveBeenCalled();
	} );

	it( 'renders and hides its content when hovered', () => {
		const handleHideMock = jest.fn();
		render(
			<Tooltip content="Tooltip content" onHide={ handleHideMock }>
				<span>Trigger element</span>
			</Tooltip>
		);

		expect(
			screen.queryByText( 'Tooltip content' )
		).not.toBeInTheDocument();

		act( () => {
			fireEvent.mouseOver( screen.getByText( 'Trigger element' ) );
			jest.runAllTimers();
		} );

		expect( screen.queryByText( 'Tooltip content' ) ).toBeInTheDocument();
		expect( handleHideMock ).not.toHaveBeenCalled();

		act( () => {
			fireEvent.mouseLeave( screen.getByText( 'Trigger element' ) );
			jest.advanceTimersByTime( 1000 );
		} );

		expect(
			screen.queryByText( 'Tooltip content' )
		).not.toBeInTheDocument();
		expect( handleHideMock ).toHaveBeenCalled();
	} );

	it( 'remains hidden when hovered ignoreMouseHover = true', () => {
		const handleHideMock = jest.fn();
		render(
			<Tooltip
				content="Tooltip content"
				onHide={ handleHideMock }
				ignoreMouseHover
			>
				<span>Trigger element</span>
			</Tooltip>
		);

		expect(
			screen.queryByText( 'Tooltip content' )
		).not.toBeInTheDocument();

		act( () => {
			fireEvent.mouseOver( screen.getByText( 'Trigger element' ) );
			jest.runAllTimers();
		} );

		expect(
			screen.queryByText( 'Tooltip content' )
		).not.toBeInTheDocument();
		expect( handleHideMock ).not.toHaveBeenCalled();

		act( () => {
			fireEvent.mouseLeave( screen.getByText( 'Trigger element' ) );
			jest.advanceTimersByTime( 1000 );
		} );

		expect(
			screen.queryByText( 'Tooltip content' )
		).not.toBeInTheDocument();
		expect( handleHideMock ).not.toHaveBeenCalled();
	} );

	it( 'asks other Tooltips to hide, when multiple are opened', () => {
		const handleHide1Mock = jest.fn();
		const handleHide2Mock = jest.fn();
		render(
			<>
				<Tooltip content="Tooltip 1 content" onHide={ handleHide1Mock }>
					<span>Open tooltip 1</span>
				</Tooltip>
				<Tooltip content="Tooltip 2 content" onHide={ handleHide2Mock }>
					<span>Open tooltip 2</span>
				</Tooltip>
			</>
		);

		jest.runAllTimers();

		expect(
			screen.queryByText( 'Tooltip 1 content' )
		).not.toBeInTheDocument();
		expect(
			screen.queryByText( 'Tooltip 2 content' )
		).not.toBeInTheDocument();
		expect( handleHide1Mock ).not.toHaveBeenCalled();
		expect( handleHide2Mock ).not.toHaveBeenCalled();

		// opening the first tooltip, no need to call any hide handlers
		act( () => userEvent.click( screen.getByText( 'Open tooltip 1' ) ) );

		expect( screen.queryByText( 'Tooltip 1 content' ) ).toBeInTheDocument();
		expect(
			screen.queryByText( 'Tooltip 2 content' )
		).not.toBeInTheDocument();
		expect( handleHide1Mock ).not.toHaveBeenCalled();
		expect( handleHide2Mock ).not.toHaveBeenCalled();

		jest.runAllTimers();

		// opening the second tooltip, only the first tooltip should not be visible anymore
		act( () => {
			userEvent.click( screen.getByText( 'Open tooltip 2' ) );
			jest.runAllTimers();
		} );

		expect(
			screen.queryByText( 'Tooltip 1 content' )
		).not.toBeInTheDocument();
		expect( screen.queryByText( 'Tooltip 2 content' ) ).toBeInTheDocument();
		expect( handleHide1Mock ).toHaveBeenCalled();
		expect( handleHide2Mock ).not.toHaveBeenCalled();
	} );
} );
