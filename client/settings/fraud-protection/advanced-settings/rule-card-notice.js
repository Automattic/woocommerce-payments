/**
 * External dependencies
 */
import { Icon } from '@wordpress/icons';
import React from 'react';

import './../style.scss';

const getSVGIconOfNoticeType = ( type ) => {
	switch ( type ) {
		case 'error':
			return (
				<svg
					width="18"
					height="18"
					viewBox="0 0 18 18"
					fill="none"
					xmlns="http://www.w3.org/2000/svg"
					data-testid="rule-card-notice-error-icon-svg"
				>
					<path
						fillRule="evenodd"
						clipRule="evenodd"
						d={
							'M9 1.75C4.99594 1.75 1.75 4.99594 1.75 9C1.75 13.0041 4.99594 16.25 9 16.25C13.0041 ' +
							'16.25 16.25 13.0041 16.25 9C16.25 4.99594 13.0041 1.75 9 1.75ZM0.25 9C0.25 4.16751 ' +
							'4.16751 0.25 9 0.25C13.8325 0.25 17.75 4.16751 17.75 9C17.75 13.8325 13.8325 17.75 ' +
							'9 17.75C4.16751 17.75 0.25 13.8325 0.25 9Z'
						}
						fill="#CC1818"
					/>
					<path d="M10 4H8V10H10V4Z" fill="#CC1818" />
					<path d="M10 12H8V14H10V12Z" fill="#CC1818" />
				</svg>
			);
		case 'warning':
			return (
				<svg
					width="18"
					height="18"
					viewBox="0 0 18 18"
					fill="none"
					xmlns="http://www.w3.org/2000/svg"
					data-testid="rule-card-notice-warning-icon-svg"
				>
					<path
						fillRule="evenodd"
						clipRule="evenodd"
						d={
							'M9 1.75C4.99594 1.75 1.75 4.99594 1.75 9C1.75 13.0041 4.99594 16.25 9 16.25C13.0041 ' +
							'16.25 16.25 13.0041 16.25 9C16.25 4.99594 13.0041 1.75 9 1.75ZM0.25 9C0.25 4.16751 ' +
							'4.16751 0.25 9 0.25C13.8325 0.25 17.75 4.16751 17.75 9C17.75 13.8325 13.8325 17.75 ' +
							'9 17.75C4.16751 17.75 0.25 13.8325 0.25 9Z'
						}
						fill="#bd8600"
					/>
					<path d="M10 4H8V10H10V4Z" fill="#bd8600" />
					<path d="M10 12H8V14H10V12Z" fill="#bd8600" />
				</svg>
			);
		case 'info':
			return (
				<svg
					width="24"
					height="24"
					viewBox="0 0 24 24"
					fill="none"
					xmlns="http://www.w3.org/2000/svg"
					data-testid="rule-card-notice-info-icon-svg"
				>
					<circle
						cx="12"
						cy="9"
						r="5"
						stroke="#007CBA"
						strokeWidth="1.42857"
					/>
					<line
						x1="9"
						y1="16.8818"
						x2="15"
						y2="16.8818"
						stroke="#007CBA"
						strokeWidth="1.5"
					/>
					<line
						x1="10"
						y1="19.25"
						x2="14"
						y2="19.25"
						stroke="#007CBA"
						strokeWidth="1.5"
					/>
				</svg>
			);
	}
};

const FraudProtectionRuleCardNotice = ( { type, children } ) => {
	return (
		0 <= [ 'error', 'warning', 'info' ].indexOf( type ) && (
			<div
				className={
					'fraud-protection-rule-card-notice fraud-protection-rule-card-notice-' +
					type
				}
			>
				<div className="fraud-protection-rule-card-notice-icon">
					<Icon
						icon={ getSVGIconOfNoticeType( type ) }
						width={ 18 }
						height={ 18 }
					></Icon>
				</div>
				<div className="fraud-protection-rule-card-notice-text">
					{ children }
				</div>
			</div>
		)
	);
};

export default FraudProtectionRuleCardNotice;
