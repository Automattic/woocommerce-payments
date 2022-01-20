// hook for handling API calls to get and create platform checkout user.
const usePlatformCheckoutUser = () => {
	const isRegisteredUser = true;

	return {
		isRegisteredUser,
	};
};

export default usePlatformCheckoutUser;
