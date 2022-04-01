/** @format */

/**
 * External dependencies
 */
import { useDispatch, useSelect } from '@wordpress/data';
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
	query: RequiredVerificationInfoParams
): unknown => {
	const { requiredFields, isLoading } = useSelect( ( select ) => {
		const {
			getRequiredVerificationInfo,
			isResolving,
			hasFinishedResolution,
		} = select( STORE_NAME );

		return {
			requiredFields: getRequiredVerificationInfo( query ),
			isLoading:
				isResolving( 'getRequiredVerificationInfo', [] ) ||
				! hasFinishedResolution( 'getRequiredVerificationInfo', [] ),
		};
	}, [] );

	const { getRequiredVerificationInfo } = useDispatch( STORE_NAME );

	return {
		requiredFields,
		isLoading,
		getRequiredVerificationInfo,
	};
};
