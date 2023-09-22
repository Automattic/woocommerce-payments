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
import { NAMESPACE, STORE_NAME } from '../../data/constants';
import { useEnabledPaymentMethodIds } from '../../data';

const WcPayUpeContextProvider = ( {
	children,
	defaultIsUpeEnabled,
	defaultUpeType,
} ) => {
	const [ isUpeEnabled, setIsUpeEnabled ] = useState(
		Boolean( defaultIsUpeEnabled )
	);
	const [ upeType, setUpeType ] = useState(
		defaultIsUpeEnabled ? defaultUpeType || 'legacy' : ''
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
					// new "toggles" will continue being "split" UPE

					if ( value ) {
						if ( upeType === 'legacy_after_deferred_intent' ) {
							setUpeType( 'deferred_upe_from_legacy_upe' );
							setIsUpeEnabled( true );
						} else if ( upeType === '' ) {
							setUpeType( 'deferred_upe_from_legacy_card' );
							setIsUpeEnabled( true );
						}
					}

					// down
					if ( ! value ) {
						if ( upeType === 'deferred_upe_from_legacy_card' ) {
							// fallback to card
							setUpeType( '' );
							setIsUpeEnabled( false );
							updateAvailablePaymentMethodIds( [ 'card' ] );
							setEnabledPaymentMethods( [ 'card' ] );
						}

						if ( upeType === 'deferred_upe_from_legacy_upe' ) {
							// fallback to upe
							setUpeType( 'legacy_after_deferred_intent' );
							setIsUpeEnabled( true );
						}
					}

					// setUpeType( value ? 'split' : '' );
					// setIsUpeEnabled( Boolean( value ) );

					// the backend already takes care of this,
					// we're just duplicating the effort
					// to ensure that the non-UPE payment methods are removed when the flag is disabled
					// if ( ! value ) {
					// 	updateAvailablePaymentMethodIds( [ 'card' ] );
					// 	setEnabledPaymentMethods( [ 'card' ] );
					// }
					setStatus( 'resolved' );
				} )
				.catch( () => {
					setStatus( 'error' );
				} );
		},
		[
			setStatus,
			setIsUpeEnabled,
			setUpeType,
			upeType,
			setEnabledPaymentMethods,
			updateAvailablePaymentMethodIds,
		]
	);

	const contextValue = useMemo(
		() => ( {
			isUpeEnabled,
			setIsUpeEnabled: updateFlag,
			status,
			upeType,
		} ),
		[ isUpeEnabled, updateFlag, status, upeType ]
	);

	return (
		<WcPayUpeContext.Provider value={ contextValue }>
			{ children }
		</WcPayUpeContext.Provider>
	);
};

export default WcPayUpeContextProvider;
