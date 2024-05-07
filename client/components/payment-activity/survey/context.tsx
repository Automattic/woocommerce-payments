/**
 * External dependencies
 */
import React, { createContext, useState, useCallback, useContext } from 'react';
import apiFetch from '@wordpress/api-fetch';
import { useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { NAMESPACE } from 'data/constants';
import type { OverviewSurveyFields } from './types';

type ResponseStatus = 'pending' | 'resolved' | 'error';

const useContextValue = ( initialState: OverviewSurveyFields = {} ) => {
	const [ surveySubmitted, setSurveySubmitted ] = useState( false );
	const [ responseStatus, setResponseStatus ] = useState< ResponseStatus >(
		'resolved'
	);
	const [ surveyAnswers, setSurveyAnswers ] = useState( initialState );

	const { createErrorNotice } = useDispatch( 'core/notices' );

	const submitSurvey = useCallback(
		async ( answers: OverviewSurveyFields ) => {
			setResponseStatus( 'pending' );
			try {
				await apiFetch( {
					path: `${ NAMESPACE }/survey/payments-overview`,
					method: 'POST',
					data: answers,
				} );
				setSurveySubmitted( true );
				setResponseStatus( 'resolved' );
			} catch ( e ) {
				setResponseStatus( 'error' );
				setSurveySubmitted( false );
				createErrorNotice(
					__(
						'An error occurred while submitting the survey. Please try again.',
						'woocommerce-payments'
					)
				);
			}
		},
		[ setResponseStatus, setSurveySubmitted, createErrorNotice ]
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
		throw new Error( 'An error occurred when using survey context' );
	}
	return context;
};

export default WcPayOverviewSurveyContext;
