/** @format **/

/**
 * External dependencies
 */
import { render, screen, act } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import Tooltip from '..';

jest.useFakeTimers();

describe( 'Tooltip', () => {
	it( 'does not render its content when closed', () => {
		const handleCloseMock = jest.fn();
		render(
			<Tooltip
				isOpen={ false }
				content="Tooltip content"
				onClose={ handleCloseMock }
			>
				<span>Trigger element</span>
			</Tooltip>
		);

		jest.runAllTimers();

		expect(
			screen.queryByText( 'Tooltip content' )
		).not.toBeInTheDocument();
		expect( screen.queryByText( 'Trigger element' ) ).toBeInTheDocument();
		expect( handleCloseMock ).not.toHaveBeenCalled();
	} );

	it( 'renders its content when opened', () => {
		const handleCloseMock = jest.fn();
		render(
			<Tooltip
				isOpen
				content="Tooltip content"
				onClose={ handleCloseMock }
			>
				<span>Trigger element</span>
			</Tooltip>
		);

		jest.runAllTimers();

		expect( screen.queryByText( 'Tooltip content' ) ).toBeInTheDocument();
		expect( screen.queryByText( 'Trigger element' ) ).toBeInTheDocument();
		expect( handleCloseMock ).not.toHaveBeenCalled();
	} );

	it( 'renders its content when clicked', () => {
		const handleCloseMock = jest.fn();
		render(
			<Tooltip content="Tooltip content" onClose={ handleCloseMock }>
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
		expect( handleCloseMock ).not.toHaveBeenCalled();
	} );

	it( 'asks other Tooltips to close, when multiple are opened', () => {
		const handleClose1Mock = jest.fn();
		const handleClose2Mock = jest.fn();
		render(
			<>
				<Tooltip
					content="Tooltip 1 content"
					onClose={ handleClose1Mock }
				>
					<span>Open tooltip 1</span>
				</Tooltip>
				<Tooltip
					content="Tooltip 2 content"
					onClose={ handleClose2Mock }
				>
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
		expect( handleClose1Mock ).not.toHaveBeenCalled();
		expect( handleClose2Mock ).not.toHaveBeenCalled();

		// opening the first tooltip, no need to call any close handlers
		act( () => userEvent.click( screen.getByText( 'Open tooltip 1' ) ) );

		expect( screen.queryByText( 'Tooltip 1 content' ) ).toBeInTheDocument();
		expect(
			screen.queryByText( 'Tooltip 2 content' )
		).not.toBeInTheDocument();
		expect( handleClose1Mock ).not.toHaveBeenCalled();
		expect( handleClose2Mock ).not.toHaveBeenCalled();

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
	} );
} );
