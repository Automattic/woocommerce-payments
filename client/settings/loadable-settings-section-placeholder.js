/** @format */
/**
 * External dependencies
 */
import React from 'react';

/**
 * Internal dependencies
 */
import { useSettings } from '../data';
import { LoadableBlock } from '../components/loadable';

const LoadableSettingsSectionPlaceholder = ( { children, numLines } ) => {
	const { isLoading } = useSettings();

	return (
		<LoadableBlock isLoading={ isLoading } numLines={ numLines }>
			{ children }
		</LoadableBlock>
	);
};

export default LoadableSettingsSectionPlaceholder;
