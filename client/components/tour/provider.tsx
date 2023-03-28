/**
 * External dependencies
 */
import React, {
	useCallback,
	useMemo,
	useState,
	useEffect,
	useRef,
} from 'react';

/**
 * Internal dependencies
 */
import WcPayTourContext from './context';
import { TourProps } from './interfaces';

const WcPayTourContextProvider: React.FC< TourProps > = ( {
	children,
	onTourEnd,
	onTourStart,
	onCloseButtonClick,
} ) => {
	const scrollRestoration = useRef< ScrollRestoration | null >( null );

	const [ steps, setSteps ] = useState< string[] >( [] );
	const [ currentIndex, setCurrentIndex ] = useState( 0 );

	const currentStep = useMemo( () => steps[ currentIndex ], [
		currentIndex,
		steps,
	] );

	const registerStep = useCallback( ( selector: string ) => {
		const randomId = ( Math.random() + 1 ).toString( 36 ).substring( 7 );
		const stepId = `${ selector }-${ randomId }`;

		setSteps( ( prev ) => [ ...prev, stepId ] );

		return stepId;
	}, [] );

	useEffect( () => {
		onTourStart?.();
	}, [ onTourStart ] );

	useEffect( () => {
		document.body.classList.add( 'modal-open' );
		document.documentElement.classList.add( 'modal-smooth-scroll' );

		// Disables automatic scroll restoration
		if ( history.scrollRestoration ) {
			scrollRestoration.current = history.scrollRestoration;
			history.scrollRestoration = 'manual';
		}

		return () => {
			document.body.classList.remove( 'modal-open' );
			document.documentElement.classList.remove( 'modal-smooth-scroll' );

			if ( scrollRestoration.current ) {
				history.scrollRestoration = scrollRestoration.current;
			}
		};
	}, [] );

	const handleNextStepButtonClick = useCallback( () => {
		if ( currentIndex >= steps.length - 1 ) {
			onTourEnd();
			return;
		}

		setCurrentIndex( ( prev ) => prev + 1 );
	}, [ currentIndex, onTourEnd, steps.length ] );

	const handlePreviousStepButtonClick = useCallback( () => {
		if ( currentIndex <= 0 ) {
			return;
		}

		setCurrentIndex( ( prev ) => prev - 1 );
	}, [ currentIndex ] );

	const contextValue = useMemo(
		() => ( {
			steps,
			currentStep,
			currentIndex,
			scrollRestoration,
			onTourEnd,
			registerStep,
			onCloseButtonClick,
			onNextStepButtonClick: handleNextStepButtonClick,
			onPreviousStepButtonClick: handlePreviousStepButtonClick,
		} ),
		[
			steps,
			currentStep,
			currentIndex,
			onTourEnd,
			registerStep,
			onCloseButtonClick,
			handleNextStepButtonClick,
			handlePreviousStepButtonClick,
		]
	);

	return (
		<WcPayTourContext.Provider value={ contextValue }>
			{ children }
		</WcPayTourContext.Provider>
	);
};

export default WcPayTourContextProvider;
