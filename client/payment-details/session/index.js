/** @format **/

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Card } from '@woocommerce/components';
import ReactMapboxGl, { Layer, Feature } from 'react-mapbox-gl';
import 'mapbox-gl/dist/mapbox-gl.css';

/**
 * Internal dependencies.
 */
import PaymentDetailsSessionDetail from './detail';
import './style.scss';
import Loadable from 'components/loadable';

const PaymentDetailsSession = ( props ) => {
	const { isLoading } = props;
	const Map = ReactMapboxGl({
		accessToken: 'pk.eyJ1IjoiYXV0b21hdHRpYyIsImEiOiJja2dyczJqNjYwOGp0MzRyc2lscWNseXkwIn0.thJ9s-11TQggxsovJemeAg'
	});

	// Shorter name for readability
	const Detail = PaymentDetailsSessionDetail;
	const placeholdertemp = 'sample text';

	return (
		<Card
		title={
			<Loadable
			isLoading={ isLoading }
			value={ __( 'Session', 'woocommerce-payments' ) }
			/>
		}
		>
		<div className="payment-method-details">
		<div className="payment-method-details__column">
		<p class="woocommerce-timeline-group__title">user agent</p>
		<Detail
		isLoading={ isLoading }
		label={ __( 'Number', 'woocommerce-payments' ) }
		>
		&bull;&bull;&bull;&bull;&nbsp;{ placeholdertemp }
		</Detail>

		<Detail
		isLoading={ isLoading }
		label={ __( 'Fingerprint', 'woocommerce-payments' ) }
		>
		{ placeholdertemp }
		</Detail>

		<Detail
		isLoading={ isLoading }
		label={ __( 'Expires', 'woocommerce-payments' ) }
		>
		{ placeholdertemp }
		</Detail>

		<Detail
		isLoading={ isLoading }
		label={ __( 'Type', 'woocommerce-payments' ) }
		>
		{ placeholdertemp }
		</Detail>

		<Detail
		isLoading={ isLoading }
		label={ __( 'ID', 'woocommerce-payments' ) }
		>
		{ placeholdertemp }
		</Detail>
		</div>

		<div className="payment-method-details__column">
		<p class="woocommerce-timeline-group__title">location</p>
		<Detail
		isLoading={ isLoading }
		label={ __( 'Owner', 'woocommerce-payments' ) }
		>
		{ placeholdertemp }
		</Detail>

		<Detail
		isLoading={ isLoading }
		label={ __( 'Owner email', 'woocommerce-payments' ) }
		>
		{ placeholdertemp }
		</Detail>

		<Detail
		isLoading={ isLoading }
		label={ __( 'Address', 'woocommerce-payments' ) }
		>
		<span
		dangerouslySetInnerHTML={ {
			__html: placeholdertemp,
		} }
		/>
		</Detail>

		<Detail
	isLoading={ isLoading }
	label={ __( 'Origin', 'woocommerce-payments' ) }
		>
		{ placeholdertemp }
		</Detail>

		<Detail
	isLoading={ isLoading }
	label={ __( 'CVC check', 'woocommerce-payments' ) }
		></Detail>

		<Detail
	isLoading={ isLoading }
	label={ __( 'Street check', 'woocommerce-payments' ) }
		>
		{ placeholdertemp }
		</Detail>

		<Detail
	isLoading={ isLoading }
	label={ __( 'Zip check', 'woocommerce-payments' ) }
		>
		{ placeholdertemp }
		</Detail>
		</div>

		</div>
		<div className="payment-method-map">
			<p class="woocommerce-timeline-group__title">MAP 1 here</p>
			<Map
				style="mapbox://styles/mapbox/streets-v11"
				containerStyle={{
				height: '320px',
				width: '970px'
			}} >
				<Layer type="symbol" id="marker" layout={{ 'icon-image': 'marker-15' }}>
					<Feature coordinates={[-0.481747846041145, 51.3233379650232]} />
				</Layer>
			</Map>
		</div>
	</Card>
);
};

export default PaymentDetailsSession;
