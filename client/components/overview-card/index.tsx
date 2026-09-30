/**
 * External dependencies
 */
import React from 'react';
import { Card, CardHeader } from '@wordpress/components';

interface OverviewCardProps {
	title: React.ReactNode;
	isLoading: boolean;
	// JSX needs PascalCase to treat this as a component, not a DOM tag — hence the eslint-disable.
	// eslint-disable-next-line @typescript-eslint/naming-convention
	LoadingState: React.ComponentType;
	className?: string;
	headerClassName?: string;
	children?: React.ReactNode;
}

const OverviewCard: React.FC< OverviewCardProps > = ( {
	title,
	isLoading,
	LoadingState,
	className,
	headerClassName,
	children,
} ) => (
	<Card className={ className }>
		<CardHeader className={ headerClassName }>{ title }</CardHeader>
		{ isLoading ? <LoadingState /> : children }
	</Card>
);

export default OverviewCard;
