/**
 * External dependencies
 */
import { createContext } from 'react';

const WcPaySurveyContext = createContext( {
	isModalOpen: false,
	modalPage: 1,
	setIsModalOpen: () => null,
	submitSurvey: () => null,
	submitStatus: 'resolved',
} );

export default WcPaySurveyContext;
