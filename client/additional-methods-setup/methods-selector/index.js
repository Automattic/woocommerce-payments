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
import ParentList from '../wizard/parent-list';
import AddPaymentMethodsTask from './add-payment-methods-task';
import SetupCompleteTask from './setup-complete-task';
import './index.scss';

const MethodsSelector = () => (
	<Card className="methods-selector">
		<CardBody>
			<Wizard defaultActiveTask="add-payment-methods">
				<ParentList>
					<WizardTask id="add-payment-methods">
						<AddPaymentMethodsTask />
					</WizardTask>
					<WizardTask id="setup-complete">
						<SetupCompleteTask />
					</WizardTask>
				</ParentList>
			</Wizard>
		</CardBody>
	</Card>
);

export default MethodsSelector;
