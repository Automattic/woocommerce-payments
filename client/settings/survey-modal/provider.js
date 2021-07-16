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
	const [ isModalOpen, setIsModalOpen ] = useState( Boolean( false ) );
	const [ isSurveySubmitted, setSurveySubmitted ] = useState(
		Boolean( false )
	);
	const [ status, setStatus ] = useState( 'resolved' );

	const submitSurvey = useCallback(
		( value ) => {
			setStatus( 'pending' );

			return apiFetch( {
				path: `${ NAMESPACE }/upe_survey`,
				method: 'POST',
				// eslint-disable-next-line camelcase
				data: { value },
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
			isModalOpen,
			setIsModalOpen,
			setSurveySubmitted: submitSurvey,
			status,
			isSurveySubmitted,
		} ),
		[ isModalOpen, submitSurvey, status, isSurveySubmitted ]
	);

	return (
		<WcPaySurveyContext.Provider value={ contextValue }>
			{ children }
		</WcPaySurveyContext.Provider>
	);
};

export default WcPaySurveyContextProvider;
