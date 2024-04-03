/**
 * External dependencies
 */
import React, { createContext, useState, useCallback, useContext } from 'react';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import { NAMESPACE } from 'data/constants';
import type { OverviewSurveyFields } from './types';

const useContextValue = ( initialState = {} as OverviewSurveyFields ) => {
	const [ surveySubmitted, setSurveySubmitted ] = useState(
		Boolean( false )
	);
	const [ responseStatus, setResponseStatus ] = useState<
		'pending' | 'resolved' | 'error'
	>( 'resolved' );
	const [ surveyAnswers, setSurveyAnswers ] = useState( initialState );

	const submitSurvey = useCallback(
		async ( answers: OverviewSurveyFields ) => {
			setResponseStatus( 'pending' );
			try {
				await apiFetch( {
					path: `${ NAMESPACE }/upe_survey/payments-overview`,
					method: 'POST',
					data: answers,
				} );
				setSurveySubmitted( true );
				setResponseStatus( 'resolved' );
			} catch ( e ) {
				setResponseStatus( 'error' );
				setSurveySubmitted( false );
			}
		},
		[ setResponseStatus, setSurveySubmitted ]
	);

	return {
		setSurveySubmitted: submitSurvey,
		responseStatus,
		surveySubmitted,
		surveyAnswers,
		setSurveyAnswers,
	};
};

type ContextValue = ReturnType< typeof useContextValue >;

const WcPayOverviewSurveyContext = createContext< ContextValue | null >( null );

export const WcPayOverviewSurveyContextProvider: React.FC< {
	initialData?: OverviewSurveyFields;
} > = ( { children, initialData } ) => {
	return (
		<WcPayOverviewSurveyContext.Provider
			value={ useContextValue( initialData ) }
		>
			{ children }
		</WcPayOverviewSurveyContext.Provider>
	);
};

export const useOverviewSurveyContext = (): ContextValue => {
	const context = useContext( WcPayOverviewSurveyContext );
	if ( ! context ) {
		throw new Error( 'An error occured when using survey context' );
	}
	return context;
};

export default WcPayOverviewSurveyContext;
