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
import EnableUpePreviewTask from './enable-upe-preview-task';
import SetupCompleteTask from './setup-complete-task';
import './index.scss';
import useIsUpeEnabled from '../../settings/wcpay-upe-toggle/hook';

const UpePreviewMethodsSelector = () => {
	const [ isUpeEnabled ] = useIsUpeEnabled();

	return (
		<Card className="upe-preview-methods-selector">
			<CardBody>
				<Wizard
					defaultActiveTask={
						isUpeEnabled ? 'setup-complete' : 'enable-upe-preview'
					}
					defaultCompletedTasks={ {
						'enable-upe-preview': isUpeEnabled,
					} }
				>
					<WizardTaskList>
						<WizardTask id="enable-upe-preview">
							<EnableUpePreviewTask />
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

export default UpePreviewMethodsSelector;
