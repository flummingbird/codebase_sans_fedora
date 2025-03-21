# Readme.md for codebase_sans_fedora

## This repository is meant to be run inside an isle-site-template repository
 
## To get started from scratch:

`git checkout https://github.com/Islandora-Devops/isle-site-template`

`cd isle-site-template`

## Generate secrets

`bash generate-certs.sh`


`bash generate-secrets.sh`


## replace the drupal codebase

`cd drupal/rootfs/var/www/`

`rm -rf drupal`

`git clone http://github.com/flummingbird/codebase_sans_fedora`

`mv codebase_sans_fedora  drupal`

`cd ../../../..`

## bring up the containers

`docker compose --profile dev up --build -d`

## then log into the site at islandora.dev

## log into the site as the admin user using the secrets/DRUPAL_DEFAULT_ACCOUNT_PASSWORD file's contents

## then log into the site as admin with 'password' not sure why the DRUPAL_DEFAULT_ACCOUNT_PASSWORD doesn't stick...
