echo "Starting the deployment script"

TMP_DIR="tmp-plugin"
NEEDED_FILES=( assets dist includes woocommerce-payments.php )

# Build all necessary scripts
echo "Compiling"
npm run build

# Copy all important files to a temporary dir
echo "Cleaning up and creating a temporary directory"
if [ -d $TMP_DIR ]; then
	rm -r $TMP_DIR
fi;
mkdir $TMP_DIR

echo "Copying files"
for fileName in "${NEEDED_FILES[@]}"; do
	cp -R $fileName $TMP_DIR
done

# Send the temporary dir to the web server
echo "Uploading"
lftp sftp://$PRESSABLE_USERNAME:$PRESSABLE_PASSWORD@sftp.pressable.com -e "mirror --verbose=1 -R ./$TMP_DIR $PRESSABLE_SITE_NAME/wp-content/plugins/woocommerce-payments; quit"

# Cleanup
echo "Cleaning up"
rm -rf $TMP_DIR

echo "Done"
