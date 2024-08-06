/**
 * External dependencies
 */
/* eslint-disable import/no-unresolved */
import http from 'k6/http';

// eslint-disable-next-line no-undef
const BASE_URL = __ENV.BASE_URL;

export default function getAccounts() {
	return http.get( `${ BASE_URL }/test` );
}
