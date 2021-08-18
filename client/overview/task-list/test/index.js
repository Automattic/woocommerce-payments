/** @format */

/**
 * External dependencies
 */
import React from 'react';
// import { render } from '@testing-library/react';
import { render, screen } from '@testing-library/react';
import { CollapsibleList, TaskItem, Text } from '@woocommerce/experimental';
import { Badge } from '@woocommerce/components';
import { useDispatch } from '@wordpress/data';

/**
 * Internal dependencies
 */
import TaskList from '..';

jest.mock( '@woocommerce/experimental', () => ( {
	CollapsibleList: jest.fn(),
	TaskItem: jest.fn(),
	Text: jest.fn(),
} ) );

jest.mock( '@woocommerce/components', () => ( {
	Badge: jest.fn(),
} ) );
jest.mock( '@wordpress/data' );

useDispatch.mockReturnValue( {
	createNotice: jest.fn(),
} );

describe( 'TaskList', () => {
	const tasksMocked = {
		key: 'task-key',
		title: 'Task Title',
		completed: false,
		content: 'Task Content',
		expanded: false,
		onClick: jest.fn(),
		action: jest.fn(),
		time: 123,
		level: 1,
	};

	const getOverviewTasksVisibilityMock = () => ( {
		deletedTodoTasks: [],
		dismissedTodoTasks: [],
		remindMeLaterTodoTasks: [],
	} );

	beforeEach( () => {
		const renderChildren = ( { children } ) => <div>{ children }</div>;
		const renderNothing = () => null;

		Badge.mockImplementation( renderNothing );
		CollapsibleList.mockImplementation( renderChildren );
		TaskItem.mockImplementation( renderChildren );
		Text.mockImplementation( renderNothing );
		const createNotice = jest.fn();
		useDispatch.mockReturnValue( {
			createNotice,
		} );
	} );
	it( 'shows an incomplete task', () => {
		const overviewTasksVisibility = getOverviewTasksVisibilityMock();
		render(
			<TaskList
				tasks={ [ tasksMocked ] }
				overviewTasksVisibility={ overviewTasksVisibility }
			/>
		);

		expect( TaskItem ).toHaveBeenCalledWith(
			expect.objectContaining( {
				title: 'Task Title',
				completed: false,
			} ),
			expect.anything()
		);
	} );
	it( 'does not show deleted tasks', () => {
		const overviewTasksVisibility = getOverviewTasksVisibilityMock();
		overviewTasksVisibility.deletedTodoTasks.push( 'task-key' );
		render(
			<TaskList
				tasks={ [ tasksMocked ] }
				overviewTasksVisibility={ overviewTasksVisibility }
			/>
		);
		expect( screen.queryByText( /Task Title 1/ ) ).not.toBeInTheDocument();
	} );
	it( 'does not show dismissed tasks', () => {
		const overviewTasksVisibility = getOverviewTasksVisibilityMock();
		overviewTasksVisibility.dismissedTodoTasks.push( 'task-key' );
		render(
			<TaskList
				tasks={ [ tasksMocked ] }
				overviewTasksVisibility={ overviewTasksVisibility }
			/>
		);
		expect( screen.queryByText( /Task Title 1/ ) ).not.toBeInTheDocument();
	} );
	it( 'does not show tasks before time', () => {
		const overviewTasksVisibility = getOverviewTasksVisibilityMock();
		const DAY_IN_MS = 24 * 60 * 60 * 1000;
		const dismissTime = Date.now() + DAY_IN_MS;

		overviewTasksVisibility.remindMeLaterTodoTasks.push( {
			'task-key': dismissTime,
		} );

		render(
			<TaskList
				tasks={ [ tasksMocked ] }
				overviewTasksVisibility={ overviewTasksVisibility }
			/>
		);
		expect( screen.queryByText( /Task Title 1/ ) ).not.toBeInTheDocument();
	} );
	it( 'shows delayed tasks after one day', () => {
		const overviewTasksVisibility = getOverviewTasksVisibilityMock();
		const DAY_IN_MS = 24 * 60 * 60 * 1000;
		const dismissTime = Date.now() - DAY_IN_MS;

		overviewTasksVisibility.remindMeLaterTodoTasks.push( {
			'task-key': dismissTime,
		} );

		render(
			<TaskList
				tasks={ [ tasksMocked ] }
				overviewTasksVisibility={ overviewTasksVisibility }
			/>
		);
		expect( TaskItem ).toHaveBeenCalledWith(
			expect.objectContaining( {
				title: 'Task Title',
				completed: false,
			} ),
			expect.anything()
		);
	} );
} );
