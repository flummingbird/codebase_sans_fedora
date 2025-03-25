# Readme.md for codebase_sans_fedora

## This repository is meant to be run inside an isle-site-template repository
 
### To get started from scratch:

`git clone https://github.com/flummingbird/isle-site-template-sans-fedora`

`cd isle-site-template-sans-fedora`

### Generate secrets

`bash generate-certs.sh`


`bash generate-secrets.sh`


### replace the drupal codebase

`cd drupal/rootfs/var/www/`

`rm -rf drupal`

`git clone http://github.com/flummingbird/codebase_sans_fedora`

`mv codebase_sans_fedora  drupal`

`cd ../../../..`

### bring up the containers

`docker compose --profile dev up --build -d`

### then log into the site at [islandora.dev](https://islandora.dev)

### log into the site as the admin user using the secrets/DRUPAL_DEFAULT_ACCOUNT_PASSWORD file's contents

### then log into the site as admin with 'password' not sure why the DRUPAL_DEFAULT_ACCOUNT_PASSWORD doesn't stick...

### if that still hasn't worked you can log into the container and change the password:

`docker exec -it <drupal-container-id> bash`


`drush user:password admin "newpassword"`


### for some reason the drush-migrate import needs to be run to populate the content models

### find the drupal container id and copy it

`docker ps | grep drupal`

### paste the contaner id below and run this command:

`docker exec -it <drupal-container-id> composer exec -- drush migrate:import --userid=1 --tag=islandora`

### enable the stark theme 

`docker exec -it <drupal-container-id> drush theme:enable stark`

