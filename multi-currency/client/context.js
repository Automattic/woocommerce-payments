/** @format */

/**
 * External dependencies
 */
import { createContext } from 'react';

const MultiCurrencySettingsContext = createContext( {
	isDirty: false,
	setisDirty: () => null,
} );

export default MultiCurrencySettingsContext;
