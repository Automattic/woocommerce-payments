/**
 * External dependencies
 */
import React from 'react';

/**
 * Internal dependencies
 */
import { WordPressComponentsContext } from './context';

const UnbundledWpComponentsProvider = ( { children } ) => (
	<WordPressComponentsContext.Provider value={ wp.components }>
		{ children }
	</WordPressComponentsContext.Provider>
);

export default UnbundledWpComponentsProvider;
