export interface TourCoordinates {
	x: number;
	y: number;
	arrow?: TourOptionAbsolutePosition;
	sticky?: boolean; // Emulates the mobile behavior
	scrollPadding?: [ number, number ];
}

export interface TourOptionRelativePosition {
	top?: number;
	left?: number;
	right?: number;
	bottom?: number;
}

export type TourOptionAbsolutePosition = 'top' | 'left' | 'bottom' | 'right';

export type TourOptionPosition =
	| TourOptionAbsolutePosition
	| TourOptionRelativePosition;

export interface TourOption {
	selector: string;
	position: TourOptionPosition;
	content: React.ReactNode;
}

export interface TourProps {
	onTourEnd: () => void;
	onTourStart?: () => void;
	onCloseButtonClick?: () => void;
}

export interface WcPayTourContextProps extends TourProps {
	steps: string[];
	currentStep: string;
	currentIndex: number;
	registerStep: ( selector: string ) => string;
	onNextStepButtonClick: () => void;
	onPreviousStepButtonClick: () => void;
	scrollRestoration?: React.MutableRefObject< ScrollRestoration | null >;
}
