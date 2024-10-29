/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { HorizontalRule } from '@wordpress/primitives';
import { Button, CardFooter, TextareaControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { createInterpolateElement, useState } from '@wordpress/element';
import { Icon, closeSmall } from '@wordpress/icons';

/**
 * Internal dependencies.
 */
import type { Rating } from './types';
import { useOverviewSurveyContext } from './context';
import Emoticon from './emoticon';
import './style.scss';

const Survey: React.FC = () => {
	const {
		responseStatus,
		surveySubmitted,
		surveyAnswers,
		setSurveyAnswers,
		setSurveySubmitted,
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
		const answers = {
			...surveyAnswers,
			rating: value,
		};
		setSurveyAnswers( answers );

		// If the user selects a rating that does not require a comment, submit the survey immediately.
		if ( value && ! ratingWithComment.includes( value ) ) {
			setSurveySubmitted( answers );
		}
	};

	if ( ! showComponent ) {
		return null;
	}

	if ( surveySubmitted ) {
		return (
			<CardFooter size="small">
				<div className="wcpay-payments-activity__survey">
					<div className="survey_container">
						<span role="img" aria-label="Thank you!">
							🙌
						</span>
						{ __(
							'We appreciate your feedback!',
							'woocommerce-payments'
						) }
					</div>

					<div className="close_container">
						<button
							type="button"
							className="components-button has-icon"
							aria-label="Close dialog"
							onClick={ () => {
								setShowComponent( false );
							} }
						>
							<Icon icon={ closeSmall } size={ 28 } />
						</button>
					</div>
				</div>
			</CardFooter>
		);
	}

	return (
		<CardFooter size="small">
			<div className="wcpay-payments-activity__survey">
				<div className="survey_container">
					{ __(
						'Are these metrics helpful?',
						'woocommerce-payments'
					) }

					<div className="survey_container__emoticons">
						{ ratings.map( ( rating ) => (
							<Emoticon
								key={ rating }
								disabled={ disableForm }
								rating={ rating }
								onClick={ () => setReviewRating( rating ) }
								isSelected={ rating === currentRating }
							/>
						) ) }
					</div>
				</div>

				{ showComment && (
					<>
						<div className="close_container">
							<button
								type="button"
								className="components-button has-icon"
								aria-label="Close dialog"
								onClick={ () => {
									setReviewRating( undefined );
								} }
								disabled={ disableForm }
							>
								<Icon icon={ closeSmall } size={ 28 } />
							</button>
						</div>

						<HorizontalRule />

						<div className="comment_container">
							<TextareaControl
								label={ __(
									'Why do you feel that way? (optional)',
									'woocommerce-payments'
								) }
								onChange={ ( text ) => {
									setSurveyAnswers( ( prev ) => ( {
										...prev,
										comments: text,
									} ) );
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
			</div>
		</CardFooter>
	);
};
export default Survey;
