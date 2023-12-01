/**
 * External dependencies
 */
import { useMemo, useState } from 'react';

/**
 * Internal dependencies
 */
import WcPayUpeContext from './context';

const WcPayUpeContextProvider = ( { children } ) => {
	const [ status ] = useState( 'resolved' );

	const contextValue = useMemo(
		() => ( {
			status,
		} ),
		[ status ]
	);

	return (
		<WcPayUpeContext.Provider value={ contextValue }>
			{ children }
		</WcPayUpeContext.Provider>
	);
};

export default WcPayUpeContextProvider;
