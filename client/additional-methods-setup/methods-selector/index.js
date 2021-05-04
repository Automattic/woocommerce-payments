/**
 * External dependencies
 */
import React from 'react';
import { Card, CardBody } from '@wordpress/components';

/**
 * Internal dependencies
 */
import SetupTasksController from '../setup-tasks/setup-provider';
import { TaskList } from './task-list';
import AddPaymentMethodsTask from './add-payment-methods-task';
import SetupCompleteTask from './setup-complete-task';

const MethodsSelector = () => (
	<Card className="methods-selector">
		<CardBody>
			<SetupTasksController defaultActiveTask="add-payment-methods">
				<TaskList>
					<AddPaymentMethodsTask />
					<SetupCompleteTask />
				</TaskList>
			</SetupTasksController>
		</CardBody>
	</Card>
);

export default MethodsSelector;
