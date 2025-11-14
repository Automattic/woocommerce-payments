/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { Card, CardBody, Button, Notice } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { usePromotions, usePromotionActions } from 'wcpay/data';

/**
 * Test component to display and interact with promotions.
 * This is for testing purposes only - DO NOT COMMIT.
 */
const PromotionsTestDisplay: React.FC = () => {
	const { promotions, promotionsError, isLoading } = usePromotions();
	const { activatePromotion, dismissPromotion } = usePromotionActions();

	// Debug logging
	// eslint-disable-next-line no-console
	console.log( 'PromotionsTestDisplay render:', {
		promotions,
		promotionsError,
		isLoading,
		hasAvailable: promotions?.available_promotions?.length,
		availableArray: promotions?.available_promotions,
	} );

	if ( isLoading ) {
		return (
			<Card>
				<CardBody>
					<h2>Promotions (Loading...)</h2>
					<p>Loading promotions data...</p>
				</CardBody>
			</Card>
		);
	}

	if ( promotionsError ) {
		return (
			<Card>
				<CardBody>
					<h2>Promotions (Error)</h2>
					<Notice status="error" isDismissible={ false }>
						Error loading promotions:{ ' ' }
						{ promotionsError.code || 'Unknown error' }
					</Notice>
				</CardBody>
			</Card>
		);
	}

	const handleActivate = ( identifier: string ) => {
		if (
			// eslint-disable-next-line no-alert
			window.confirm(
				`Activate promotion "${ identifier }"? This will call the API.`
			)
		) {
			activatePromotion( identifier, true );
		}
	};

	const handleDismiss = ( identifier: string ) => {
		if (
			// eslint-disable-next-line no-alert
			window.confirm(
				`Dismiss promotion "${ identifier }"? This will call the API.`
			)
		) {
			dismissPromotion( identifier );
		}
	};

	return (
		<Card>
			<CardBody>
				<h2>
					<span role="img" aria-label="test tube">
						🧪
					</span>{ ' ' }
					Promotions Test Display
				</h2>

				<div style={ { marginBottom: '20px' } }>
					<h3>Available Promotions</h3>
					{ promotions?.available_promotions?.length ? (
						<ul>
							{ promotions.available_promotions.map(
								( promo ) => (
									<li
										key={ promo.promo_id }
										style={ {
											marginBottom: '15px',
											padding: '10px',
											background: '#f0f0f0',
											borderRadius: '4px',
										} }
									>
										<strong>{ promo.promo_id }</strong>
										<br />
										<small>
											Discount: { promo.discount_rate } |
											Duration: { promo.duration_days }{ ' ' }
											days | Variations:{ ' ' }
											{ promo.variations.length }
										</small>
										<br />
										{ promo.variations.map(
											( variation, idx ) => (
												<div
													key={ variation.id }
													style={ {
														marginTop: '10px',
														padding: '8px',
														background: '#fff',
														borderRadius: '4px',
													} }
												>
													<strong>
														Variation { idx + 1 }
													</strong>{ ' ' }
													<code>
														({ variation.type })
													</code>
													{ variation.badge && (
														<span
															style={ {
																marginLeft:
																	'8px',
																padding:
																	'2px 6px',
																background:
																	variation.badge_type ===
																	'success'
																		? '#4caf50'
																		: '#2196f3',
																color: '#fff',
																borderRadius:
																	'3px',
																fontSize:
																	'11px',
															} }
														>
															{ variation.badge }
														</span>
													) }
													<br />
													<div>
														{ variation.heading }
													</div>
													<small>
														{
															variation.description
														}
													</small>
													<br />
													{ variation.tc_url && (
														<small>
															<a
																href={
																	variation.tc_url
																}
																target="_blank"
																rel="noreferrer"
															>
																Terms &
																Conditions
															</a>
														</small>
													) }
												</div>
											)
										) }
										<div style={ { marginTop: '8px' } }>
											<Button
												variant="primary"
												size="small"
												onClick={ () =>
													handleActivate(
														promo.promo_id
													)
												}
											>
												Activate
											</Button>{ ' ' }
											<Button
												variant="secondary"
												size="small"
												onClick={ () =>
													handleDismiss(
														promo.promo_id
													)
												}
											>
												Dismiss
											</Button>
										</div>
									</li>
								)
							) }
						</ul>
					) : (
						<p>No available promotions</p>
					) }
				</div>

				<div style={ { marginBottom: '20px' } }>
					<h3>Active Promotions</h3>
					{ promotions?.active_promotions?.length ? (
						<ul>
							{ promotions.active_promotions.map( ( promoId ) => {
								// Look up the full promotion from available_promotions
								const fullPromo = promotions.available_promotions.find(
									( p ) => p.promo_id === promoId
								);

								return (
									<li
										key={ promoId }
										style={ {
											marginBottom: '15px',
											padding: '10px',
											background: '#e8f5e9',
											borderRadius: '4px',
										} }
									>
										<strong>{ promoId }</strong>
										{ fullPromo && (
											<>
												<br />
												<small>
													Discount:{ ' ' }
													{ fullPromo.discount_rate }{ ' ' }
													| Duration:{ ' ' }
													{ fullPromo.duration_days }{ ' ' }
													days
												</small>
											</>
										) }
									</li>
								);
							} ) }
						</ul>
					) : (
						<p>No active promotions</p>
					) }
				</div>

				<div
					style={ {
						padding: '10px',
						background: '#fff3cd',
						borderRadius: '4px',
					} }
				>
					<strong>Debug Info:</strong>
					<br />
					<pre
						style={ {
							fontSize: '11px',
							overflow: 'auto',
							maxHeight: '200px',
						} }
					>
						{ JSON.stringify( promotions, null, 2 ) }
					</pre>
				</div>
			</CardBody>
		</Card>
	);
};

export default PromotionsTestDisplay;
