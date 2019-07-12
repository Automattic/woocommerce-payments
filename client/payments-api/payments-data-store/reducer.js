
const defualtState = {
	resources: {},
};

export default function reducer( state = defualtState, action ) {
	switch ( action.type ) {
		case 'FRESH_DATA_REQUESTED':
		case 'FRESH_DATA_RECEIVED':
		default:
			return state;
	}
}
