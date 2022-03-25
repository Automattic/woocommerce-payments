/** @format */

/**
 * External dependencies
 */
import { useSelect } from '@wordpress/data';
import { STORE_NAME } from '../constants';

export const useBusinessTypes = (): unknown =>
	useSelect( ( select ) => {
		const { getBusinessTypes, isResolving, hasFinishedResolution } = select(
			STORE_NAME
		);

		return {
			businessTypes: getBusinessTypes(),
			isLoading:
				isResolving( 'getBusinessTypes' ) ||
				! hasFinishedResolution( 'getBusinessTypes' ),
		};
	}, [] );
