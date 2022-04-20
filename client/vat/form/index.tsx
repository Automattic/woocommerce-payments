/** @format **/

/**
 * External dependencies
 */
import React, { useState } from 'react';
import WizardTask from 'wcpay/additional-methods-setup/wizard/task';
import WizardTaskList from 'wcpay/additional-methods-setup/wizard/task-list';
import Wizard from 'wcpay/additional-methods-setup/wizard/wrapper';

/**
 * Internal dependencies
 */
import { VatNumberTask } from './tasks/vat-number-task';

const VatForm = (): JSX.Element => {
	/* eslint-disable @typescript-eslint/no-unused-vars */
	const [ vatNumber, setVatNumber ] = useState< string | null >( null );
	const [ name, setName ] = useState< string >( '' );
	const [ address, setAddress ] = useState< string >( '' );
	/* eslint-enable @typescript-eslint/no-unused-vars */

	const onVatNumberCompleted = (
		newVatNumber: string | null,
		companyName: string,
		companyAddress: string
	): void => {
		setVatNumber( newVatNumber );
		setName( companyName );
		setAddress( companyAddress );
	};

	return (
		<Wizard defaultActiveTask="vat-number">
			<WizardTaskList>
				<WizardTask id="vat-number">
					<VatNumberTask onCompleted={ onVatNumberCompleted } />
				</WizardTask>
			</WizardTaskList>
		</Wizard>
	);
};

export default VatForm;
