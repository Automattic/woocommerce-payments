/**
 * External dependencies
 */
import { useContext } from 'react';

/**
 * Internal dependencies
 */
import WcPaySurveyContext from './context';

const useIsSurveyModalOpen = () => {
	const { useIsModalOpen, setIsModalOpen } = useContext( WcPaySurveyContext );

	return [ useIsModalOpen, setIsModalOpen ];
};

export default useIsSurveyModalOpen;
