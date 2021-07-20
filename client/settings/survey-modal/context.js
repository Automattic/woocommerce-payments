/**
 * External dependencies
 */
import { createContext } from 'react';

const WcPaySurveyContext = createContext( {
	isSurveySubmitted: false,
	setSurveySubmitted: () => null,
	status: 'resolved',
	surveyAnswers: {},
	setSurveyAnswers: () => null,
} );

export default WcPaySurveyContext;
