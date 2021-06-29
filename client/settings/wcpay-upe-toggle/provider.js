/**
 * External dependencies
 */
import { useCallback, useMemo, useState } from 'react';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import WcPayUpeContext from './context';
import { NAMESPACE } from '../../data/constants';

const WcPayUpeContextProvider = ( { children, defaultIsUpeEnabled } ) => {
	const [ isUpeEnabled, setIsUpeEnabled ] = useState(
		Boolean( defaultIsUpeEnabled )
	);
	const [ status, setStatus ] = useState( 'resolved' );

	const updateFlag = useCallback(
		( value ) => {
			setStatus( 'pending' );

			return apiFetch( {
				path: `${ NAMESPACE }/upe_flag_toggle`,
				method: 'POST',
				// eslint-disable-next-line camelcase
				data: { is_upe_enabled: Boolean( value ) },
			} )
				.then( () => {
					setIsUpeEnabled( Boolean( value ) );
					setStatus( 'resolved' );
				} )
				.catch( () => {
					setStatus( 'error' );
				} );
		},
		[ setStatus, setIsUpeEnabled ]
	);

	const contextValue = useMemo(
		() => ( { isUpeEnabled, setIsUpeEnabled: updateFlag, status } ),
		[ isUpeEnabled, updateFlag, status ]
	);

	return (
		<WcPayUpeContext.Provider value={ contextValue }>
			{ children }
		</WcPayUpeContext.Provider>
	);
};

export default WcPayUpeContextProvider;
