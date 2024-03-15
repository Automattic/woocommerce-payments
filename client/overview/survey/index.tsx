/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { HorizontalRule } from '@wordpress/primitives';
import {
	Card,
	CardBody,
	Button,
	TextareaControl,
	Icon,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { createInterpolateElement, useState } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import './style.scss';
import Emoticons from 'wcpay/overview/survey/emoticons';
import { useOverviewSurveyContext } from './context';
import { OverviewSurveyFields } from 'wcpay/overview/survey/types';
import close from 'wcpay/overview/survey/icons/close';

const Survey = () => {
	const { status } = useOverviewSurveyContext();
	const {
		setSurveySubmitted,
		surveySubmitted,
		surveyAnswers,
		setSurveyAnswers,
	} = useOverviewSurveyContext();

	const [ showComponent, setShowComponent ] = useState( true );

	const currentRating = surveyAnswers.rating ?? '';
	const ratingWithComment = [ 'very-unhappy', 'unhappy', 'neutral' ];
	const ratings = [
		'very-unhappy',
		'unhappy',
		'neutral',
		'happy',
		'very-happy',
	];
	const showComment = ratingWithComment.includes( currentRating );
	const setReviewRating = function ( value: string ) {
		const answers: OverviewSurveyFields = {
			...surveyAnswers,
			rating: value,
		};
		setSurveyAnswers( answers );
		if ( ! ratingWithComment.includes( value ) && '' !== value ) {
			setSurveySubmitted( answers );
		}
	};

	if ( ! showComponent ) {
		return <></>;
	}

	return (
		<Card>
			<CardBody className="wcpay-overview__survey__container">
				{ ! surveySubmitted && (
					<>
						<div className="survey_container">
							<div className="emoticons_container">
								<span>
									{ __(
										'How do you like your new finance overview?',
										'woocommerce-payments'
									) }
									<span className="padding_left_10">
										{ ratings.map( ( rating ) => {
											return (
												<Emoticons
													key={ rating }
													disabled={
														'pending' === status
													}
													rating={ rating }
													setReviewRating={
														setReviewRating
													}
													currentRating={
														currentRating
													}
												/>
											);
										} ) }
									</span>
								</span>
							</div>
							<div className="close_container">
								{ showComment && (
									<button
										type="button"
										className="components-button has-icon"
										aria-label="Close dialog"
										onClick={ () => {
											setReviewRating( '' );
										} }
									>
										<Icon
											icon={ close }
											type="close"
											size={ 32 }
										/>
									</button>
								) }
							</div>
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
								readOnly={ 'pending' === status }
							/>
							<p className="survey-bottom-disclaimer">
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
								variant={ 'secondary' }
								disabled={ 'pending' === status }
								onClick={ () => {
									setReviewRating( '' );
								} }
							>
								{ __( 'Cancel', 'woocommerce-payments' ) }
							</Button>
							<Button
								variant={ 'primary' }
								isBusy={ 'pending' === status }
								disabled={ 'pending' === status }
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
								<span
									className="padding_right_7"
									role="img"
									aria-label="Thank you!"
								>
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
			</CardBody>
		</Card>
	);
};
export default Survey;
