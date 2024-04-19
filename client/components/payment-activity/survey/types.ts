/**
 * Internal dependencies
 */

export type Rating =
	| 'very-unhappy'
	| 'unhappy'
	| 'neutral'
	| 'happy'
	| 'very-happy';

export type OverviewSurveyFields = {
	rating?: Rating;
	comments?: string;
};
