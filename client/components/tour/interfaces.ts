export interface TourCoordinates {
	x: number;
	y: number;
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

export type TourOptionPosition =
	| 'top'
	| 'left'
	| 'bottom'
	| 'right'
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
