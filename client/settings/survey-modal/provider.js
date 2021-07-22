/**
 * External dependencies
 */
import { useCallback, useMemo, useState } from 'react';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import WcPaySurveyContext from './context';
import { NAMESPACE } from '../../data/constants';

// @todo - prepare data from form and pass to data in POST method.

const WcPaySurveyContextProvider = ( { children } ) => {
	const [ isSurveySubmitted, setSurveySubmitted ] = useState(
		Boolean( false )
	);
	const [ status, setStatus ] = useState( 'resolved' );
	const [ surveyAnswers, setSurveyAnswers ] = useState( {} );

	const submitSurvey = useCallback(
		( value ) => {
			setStatus( 'pending' );
			// map answers data to fit to API, where key string is the question and comments is just a string.

			return apiFetch( {
				path: `${ NAMESPACE }/upe_survey`,
				method: 'POST',
				// eslint-disable-next-line camelcase
				data: value,
			} )
				.then( () => {
					setSurveySubmitted( Boolean( true ) );
					setStatus( 'resolved' );
				} )
				.catch( () => {
					setStatus( 'error' );
					setSurveySubmitted( Boolean( false ) );
				} );
		},
		[ setStatus, setSurveySubmitted ]
	);

	const contextValue = useMemo(
		() => ( {
			setSurveySubmitted: submitSurvey,
			status,
			isSurveySubmitted,
			surveyAnswers,
			setSurveyAnswers,
		} ),
		[
			submitSurvey,
			status,
			isSurveySubmitted,
			surveyAnswers,
			setSurveyAnswers,
		]
	);

	return (
		<WcPaySurveyContext.Provider value={ contextValue }>
			{ children }
		</WcPaySurveyContext.Provider>
	);
};

export default WcPaySurveyContextProvider;
