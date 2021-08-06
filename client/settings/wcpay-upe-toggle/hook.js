/**
 * External dependencies
 */
import { useContext } from 'react';

/**
 * Internal dependencies
 */
import WcPayUpeContext from './context';

const useIsUpeEnabled = () => {
	const { isUpeEnabled, setIsUpeEnabled } = useContext( WcPayUpeContext );

	return [ isUpeEnabled, setIsUpeEnabled ];
};

export default useIsUpeEnabled;
