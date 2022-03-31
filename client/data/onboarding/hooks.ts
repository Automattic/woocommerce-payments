/** @format */

/**
 * External dependencies
 */
import { useSelect } from '@wordpress/data';
import { STORE_NAME } from '../constants';
import { RequiredVerificationInfoParams } from './types';

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

export const useRequiredVerificationInfo = (
	country: string,
	type: string,
	structure: string
): unknown =>
	useSelect( ( select ) => {
		const { getRequiredVerificationInfo, isResolving } = select(
			STORE_NAME
		);

		const query: RequiredVerificationInfoParams = {
			country: country,
			type: type,
		};

		if ( structure.length > 0 ) {
			query.structure = structure;
		}

		return {
			requiredFields: getRequiredVerificationInfo( query ),
			isLoading: isResolving( 'getRequiredVerificationInfo', query ),
		};
	}, [] );
