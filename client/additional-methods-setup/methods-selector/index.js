/**
 * External dependencies
 */
import React from 'react';
import { Card, CardBody } from '@wordpress/components';

/**
 * Internal dependencies
 */
import Wizard from '../wizard/wrapper';
import WizardTask from '../wizard/task';
import WizardTaskList from '../wizard/task-list';
import AddPaymentMethodsTask from './add-payment-methods-task';
import SetupCompleteTask from './setup-complete-task';
import './index.scss';

const MethodsSelector = () => (
	<Card className="methods-selector">
		<CardBody>
			<Wizard defaultActiveTask="add-payment-methods">
				<WizardTaskList>
					<WizardTask id="add-payment-methods">
						<AddPaymentMethodsTask />
					</WizardTask>
					<WizardTask id="setup-complete">
						<SetupCompleteTask />
					</WizardTask>
				</WizardTaskList>
			</Wizard>
		</CardBody>
	</Card>
);

export default MethodsSelector;
