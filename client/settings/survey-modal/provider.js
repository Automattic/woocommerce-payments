/**
 * External dependencies
 */
import { useCallback, useMemo, useState, useEffect } from 'react';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import WcPaySurveyContext from './context';
import { wcPaySurveys } from './questions';
import { NAMESPACE } from '../../data/constants';
import { formatSsr } from '../../utils/format-ssr';

const WcPaySurveyContextProvider = ( { children } ) => {
	const [ isSurveySubmitted, setSurveySubmitted ] = useState( false );
	const [ isLoadingSsr, setLoadingSsr ] = useState( false );
	const [ status, setStatus ] = useState( 'resolved' );
	// set a default answer since the survey form has a default value.
	const [ surveyAnswers, setSurveyAnswers ] = useState(
		wcPaySurveys[ 0 ].defaultAnswer
	);

	useEffect( () => {
		const fetchSystemReport = async () => {
			setLoadingSsr( true );
			let formattedSsr = '';
			try {
				const [ systemStatus, wcPayData ] = await Promise.all(
					[
						'/wc/v3/system_status',
						'/wc/v3/payments/accounts',
					].map( ( url ) => apiFetch( { path: url } ) )
				);
				formattedSsr = formatSsr( systemStatus, wcPayData );
				setSurveyAnswers( ( prev ) => ( {
					...prev,
					ssr: formattedSsr,
				} ) );
			} catch ( error ) {
				setSurveyAnswers( ( prev ) => ( {
					...prev,
					ssr: __(
						'Can not load System Status Report',
						'woocommerce-payments'
					),
				} ) );
			}
			setLoadingSsr( false );
		};
		fetchSystemReport();
	}, [] );

	const submitSurvey = useCallback( () => {
		setStatus( 'pending' );
		// map answers data to fit to API, where key string is the question and comments is just a string.

		return apiFetch( {
			path: `${ NAMESPACE }/upe_survey`,
			method: 'POST',
			data: surveyAnswers,
		} )
			.then( () => {
				setSurveySubmitted( true );
				setStatus( 'resolved' );
			} )
			.catch( () => {
				setStatus( 'error' );
				setSurveySubmitted( false );
			} );
	}, [ setStatus, setSurveySubmitted, surveyAnswers ] );

	const contextValue = useMemo(
		() => ( {
			submitSurvey,
			status,
			isSurveySubmitted,
			isLoadingSsr,
			surveyAnswers,
			setSurveyAnswers,
		} ),
		[
			submitSurvey,
			status,
			isSurveySubmitted,
			isLoadingSsr,
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
