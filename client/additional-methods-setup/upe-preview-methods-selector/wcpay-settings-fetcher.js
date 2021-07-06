/**
 * External dependencies
 */
import React, { useContext } from 'react';

/**
 * Internal dependencies
 */
import WizardTaskContext from '../wizard/task/context';
import { useSettings } from '../../data';

const WcpaySettingsFetcher = () => {
	useSettings();

	return null;
};

const WcpaySettingsFetcherWrapper = () => {
	const { isCompleted } = useContext( WizardTaskContext );

	// fetch the settings only _after_ UPE is enabled, to ensure that the payment methods are correctly fetched
	return isCompleted ? <WcpaySettingsFetcher /> : null;
};

export default WcpaySettingsFetcherWrapper;
