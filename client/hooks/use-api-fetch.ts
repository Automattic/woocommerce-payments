/**
 * External dependencies
 */
import { createContext, useContext, useEffect, useState } from 'react';
import { dispatch } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';

const cacheContext = createContext< { [ key: string ]: any } >( {} );

interface UseApiFetchParams {
	path: string;
	errorMessage?: string;
}

interface ApiResponse< T > {
	data: T;
}

export function useApiFetch< T >( {
	path,
	errorMessage,
}: UseApiFetchParams ): {
	data?: T;
	isLoading: boolean;
} {
	const cache = useContext( cacheContext );
	const [ data, setData ] = useState< T >();
	const [ isLoading, setLoading ] = useState( false );

	useEffect( () => {
		const fetch = async () => {
			try {
				setLoading( true );
				if ( cache[ path ] ) {
					setData( cache[ path ] );
				} else {
					const result = await apiFetch< ApiResponse< T > >( {
						path,
					} );
					cache[ path ] = result.data;
					setData( result.data );
				}
			} catch ( error ) {
				if ( errorMessage )
					dispatch( 'core/notices' ).createErrorNotice(
						errorMessage
					);
			} finally {
				setLoading( false );
			}
		};
		fetch();
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ path, errorMessage ] );

	return { data, isLoading };
}
