/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { act, fireEvent, render, screen } from '@testing-library/react';
import { CollapsibleList, TaskItem, Text } from '@woocommerce/experimental';
import { Badge } from '@woocommerce/components';
import { useDispatch } from '@wordpress/data';

/**
 * Internal dependencies
 */
import TaskList from '..';
import { saveOption } from 'wcpay/data/settings/actions';

jest.mock( '@woocommerce/experimental', () => ( {
	CollapsibleList: jest.fn(),
	TaskItem: jest.fn(),
	Text: jest.fn(),
} ) );

jest.mock( '@woocommerce/components', () => ( {
	Badge: jest.fn(),
} ) );
jest.mock( '@wordpress/data' );
jest.mock( 'wcpay/data/settings/actions', () => ( {
	saveOption: jest.fn(),
} ) );

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
		remindMeLaterTodoTasks: {},
	} );

	beforeEach( () => {
		const renderNothing = () => null;

		Badge.mockImplementation( renderNothing );
		CollapsibleList.mockImplementation( ( { children } ) => (
			<div>{ children }</div>
		) );
		TaskItem.mockImplementation( ( { title } ) => <div>{ title }</div> );
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

		expect( screen.queryByText( /Task Title/ ) ).toBeInTheDocument();
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
		expect( screen.queryByText( /Task Title/ ) ).not.toBeInTheDocument();
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
		expect( screen.queryByText( /Task Title/ ) ).not.toBeInTheDocument();
	} );
	it( 'does not show tasks before time', () => {
		const overviewTasksVisibility = getOverviewTasksVisibilityMock();
		const DAY_IN_MS = 24 * 60 * 60 * 1000;
		const dismissTime = Date.now() + DAY_IN_MS;

		overviewTasksVisibility.remindMeLaterTodoTasks = {
			'task-key': dismissTime,
		};

		render(
			<TaskList
				tasks={ [ tasksMocked ] }
				overviewTasksVisibility={ overviewTasksVisibility }
			/>
		);
		expect( screen.queryByText( /Task Title/ ) ).not.toBeInTheDocument();
	} );
	it( 'shows snoozed tasks after one day', () => {
		const overviewTasksVisibility = getOverviewTasksVisibilityMock();
		const DAY_IN_MS = 24 * 60 * 60 * 1000;
		const dismissTime = Date.now() - DAY_IN_MS;

		overviewTasksVisibility.remindMeLaterTodoTasks = {
			'task-key': dismissTime,
		};

		render(
			<TaskList
				tasks={ [ tasksMocked ] }
				overviewTasksVisibility={ overviewTasksVisibility }
			/>
		);
		expect( screen.queryByText( /Task Title/ ) ).toBeInTheDocument();
	} );

	describe( 'a task that owns its dismissal', () => {
		let createNotice;

		const renderTask = (
			task,
			visibility = getOverviewTasksVisibilityMock()
		) => {
			createNotice = jest.fn();
			useDispatch.mockReturnValue( { createNotice } );
			TaskItem.mockImplementation( ( { title, onDismiss } ) => (
				<button onClick={ onDismiss }>{ title }</button>
			) );

			render(
				<TaskList
					tasks={ [
						{ ...tasksMocked, isDismissable: true, ...task },
					] }
					overviewTasksVisibility={ visibility }
				/>
			);
		};

		const undoFromNotice = () => {
			const [ , , { actions } ] = createNotice.mock.calls[ 0 ];
			act( () => {
				actions[ 0 ].onClick();
			} );
		};

		beforeEach( () => {
			saveOption.mockClear();
		} );

		it( 'hides the task and calls onDismiss without writing the dismissed-tasks option', () => {
			const onDismiss = jest.fn();
			renderTask( { ownsDismissal: true, onDismiss } );

			fireEvent.click( screen.getByText( 'Task Title' ) );

			expect(
				screen.queryByText( 'Task Title' )
			).not.toBeInTheDocument();
			expect( onDismiss ).toHaveBeenCalledTimes( 1 );
			expect( saveOption ).not.toHaveBeenCalled();
		} );

		it( 'is not hidden by an entry in the dismissed-tasks option', () => {
			const visibility = getOverviewTasksVisibilityMock();
			visibility.dismissedTodoTasks.push( 'task-key' );

			renderTask( { ownsDismissal: true }, visibility );

			expect( screen.queryByText( 'Task Title' ) ).toBeInTheDocument();
		} );

		it( 'shows the task again and calls onUndoDismiss when the dismissal is undone', () => {
			const onUndoDismiss = jest.fn();
			renderTask( { ownsDismissal: true, onUndoDismiss } );

			fireEvent.click( screen.getByText( 'Task Title' ) );
			undoFromNotice();

			expect( screen.queryByText( 'Task Title' ) ).toBeInTheDocument();
			expect( onUndoDismiss ).toHaveBeenCalledTimes( 1 );
			expect( saveOption ).not.toHaveBeenCalled();
		} );

		it( 'leaves a task without ownsDismissal on the dismissed-tasks option', () => {
			renderTask( {} );

			fireEvent.click( screen.getByText( 'Task Title' ) );

			expect( saveOption ).toHaveBeenCalledWith(
				'woocommerce_dismissed_todo_tasks',
				[ 'task-key' ]
			);
		} );
	} );
} );
