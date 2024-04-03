/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { HorizontalRule } from '@wordpress/primitives';
import { Button, TextareaControl, Icon } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { createInterpolateElement, useState } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import type { OverviewSurveyFields, Rating } from './types';
import { useOverviewSurveyContext } from './context';
import Emoticon from './emoticon';
import close from './icons/close';
import './style.scss';

const Survey: React.FC = () => {
	const { responseStatus } = useOverviewSurveyContext();
	const {
		setSurveySubmitted,
		surveySubmitted,
		surveyAnswers,
		setSurveyAnswers,
	} = useOverviewSurveyContext();

	const [ showComponent, setShowComponent ] = useState( true );

	const currentRating = surveyAnswers.rating;
	const ratingWithComment: Rating[] = [
		'very-unhappy',
		'unhappy',
		'neutral',
	];
	const ratings: Rating[] = [
		'very-unhappy',
		'unhappy',
		'neutral',
		'happy',
		'very-happy',
	];
	const showComment =
		currentRating && ratingWithComment.includes( currentRating );
	const disableForm = 'pending' === responseStatus;

	const setReviewRating = function ( value?: Rating ) {
		const answers: OverviewSurveyFields = {
			...surveyAnswers,
			rating: value,
		};
		setSurveyAnswers( answers );
		if ( value && ! ratingWithComment.includes( value ) ) {
			setSurveySubmitted( answers );
		}
	};

	if ( ! showComponent ) {
		return null;
	}

	return (
		<div className="wcpay-payments-activity__survey">
			{ ! surveySubmitted && (
				<>
					<div className="survey_container">
						{ __(
							'How do you like your new payment activity dashboard?',
							'woocommerce-payments'
						) }

						<div className="survey_container__emoticons">
							{ ratings.map( ( rating ) => {
								return (
									<Emoticon
										key={ rating }
										disabled={ disableForm }
										rating={ rating }
										setReviewRating={ setReviewRating }
										currentRating={ currentRating }
									/>
								);
							} ) }
						</div>
					</div>

					<div className="close_container">
						{ showComment && (
							<button
								type="button"
								className="components-button has-icon"
								aria-label="Close dialog"
								onClick={ () => {
									setReviewRating( undefined );
								} }
							>
								<Icon icon={ close } type="close" size={ 32 } />
							</button>
						) }
					</div>
				</>
			) }

			{ ! surveySubmitted && showComment && (
				<>
					<HorizontalRule />
					<div className="comment_container">
						<TextareaControl
							label={ __(
								'Why do you feel that way? (optional)',
								'woocommerce-payments'
							) }
							onChange={ ( text ) => {
								setSurveyAnswers(
									(
										prev: OverviewSurveyFields
									): OverviewSurveyFields => ( {
										...prev,
										comments: text,
									} )
								);
							} }
							value={ surveyAnswers.comments ?? '' }
							readOnly={ disableForm }
						/>
						<p className="comment_container__disclaimer">
							{ createInterpolateElement(
								__(
									'Your feedback will be only be shared with WooCommerce and treated pursuant to our <a>privacy policy</a>.',
									'woocommerce-payments'
								),
								{
									a: (
										// eslint-disable-next-line jsx-a11y/anchor-has-content
										<a
											href="https://automattic.com/privacy/"
											target="_blank"
											rel="noreferrer"
										/>
									),
								}
							) }
						</p>
					</div>

					<div className="wcpay-confirmation-modal__footer">
						<Button
							variant={ 'tertiary' }
							disabled={ disableForm }
							onClick={ () => {
								setReviewRating( undefined );
							} }
						>
							{ __( 'Cancel', 'woocommerce-payments' ) }
						</Button>
						<Button
							variant={ 'primary' }
							isBusy={ disableForm }
							disabled={ disableForm }
							onClick={ () =>
								setSurveySubmitted( surveyAnswers )
							}
						>
							{ __( 'Send', 'woocommerce-payments' ) }
						</Button>
					</div>
				</>
			) }
			{ surveySubmitted && (
				<>
					<div className="survey_container">
						<span>
							<span role="img" aria-label="Thank you!">
								🙌
							</span>
							{ __(
								'We appreciate your feedback!',
								'woocommerce-payments'
							) }
						</span>
					</div>

					<div className="close_survey">
						<button
							type="button"
							className="components-button has-icon"
							aria-label="Close dialog"
							onClick={ () => {
								setShowComponent( false );
							} }
						>
							<Icon icon={ close } type="close" size={ 32 } />
						</button>
					</div>
				</>
			) }
		</div>
	);
};
export default Survey;
