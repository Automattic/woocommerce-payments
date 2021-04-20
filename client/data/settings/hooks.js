/** @format */

/**
 * External dependencies
 */
import { useSelect, useDispatch } from '@wordpress/data';
import { STORE_NAME } from '../constants';

export const useSettings = () => {
	const { settings, isLoading } = useSelect( ( select ) => {
		const { getSettings, isResolving } = select( STORE_NAME );

		return {
			settings: getSettings(),
			isLoading: isResolving( 'getSettings' ),
		};
	} );

	const { updateSettings } = useDispatch( STORE_NAME );

	return { settings, isLoading, updateSettings };
};
