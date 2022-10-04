/**
 * External dependencies
 */
import React, { useState } from 'react';
import { Button, Notice } from '@wordpress/components';

/**
 * Internal dependencies
 */
import strings from './strings';

const RegularOnboardingNotice: React.FunctionComponent = () => {
	const [ isSubmitted, setIsSubmitted ] = useState( false );

	return (
		<Notice isDismissible={ false }>
			<Button
				isPrimary
				isBusy={ isSubmitted }
				disabled={ isSubmitted }
				onClick={ () => setIsSubmitted( true ) }
				href={ wcpaySettings.connectUrl }
			>
				{ strings.connect }
			</Button>{ ' ' }
			{ strings.regularOnboardingNotice }
		</Notice>
	);
};

export default RegularOnboardingNotice;
