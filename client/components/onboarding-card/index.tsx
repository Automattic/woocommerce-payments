/**
 * External dependencies
 */
import React from 'react';
import { Button } from '@wordpress/components';
import classNames from 'classnames';

/**
 * Internal dependencies
 */
import './style.scss';

interface Props {
	icon: React.ReactNode;
	heading: string;
	actionLabel: string;
	content: React.ReactNode;
	onClick: () => void;
	className?: string;
}
const OnboardingCard: React.FC< Props > = ( {
	icon,
	heading,
	content,
	onClick,
	actionLabel,
	className,
} ) => {
	return (
		<div
			className={ classNames(
				'wcpay-component-onboarding-card',
				className
			) }
		>
			<div className="wcpay-component-onboarding-card__label">
				{ icon }
				{ heading }
			</div>
			<div className="wcpay-component-onboarding-card__body">
				{ content }
			</div>
			<div className="wcpay-component-onboarding-card__footer">
				<Button
					className="wcpay-component-onboarding-card__button"
					variant="primary"
					data-testid="live-mode-button"
					onClick={ onClick }
				>
					{ actionLabel }
				</Button>
			</div>
		</div>
	);
};

export default OnboardingCard;
