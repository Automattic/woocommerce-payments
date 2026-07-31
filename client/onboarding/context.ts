/**
 * External dependencies
 */
import { createContext } from 'react';

/**
 * Internal dependencies
 */
import { OnboardingContextValue } from './types';

export const OnboardingContext = createContext< OnboardingContextValue | null >(
	null
);
