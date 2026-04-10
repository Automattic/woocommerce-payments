// ASCII-only email validation matching WordPress is_email() and Stripe's
// server-side requirements.
export const isEmail = ( email ) =>
	email.length <= 254 &&
	/^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9.-]+$/.test(
		email
	);
