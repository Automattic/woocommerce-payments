/**
 * External dependencies
 */
import React from 'react';
import { Card, CardBody } from '@wordpress/components';

/**
 * Internal dependencies
 */
import Wizard from '../../additional-methods-setup/wizard/wrapper';
import WizardTask from '../../additional-methods-setup/wizard/task';
import WizardTaskList from '../../additional-methods-setup/wizard/task-list';
import StoreSettingsTask from './store-settings-task';
import SetupCompleteTask from './setup-complete-task';
import AddCurrenciesTask from './add-currencies-task';
import './multi-currency-setup.scss';

const MultiCurrencySetup = () => {
	return (
		<Card className="multi-currency-setup-wizard">
			<CardBody>
				<Wizard defaultActiveTask="add-currencies">
					<WizardTaskList>
						<WizardTask id="add-currencies">
							<AddCurrenciesTask />
						</WizardTask>
						<WizardTask id="multi-currency-settings">
							<StoreSettingsTask />
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

export default MultiCurrencySetup;
