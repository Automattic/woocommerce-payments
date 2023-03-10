/**
 * External dependencies
 */
import { createContext } from 'react';

/**
 * Internal dependencies
 */
import { WcPayTourContextProps } from './interfaces';

const WcPayTourContext = createContext< WcPayTourContextProps >( {
	steps: [],
	currentStep: '',
	currentIndex: 0,
	registerStep: () => '',
	onTourEnd: () => null,
	onNextStepButtonClick: () => null,
	onPreviousStepButtonClick: () => null,
} );

export default WcPayTourContext;
