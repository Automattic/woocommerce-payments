/**
 * External dependencies
 */
import React, { useState } from 'react';
import ReactDOM from 'react-dom';

/**
 * Internal dependencies
 */
import type { TaskItemProps } from '../types';
import strings from '../strings';
import SetupLivePaymentsModal from '../../modal/setup-live-payments';

const SetupLivePaymentsModalWrapper: React.FC = () => {
	const [ modalVisible, setModalVisible ] = useState( true );

	return modalVisible ? (
		<SetupLivePaymentsModal onClose={ () => setModalVisible( false ) } />
	) : (
		<></>
	);
};

export const getGoLiveTask = (): TaskItemProps | null => {
	const handleClick = () => {
		const container = document.createElement( 'div' );
		container.id = 'wcpay-golivemodal-container';
		document.body.appendChild( container );
		ReactDOM.render( <SetupLivePaymentsModalWrapper />, container );
	};

	return {
		key: 'go-live-payments',
		level: 3,
		content: '',
		title: strings.tasks.go_live.title,
		time: strings.tasks.go_live.time,
		completed: false,
		onClick: handleClick,
		action: handleClick,
		expandable: false,
		showActionButton: false,
	};
};
