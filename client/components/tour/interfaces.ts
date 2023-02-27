export interface TourCoordinates {
	x: number;
	y: number;
	arrow?: TourOptionAbsolutePosition;
	scrollPadding?: [ number, number ];
}

interface TourOptionContentImage {
	src: string;
	mobileOnly?: boolean;
}

interface TourOptionContentButton {
	text: string;
}

interface TourOptionContent {
	title: string;
	description: string;
	image?: TourOptionContentImage;
	counter?: boolean;
	actionButton?: TourOptionContentButton;
	previousButton?: TourOptionContentButton;
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

interface TourOption {
	selector: string;
	position: TourOptionPosition;
	content: TourOptionContent;
}

export interface TourProps {
	options: TourOption[];
	onTourEnd: () => void;
}
