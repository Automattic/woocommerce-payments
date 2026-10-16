/** @format */
/**
 * External dependencies
 */
import React from 'react';

/**
 * Internal dependencies
 */
import { useSettings } from 'wcpay/data/settings';
import { LoadableBlock } from '../components/loadable';

const LoadableSettingsSection = ( { children, numLines } ) => {
	const { isLoading } = useSettings();

	return (
		<LoadableBlock isLoading={ isLoading } numLines={ numLines }>
			{ children }
		</LoadableBlock>
	);
};

export default LoadableSettingsSection;
