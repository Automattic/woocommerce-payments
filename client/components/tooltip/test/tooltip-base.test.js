/** @format **/

/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import TooltipBase from '../tooltip-base';

jest.useFakeTimers();

describe( 'TooltipBase', () => {
	it( 'does not render its content when closed', () => {
		const handleCloseMock = jest.fn();
		render(
			<TooltipBase
				isOpen={ false }
				content="Tooltip content"
				onClose={ handleCloseMock }
			>
				<span>Trigger element</span>
			</TooltipBase>
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
			<TooltipBase
				isOpen
				content="Tooltip content"
				onClose={ handleCloseMock }
			>
				<span>Trigger element</span>
			</TooltipBase>
		);

		jest.runAllTimers();

		expect( screen.queryByText( 'Tooltip content' ) ).toBeInTheDocument();
		expect( screen.queryByText( 'Trigger element' ) ).toBeInTheDocument();
		expect( handleCloseMock ).not.toHaveBeenCalled();
	} );

	it( 'does not call onClose when an internal element is clicked', () => {
		const handleCloseMock = jest.fn();
		render(
			<TooltipBase
				isOpen
				content="Tooltip content"
				onClose={ handleCloseMock }
			>
				<span>Trigger element</span>
			</TooltipBase>
		);

		userEvent.click( screen.getByText( 'Tooltip content' ) );
		jest.runAllTimers();

		expect( screen.queryByText( 'Trigger element' ) ).toBeInTheDocument();
		expect( handleCloseMock ).not.toHaveBeenCalled();
	} );

	it( 'calls onClose when an external element is clicked', () => {
		const handleCloseMock = jest.fn();
		render(
			<>
				<TooltipBase
					isOpen
					content="Tooltip content"
					onClose={ handleCloseMock }
				>
					<span>Trigger element</span>
				</TooltipBase>
				<span>External element</span>
			</>
		);

		userEvent.click( screen.getByText( 'External element' ) );
		jest.runAllTimers();

		expect( screen.queryByText( 'Trigger element' ) ).toBeInTheDocument();
		expect( handleCloseMock ).toHaveBeenCalled();
	} );
} );
