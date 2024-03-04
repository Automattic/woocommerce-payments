/**
 * External dependencies
 */
import React, { createContext, useState, useCallback, useContext } from 'react';
import { OverviewSurveyFields } from 'wcpay/overview/survey/types';
import apiFetch from '@wordpress/api-fetch';
import { NAMESPACE } from 'wcpay/data/constants';

const useContextValue = ( initialState = {} as OverviewSurveyFields ) => {
	const [ surveySubmitted, setSurveySubmitted ] = useState(
		Boolean( false )
	);
	const [ status, setStatus ] = useState( 'resolved' );
	const [ surveyAnswers, setSurveyAnswers ] = useState( initialState );

	const submitSurvey = useCallback(
		async ( answers: OverviewSurveyFields ) => {
			setStatus( 'pending' );
			try {
				await apiFetch( {
					path: `${ NAMESPACE }/upe_survey/payments-overview`,
					method: 'POST',
					data: answers,
				} );
				setSurveySubmitted( true );
				setStatus( 'resolved' );
			} catch ( e ) {
				setStatus( 'error' );
				setSurveySubmitted( false );
			}
		},
		[ setStatus, setSurveySubmitted ]
	);

	return {
		setSurveySubmitted: submitSurvey,
		status,
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
		throw new Error(
			'useOverviewSurveyContext() must be used within <WcPayOverviewSurveyContextProvider>'
		);
	}
	return context;
};

export default WcPayOverviewSurveyContext;
