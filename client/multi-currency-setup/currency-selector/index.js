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
import MultiCurrencySettingsTask from './multi-currency-settings-task';
import SetupCompleteTask from './setup-complete-task';
import AddCurrenciesTask from './add-currencies-task';
import './index.scss';

const CurrenciesSelector = () => {
	return (
		<Card className="multi-currency-setup-wizard">
			<CardBody>
				<Wizard
					defaultActiveTask={ 'add-currencies' }
					defaultCompletedTasks={ 'add-currencies' }
				>
					<WizardTaskList>
						<WizardTask id="add-currencies">
							<AddCurrenciesTask />
						</WizardTask>
						<WizardTask id="multi-currency-settings">
							<MultiCurrencySettingsTask />
						</WizardTask>
						<WizardTask id="setup-complete">
							<SetupCompleteTask />
						</WizardTask>
					</WizardTaskList>
				</Wizard>
			</CardBody>
		</Card>
	);
};

export default CurrenciesSelector;
