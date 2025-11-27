/**
 * External dependencies
 */
import React, { useEffect, useState } from 'react';
import {
	Card,
	CardBody,
	CardHeader,
	CardMedia,
	CardFooter,
	Button,
	Flex,
	Icon,
} from '@wordpress/components';
import { closeSmall } from '@wordpress/icons';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { SpotlightProps } from './types';
import Chip from 'components/chip';
import './style.scss';

const showDelayMs = 4000; // 4 seconds

const Spotlight: React.FC< SpotlightProps > = ( {
	badge,
	heading,
	description,
	disclaimer,
	image,
	primaryButtonLabel,
	onPrimaryClick,
	secondaryButtonLabel,
	onSecondaryClick,
	onDismiss,
	onView,
	showImmediately = false,
} ) => {
	const [ isVisible, setIsVisible ] = useState( false );
	const [ isAnimatingIn, setIsAnimatingIn ] = useState( false );

	useEffect( () => {
		if ( showImmediately ) {
			setIsVisible( true );
			setIsAnimatingIn( true );
			return;
		}

		// Show the spotlight after a delay
		const timer = setTimeout( () => {
			setIsVisible( true );
			// Double RAF to ensure browser paints initial state before animating
			requestAnimationFrame( () => {
				requestAnimationFrame( () => {
					setIsAnimatingIn( true );
				} );
			} );
		}, showDelayMs );

		return () => clearTimeout( timer );
	}, [ showImmediately ] );

	// Call onView when spotlight becomes visible
	useEffect( () => {
		if ( isAnimatingIn && onView ) {
			onView();
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ isAnimatingIn ] );

	const handleClose = () => {
		setIsAnimatingIn( false );
		// Wait for animation to complete before hiding
		setTimeout( () => {
			setIsVisible( false );
			onDismiss();
		}, 300 );
	};

	const handlePrimaryClick = () => {
		onPrimaryClick();
		handleClose();
	};

	if ( ! isVisible ) {
		return null;
	}

	return (
		<div
			className={ `wcpay-spotlight ${
				isAnimatingIn ? 'wcpay-spotlight--visible' : ''
			}` }
		>
			<div className="wcpay-spotlight__container">
				<Card
					className={ `wcpay-spotlight__card ${
						image ? 'has-image' : ''
					}` }
					elevation={ 2 }
				>
					{ image && (
						<CardMedia className="wcpay-spotlight__image">
							{ typeof image === 'string' ? (
								<img
									src={ image }
									alt={ __(
										'Spotlight image',
										'woocommerce-payments'
									) }
								/>
							) : (
								image
							) }
						</CardMedia>
					) }

					<CardHeader
						isBorderless={ true }
						size="small"
						className="wcpay-spotlight__header"
					>
						<Flex
							className="wcpay-spotlight__controls"
							justify="flex-end"
						>
							<Button
								className="wcpay-spotlight__close-btn"
								label={ __( 'Close', 'woocommerce-payments' ) }
								icon={
									<Icon
										icon={ closeSmall }
										viewBox="6 4 12 14"
									/>
								}
								iconSize={ 24 }
								onClick={ handleClose }
							/>
						</Flex>
					</CardHeader>

					<CardBody className="wcpay-spotlight__body" size="small">
						{ badge && (
							<div className="wcpay-spotlight__badge">
								<Chip message={ badge } type="primary" />
							</div>
						) }
						<h2 className="wcpay-spotlight__heading">
							{ heading }
						</h2>
						<div className="wcpay-spotlight__description">
							{ description }
						</div>
						{ disclaimer && (
							<div className="wcpay-spotlight__disclaimer">
								{ disclaimer }
							</div>
						) }
					</CardBody>

					<CardFooter
						isBorderless={ true }
						size="small"
						className="wcpay-spotlight__footer"
					>
						<Flex justify="flex-end" gap={ 3 }>
							{ secondaryButtonLabel && (
								<Button
									className="wcpay-spotlight__secondary-btn"
									variant="tertiary"
									onClick={ onSecondaryClick }
								>
									{ secondaryButtonLabel }
								</Button>
							) }
							<Button
								className="wcpay-spotlight__primary-btn"
								variant="primary"
								onClick={ handlePrimaryClick }
							>
								{ primaryButtonLabel }
							</Button>
						</Flex>
					</CardFooter>
				</Card>
			</div>
		</div>
	);
};

export default Spotlight;
