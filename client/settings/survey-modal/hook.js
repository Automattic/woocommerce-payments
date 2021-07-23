/**
 * External dependencies
 */
import { useContext } from 'react';

/**
 * Internal dependencies
 */
import WcPaySurveyContext from './context';

export const useSurveySubmit = () => {
	const { isSurveySubmitted, setSurveySubmitted } = useContext(
		WcPaySurveyContext
	);
	return [ isSurveySubmitted, setSurveySubmitted ];
};

export const useSurveyAnswers = () => {
	const { surveyAnswers, setSurveyAnswers } = useContext(
		WcPaySurveyContext
	);
	return [ surveyAnswers, setSurveyAnswers ];
};
