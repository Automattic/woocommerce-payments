/**
 * External dependencies
 */
import { createContext } from 'react';

const WcPaySurveyContext = createContext( {
	isSurveySubmitted: false,
	isLoadingSsr: false,
	submitSurvey: () => Promise.resolve(),
	status: 'resolved',
	surveyAnswers: {},
	setSurveyAnswers: () => null,
} );

export default WcPaySurveyContext;
