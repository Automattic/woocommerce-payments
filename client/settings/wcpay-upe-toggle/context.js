/**
 * External dependencies
 */
import { createContext } from 'react';

const WcPayUpeContext = createContext( {
	isUpeEnabled: false,
	setIsUpeEnabled: () => null,
	status: 'resolved',
} );

export default WcPayUpeContext;
