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
import { HoverTooltip, ClickTooltip } from '..';

describe( 'HoverTooltip', () => {
	beforeEach( () => {
		jest.useFakeTimers();
	} );

	afterEach( () => {
		jest.useRealTimers();
	} );

	it( 'does not render its content when hidden', () => {
		const handleHideMock = jest.fn();
		render(
			<HoverTooltip
				isVisible={ false }
				content="Tooltip content"
				onHide={ handleHideMock }
			>
				<span>Trigger element</span>
			</HoverTooltip>
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
			<HoverTooltip
				isVisible
				content="Tooltip content"
				onHide={ handleHideMock }
			>
				<span>Trigger element</span>
			</HoverTooltip>
		);

		jest.runAllTimers();

		expect( screen.queryByText( 'Tooltip content' ) ).toBeInTheDocument();
		expect( screen.queryByText( 'Trigger element' ) ).toBeInTheDocument();
		expect( handleHideMock ).not.toHaveBeenCalled();
	} );

	it( 'renders its content when clicked', () => {
		const handleHideMock = jest.fn();
		render(
			<HoverTooltip content="Tooltip content" onHide={ handleHideMock }>
				<span>Trigger element</span>
			</HoverTooltip>
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

	it( 'renders and hides its content when hovered', () => {
		const handleHideMock = jest.fn();
		render(
			<HoverTooltip content="Tooltip content" onHide={ handleHideMock }>
				<span>Trigger element</span>
			</HoverTooltip>
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
} );

describe( 'ClickTooltip', () => {
	beforeEach( () => {
		jest.useFakeTimers();
	} );

	afterEach( () => {
		jest.useRealTimers();
	} );

	it( 'does not render its content when hidden', () => {
		const handleHideMock = jest.fn();
		render(
			<ClickTooltip
				isVisible={ false }
				content="Tooltip content"
				onHide={ handleHideMock }
			>
				<span>Trigger element</span>
			</ClickTooltip>
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
			<ClickTooltip
				isVisible
				content="Tooltip content"
				onHide={ handleHideMock }
			>
				<span>Trigger element</span>
			</ClickTooltip>
		);

		jest.runAllTimers();

		expect( screen.queryByText( 'Tooltip content' ) ).toBeInTheDocument();
		expect( screen.queryByText( 'Trigger element' ) ).toBeInTheDocument();
		expect( handleHideMock ).not.toHaveBeenCalled();
	} );

	it( 'renders and hides its content when clicked', () => {
		const handleHideMock = jest.fn();
		render(
			<ClickTooltip content="Tooltip content" onHide={ handleHideMock }>
				<span>Trigger element</span>
			</ClickTooltip>
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

	it( `doesn't show or hide on hover events`, () => {
		const handleHideMock = jest.fn();
		render(
			<ClickTooltip content="Tooltip content" onHide={ handleHideMock }>
				<span>Trigger element</span>
			</ClickTooltip>
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

	it( `correctly navigates to link in tooltip content via keyboard navigation`, () => {
		const handleHideMock = jest.fn();
		render(
			<ClickTooltip
				content={
					// Tooltip content includes a link element which should be navigable via keyboard
					<span>
						Tooltip content <a href="woocommerce.com">Link</a>
					</span>
				}
				onHide={ handleHideMock }
			>
				<span role="button" tabIndex={ 0 }>
					Trigger element
				</span>
			</ClickTooltip>
		);

		expect(
			screen.queryByText( 'Tooltip content' )
		).not.toBeInTheDocument();

		act( () => {
			userEvent.click( screen.getByText( 'Trigger element' ) );
			fireEvent.focus( screen.getByText( 'Trigger element' ) );
			jest.runAllTimers();
		} );

		expect( screen.queryByText( 'Tooltip content' ) ).toBeInTheDocument();
		expect( handleHideMock ).not.toHaveBeenCalled();

		userEvent.tab();

		expect(
			screen.getAllByRole( 'link', { name: 'Link' } )[ 0 ]
		).toHaveFocus();
	} );
} );

describe( 'Tooltips', () => {
	beforeEach( () => {
		jest.useFakeTimers();
	} );

	afterEach( () => {
		jest.useRealTimers();
	} );

	type Tooltips = {
		content: string;
		handleHideMock: jest.Mock;
	}[];
	const tooltips: Tooltips = [
		{
			content: 'Tooltip 1 content',
			handleHideMock: jest.fn(),
		},
		{
			content: 'Tooltip 2 content',
			handleHideMock: jest.fn(),
		},
		{
			content: 'Tooltip 3 content',
			handleHideMock: jest.fn(),
		},
	];
	const setupTooltips = () => {
		render(
			<>
				<HoverTooltip
					content={ tooltips[ 0 ].content }
					onHide={ tooltips[ 0 ].handleHideMock }
				>
					<span>Open tooltip 0</span>
				</HoverTooltip>
				<ClickTooltip
					content={ tooltips[ 1 ].content }
					onHide={ tooltips[ 1 ].handleHideMock }
				>
					<span>Open tooltip 1</span>
				</ClickTooltip>
				<HoverTooltip
					content={ tooltips[ 2 ].content }
					onHide={ tooltips[ 2 ].handleHideMock }
				>
					<span>Open tooltip 2</span>
				</HoverTooltip>
			</>
		);

		jest.runAllTimers();
	};

	it( 'asks other tooltips to hide when a tooltip is opened', () => {
		setupTooltips();

		const assertTooltipsVisibility = ( {
			visibleTooltip,
		}: {
			visibleTooltip?: 0 | 1 | 2;
		} ) => {
			const hiddenTooltips = [ 0, 1, 2 ].filter(
				( tooltip ) => tooltip !== visibleTooltip
			);
			if ( visibleTooltip !== undefined ) {
				expect(
					screen.queryByText( tooltips[ visibleTooltip ].content )
				).toBeInTheDocument();
			}
			hiddenTooltips.forEach( ( tooltip ) => {
				expect(
					screen.queryByText( tooltips[ tooltip ].content )
				).not.toBeInTheDocument();
			} );
		};

		assertTooltipsVisibility( { visibleTooltip: undefined } );

		// opening the first tooltip, no need to call any hide handlers
		act( () => {
			userEvent.click( screen.getByText( 'Open tooltip 0' ) );
			jest.runAllTimers();
		} );
		assertTooltipsVisibility( { visibleTooltip: 0 } );
		expect( tooltips[ 0 ].handleHideMock ).not.toHaveBeenCalled();
		expect( tooltips[ 1 ].handleHideMock ).not.toHaveBeenCalled();
		expect( tooltips[ 2 ].handleHideMock ).not.toHaveBeenCalled();

		// opening the second tooltip, the first tooltip should not be visible
		act( () => {
			userEvent.click( screen.getByText( 'Open tooltip 1' ) );
			jest.runAllTimers();
		} );
		assertTooltipsVisibility( { visibleTooltip: 1 } );
		expect( tooltips[ 0 ].handleHideMock ).toHaveBeenCalled();
		expect( tooltips[ 1 ].handleHideMock ).not.toHaveBeenCalled();
		expect( tooltips[ 2 ].handleHideMock ).not.toHaveBeenCalled();

		// opening the third tooltip, the second tooltip should not be visible
		act( () => {
			userEvent.click( screen.getByText( 'Open tooltip 2' ) );
			jest.runAllTimers();
		} );
		assertTooltipsVisibility( { visibleTooltip: 2 } );
		expect( tooltips[ 0 ].handleHideMock ).toHaveBeenCalled();
		expect( tooltips[ 1 ].handleHideMock ).toHaveBeenCalled();
		expect( tooltips[ 2 ].handleHideMock ).not.toHaveBeenCalled();

		// opening the first tooltip, the third tooltip should not be visible
		act( () => {
			userEvent.click( screen.getByText( 'Open tooltip 0' ) );
			jest.runAllTimers();
		} );
		assertTooltipsVisibility( { visibleTooltip: 0 } );
		expect( tooltips[ 0 ].handleHideMock ).toHaveBeenCalled();
		expect( tooltips[ 1 ].handleHideMock ).toHaveBeenCalled();
		expect( tooltips[ 2 ].handleHideMock ).toHaveBeenCalled();
	} );
} );
