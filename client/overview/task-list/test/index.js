/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';
import { CollapsibleList, TaskItem, Text } from '@woocommerce/experimental';
import { Badge } from '@woocommerce/components';

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

describe( 'TaskList', () => {
	const createTaskMock = () => ( {
		key: 'task-key',
		title: 'Task Title',
		completed: false,
		content: 'Task Content',
		expanded: false,
		onClick: jest.fn(),
		action: jest.fn(),
		time: 123,
		level: 1,
	} );

	beforeEach( () => {
		const renderChildren = ( { children } ) => <div>{ children }</div>;
		const renderNothing = () => null;

		Badge.mockImplementation( renderNothing );
		CollapsibleList.mockImplementation( renderChildren );
		TaskItem.mockImplementation( renderChildren );
		Text.mockImplementation( renderNothing );
	} );

	it( "map task's `onClick` prop to `action`", () => {
		const task = createTaskMock();

		render( <TaskList tasks={ [ task ] } /> );

		expect( TaskItem ).toHaveBeenCalledWith(
			expect.objectContaining( {
				action: task.onClick,
			} ),
			expect.anything()
		);
	} );
} );
