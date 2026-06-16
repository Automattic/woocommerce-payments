/**
 * External dependencies
 */
import React from 'react';
import { Button } from '@wordpress/components';

/**
 * Internal dependencies
 */
import type { ReportFeedbackRating } from './tracks';
import { thumbsDownAriaLabel, thumbsUpAriaLabel } from './strings';

interface ThumbsControlProps {
	disabled?: boolean;
	onSelect: ( rating: ReportFeedbackRating ) => void;
	selectedRating: ReportFeedbackRating | null;
}

const options: Array< {
	ariaLabel: string;
	icon: string;
	rating: ReportFeedbackRating;
} > = [
	{
		ariaLabel: thumbsUpAriaLabel,
		icon: '👍',
		rating: 'thumbs-up',
	},
	{
		ariaLabel: thumbsDownAriaLabel,
		icon: '👎',
		rating: 'thumbs-down',
	},
];

export const ThumbsControl = ( {
	disabled = false,
	onSelect,
	selectedRating,
}: ThumbsControlProps ) => {
	return (
		<div
			className="wcpay-reports-feedback-survey__thumbs"
			role="group"
			aria-label={ thumbsUpAriaLabel + ' / ' + thumbsDownAriaLabel }
		>
			{ options.map( ( { ariaLabel, icon, rating } ) => {
				const isSelected = rating === selectedRating;
				const className = [
					'wcpay-reports-feedback-survey__thumb',
					isSelected
						? 'wcpay-reports-feedback-survey__thumb--selected'
						: '',
				]
					.filter( Boolean )
					.join( ' ' );

				return (
					<Button
						key={ rating }
						aria-label={ ariaLabel }
						aria-pressed={ isSelected }
						className={ className }
						disabled={ disabled }
						onClick={ () => onSelect( rating ) }
					>
						<span aria-hidden="true">{ icon }</span>
					</Button>
				);
			} ) }
		</div>
	);
};
