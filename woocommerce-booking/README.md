# Booking & Appointment plugin for WooCommerce

## Steps for creating a build:

### Development
1. composer install
2. yarn install
3. yarn dev

### Production
1. composer install --no-dev
2. yarn install
3. yarn prod

### Some other instructions
1. "build" folder generated need not be committed (though we have provisions set in the .gitignore but we must take care). For each test cycle the build is ideally generated to avoid any issues.
2. BKAP_DEV_MODE value ( in woocommerce-booking.php ) should be set to TRUE while in development and set to FALSE for production.
3. BKAP version in .env file should be bumped alongside the version that is usually bumped up in the woocommerce-booking/woocommerce-booking.php file. This is important as this is used to set the path during the upload of static files to AWS.
4. Ensure that the build process completes without any errors. Please pull a fresh copy of the plugin and restart build process in cases of errors.
5. Delete the "dist" folder. They have been left after the build process just for reference. The dist folder contains the files that have been already uploaded to the AWS CDN.
