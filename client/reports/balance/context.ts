/** @format */

/**
 * External dependencies
 */
import { createContext } from 'react';

// Public boundary for parent orchestrators that need to provide a shared
// "now" reference to the Balance date filter without reaching into the
// hook's implementation file.
export const BalanceDateFilterNowContext = createContext< Date | undefined >(
	undefined
);
