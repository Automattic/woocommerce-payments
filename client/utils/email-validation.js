// Client-side ASCII email check. Stricter than the browser's built-in
// email validation so incomplete addresses like `test@test` are rejected,
// but looser than WordPress `is_email()` (which enforces a minimum length,
// rejects consecutive dots, and applies per-subdomain rules). The server
// remains the source of truth for validation.
export const isEmail = ( email ) =>
	email.length <= 254 &&
	/^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9.-]+$/.test(
		email
	);
