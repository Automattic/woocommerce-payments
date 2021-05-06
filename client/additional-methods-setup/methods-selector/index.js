/**
 * External dependencies
 */
import React from 'react';
import { Card, CardBody } from '@wordpress/components';

/**
 * Internal dependencies
 */
import WizardContextProvider from '../wizard/parent/provider';
import TaskContextProvider from '../wizard/task/provider';
import ParentList from '../wizard/parent-list';
import AddPaymentMethodsTask from './add-payment-methods-task';
import SetupCompleteTask from './setup-complete-task';

const MethodsSelector = () => (
	<Card className="methods-selector">
		<CardBody>
			<WizardContextProvider defaultActiveTask="add-payment-methods">
				<ParentList>
					<TaskContextProvider id="add-payment-methods">
						<AddPaymentMethodsTask />
					</TaskContextProvider>
					<TaskContextProvider id="setup-complete">
						<SetupCompleteTask />
					</TaskContextProvider>
				</ParentList>
			</WizardContextProvider>
		</CardBody>
	</Card>
);

export default MethodsSelector;
