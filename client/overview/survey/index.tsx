/** @format **/

/**
 * External dependencies
 */
import React from 'react';

/**
 * Internal dependencies.
 */
import {
	Card,
	CardBody,
	Button,
	TextareaControl,
	Icon,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import './style.scss';
import Emoticons from 'wcpay/overview/survey/emoticons';
import { HorizontalRule } from '@wordpress/primitives';
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

	const currentRating = surveyAnswers.rating ?? '';
	const ratingWithComment = [ 'very-unhappy', 'unhappy', 'neutral' ];
	const showComment = ratingWithComment.includes( currentRating );
	const setReviewRating = function ( value: string ) {
		const answers: OverviewSurveyFields = {
			...surveyAnswers,
			rating: value,
		};
		setSurveyAnswers( answers );
		if ( ! ratingWithComment.includes( value ) ) {
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
									{ __(
										'How do you like your new finance overview?',
										'woocommerce-payments'
									) }
								</span>
								<div>
									<Emoticons
										disabled={ 'pending' === status }
										rating="very-unhappy"
										setReviewRating={ setReviewRating }
										currentRating={ currentRating }
									/>
									<Emoticons
										disabled={ 'pending' === status }
										rating="unhappy"
										setReviewRating={ setReviewRating }
										currentRating={ currentRating }
									/>
									<Emoticons
										disabled={ 'pending' === status }
										rating="neutral"
										setReviewRating={ setReviewRating }
										currentRating={ currentRating }
									/>
									<Emoticons
										disabled={ 'pending' === status }
										rating="happy"
										setReviewRating={ setReviewRating }
										currentRating={ currentRating }
									/>
									<Emoticons
										disabled={ 'pending' === status }
										rating="very-happy"
										setReviewRating={ setReviewRating }
										currentRating={ currentRating }
									/>
								</div>
							</div>
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
					</>
				) }

				{ ! surveySubmitted && showComment && (
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
					<div className="survey_container">
						<span>
							<span role="img" aria-label="Thank you!">
								🙌{ ' ' }
							</span>
							{ __(
								'We appreciate your feedback!',
								'woocommerce-payments'
							) }
						</span>
					</div>
				) }
			</CardBody>
		</Card>
	);
};
export default Survey;
