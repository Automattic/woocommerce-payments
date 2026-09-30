/**
 * External dependencies
 */
import React from 'react';
import { Button } from '@wordpress/components';
import clsx from 'clsx';

/**
 * Internal dependencies
 */
import type { ReportFeedbackRating } from './tracks';
import {
	reportFeedbackRatingAriaLabel,
	thumbsDownAriaLabel,
	thumbsUpAriaLabel,
} from './strings';

interface ThumbsControlProps {
	controlsId?: string;
	disabled?: boolean;
	isExpanded?: boolean;
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
	controlsId,
	disabled = false,
	isExpanded = false,
	onSelect,
	selectedRating,
}: ThumbsControlProps ) => {
	return (
		<div
			className="wcpay-reports-feedback-survey__thumbs"
			role="group"
			aria-label={ reportFeedbackRatingAriaLabel }
		>
			{ options.map( ( { ariaLabel, icon, rating } ) => {
				const isSelected = rating === selectedRating;

				return (
					<Button
						key={ rating }
						aria-controls={ controlsId }
						aria-expanded={ isExpanded }
						aria-label={ ariaLabel }
						aria-pressed={ isSelected }
						className={ clsx(
							'wcpay-reports-feedback-survey__thumb',
							{
								'wcpay-reports-feedback-survey__thumb--selected':
									isSelected,
							}
						) }
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
