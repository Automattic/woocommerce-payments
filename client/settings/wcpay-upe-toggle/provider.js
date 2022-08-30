/**
 * External dependencies
 */
import { useCallback, useMemo, useState } from 'react';
import apiFetch from '@wordpress/api-fetch';
import { useDispatch } from '@wordpress/data';

/**
 * Internal dependencies
 */
import WcPayUpeContext from './context';
import wcpayTracks from '../../tracks';
import { NAMESPACE, STORE_NAME } from '../../data/constants';
import { useEnabledPaymentMethodIds } from '../../data';

const WcPayUpeContextProvider = ( { children, defaultIsUpeEnabled } ) => {
	const [ isUpeEnabled, setIsUpeEnabled ] = useState(
		Boolean( defaultIsUpeEnabled )
	);
	const [ status, setStatus ] = useState( 'resolved' );
	const [ , setEnabledPaymentMethods ] = useEnabledPaymentMethodIds();
	const { updateAvailablePaymentMethodIds } = useDispatch( STORE_NAME );

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

					// Track enabling/disabling UPE.
					const event = Boolean( value )
						? wcpayTracks.events.UPE_ENABLED
						: wcpayTracks.events.UPE_DISABLED;
					wcpayTracks.recordEvent( event );

					// the backend already takes care of this,
					// we're just duplicating the effort
					// to ensure that the non-UPE payment methods are removed when the flag is disabled
					if ( ! value ) {
						updateAvailablePaymentMethodIds( [ 'card' ] );
						setEnabledPaymentMethods( [ 'card' ] );
					}
					setStatus( 'resolved' );
				} )
				.catch( () => {
					setStatus( 'error' );
				} );
		},
		[
			setStatus,
			setIsUpeEnabled,
			setEnabledPaymentMethods,
			updateAvailablePaymentMethodIds,
		]
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
