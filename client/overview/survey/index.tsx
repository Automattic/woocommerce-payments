/** @format **/

/**
 * External dependencies
 */
import React from 'react';

/**
 * Internal dependencies.
 */
import { Card, CardBody, Button, TextareaControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import './style.scss';
import Emoticons from 'wcpay/overview/survey/emoticons';
import { HorizontalRule } from '@wordpress/primitives';
import { useOverviewSurveyContext } from './context';
import { OverviewSurveyFields } from 'wcpay/overview/survey/types';

const Survey = () => {
	const { status } = useOverviewSurveyContext();
	const {
		setSurveySubmitted,
		surveySubmitted,
		surveyAnswers,
		setSurveyAnswers,
	} = useOverviewSurveyContext();

	const setReviewRating = function ( value: number ) {
		const answers: OverviewSurveyFields = {
			...surveyAnswers,
			rating: value,
		};
		setSurveyAnswers( answers );
		if ( value > 3 ) {
			setSurveySubmitted( answers );
		}
	};

	return (
		<Card>
			<CardBody className="wcpay-overview__survey__container">
				{ ! surveySubmitted && (
					<>
						<div className="survey_container">
							<div className="emoticons_container">
								<span>
									How do you like your new finance overview?
								</span>
								<Emoticons
									icon={ 'icon_sad1.png' }
									disabled={ 'pending' === status }
									rating="1"
									onClick={ setReviewRating }
									currentRating={ surveyAnswers.rating ?? 0 }
								/>
								<Emoticons
									icon={ 'icon_sad2.png' }
									disabled={ 'pending' === status }
									rating="2"
									onClick={ setReviewRating }
									currentRating={ surveyAnswers.rating ?? 0 }
								/>
								<Emoticons
									icon={ 'icon_neutral.png' }
									disabled={ 'pending' === status }
									rating="3"
									onClick={ setReviewRating }
									currentRating={ surveyAnswers.rating ?? 0 }
								/>
								<Emoticons
									icon={ 'icon_smile.png' }
									disabled={ 'pending' === status }
									rating="4"
									onClick={ setReviewRating }
									currentRating={ surveyAnswers.rating ?? 0 }
								/>
								<Emoticons
									icon={ 'icon_love.png' }
									disabled={ 'pending' === status }
									rating="5"
									onClick={ setReviewRating }
									currentRating={ surveyAnswers.rating ?? 0 }
								/>
							</div>
							{ ( surveyAnswers.rating ?? 0 ) <= 3 &&
								( surveyAnswers.rating ?? 0 ) > 0 && (
									<button
										type="button"
										className="components-button has-icon"
										aria-label="Close dialog"
										onClick={ () => {
											setReviewRating( 0 );
										} }
									>
										<svg
											width="24"
											height="24"
											xmlns="http://www.w3.org/2000/svg"
											viewBox="0 0 24 24"
											aria-hidden="true"
											focusable="false"
										>
											<path d="M12 13.06l3.712 3.713 1.061-1.06L13.061 12l3.712-3.712-1.06-1.06L12 10.938 8.288 7.227l-1.061 1.06L10.939 12l-3.712 3.712 1.06 1.061L12 13.061z"></path>
										</svg>
									</button>
								) }
						</div>
					</>
				) }

				{ ! surveySubmitted &&
					( surveyAnswers.rating ?? 0 ) <= 3 &&
					( surveyAnswers.rating ?? 0 ) > 0 && (
						<>
							<HorizontalRule />
							<TextareaControl
								className="ssr-text-field"
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
								{ sprintf(
									/* translators: %s: WooPayments */
									__(
										'Your feedback will be only be shared with WooCommerce and treated pursuant to our privacy policy.',
										'woocommerce-payments'
									),
									'WooPayments'
								) }
							</p>
							<div className="wcpay-confirmation-modal__footer">
								<Button
									variant={ 'secondary' }
									disabled={ 'pending' === status }
									onClick={ () => {
										setReviewRating( 0 );
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
					<div className="survey_container">
						<span>
							<span role="img" aria-label="Thank you!">
								🙌
							</span>
							We appreciate your feedback!
						</span>
					</div>
				) }
			</CardBody>
		</Card>
	);
};
export default Survey;
