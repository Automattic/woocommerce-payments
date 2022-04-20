/** @format **/

/**
 * External dependencies
 */
import React, { useState } from 'react';
import Wizard from 'wcpay/additional-methods-setup/wizard/wrapper';
import WizardTask from 'wcpay/additional-methods-setup/wizard/task';
import WizardTaskList from 'wcpay/additional-methods-setup/wizard/task-list';

/**
 * Internal dependencies
 */
import { VatFormOnCompleted } from '../types';
import { CompanyDataTask } from './tasks/company-data-task';
import { VatNumberTask } from './tasks/vat-number-task';

const VatForm = ( {
	onCompleted,
}: {
	onCompleted: VatFormOnCompleted;
} ): JSX.Element => {
	const [ vatNumber, setVatNumber ] = useState< string | null >( null );
	const [ name, setName ] = useState< string >( '' );
	const [ address, setAddress ] = useState< string >( '' );

	const onVatNumberCompleted = (
		newVatNumber: string | null,
		companyName: string,
		companyAddress: string
	): void => {
		setVatNumber( newVatNumber );
		setName( companyName );
		setAddress( companyAddress );
	};

	const onCompanyDataCompleted = (
		companyVatNumber: string | null,
		companyName: string,
		companyAddress: string
	): void => {
		onCompleted( companyVatNumber, companyName, companyAddress );
	};

	return (
		<Wizard defaultActiveTask="vat-number">
			<WizardTaskList>
				<WizardTask id="vat-number">
					<VatNumberTask onCompleted={ onVatNumberCompleted } />
				</WizardTask>
				<WizardTask id="company-data">
					<CompanyDataTask
						onCompleted={ onCompanyDataCompleted }
						vatNumber={ vatNumber }
						placeholderCompanyName={ name }
						placeholderCompanyAddress={ address }
					/>
				</WizardTask>
			</WizardTaskList>
		</Wizard>
	);
};

export default VatForm;
