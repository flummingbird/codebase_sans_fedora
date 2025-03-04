Flysystem for Drupal
====================

[Flysystem](http://flysystem.thephpleague.com/) is a filesystem abstraction
which allows you to easily swap out a local filesystem for a remote one.
Reducing technical debt and chance of vendor lock-in.

## REQUIREMENTS ##

- Composer (https://getcomposer.org)

## INSTALLATION ##

These are the steps you need to take in order to use this software. Order is
important.

 1. Download and install flysystem's module and its dependencies using Composer
 2. Enjoy.

```bash
cd /path/to/drupal/root
composer require drupal/flysystem
drush en flysystem
```

## CONFIGURATION ##

Stream wrappers are configured in settings.php.

The keys (local-example below) are the names of the stream wrappers.

For example: 'local-example://filename.txt'.

Stream wrapper names cannot contain underscores, they can only contain letters,
numbers, + (plus sign), . (period), - (hyphen).

The 'driver' key, is the type of adapter. The available adapters are:

 - local
 - ftp (Requires the PHP ftp extension)
 - Dropbox (https://www.drupal.org/project/flysystem_dropbox)
 - Rackspace (https://www.drupal.org/project/flysystem_rackspace)
 - s3v2 (https://www.drupal.org/project/flysystem_s3)
 - sftp (https://www.drupal.org/project/flysystem_sftp)
 - zip (https://www.drupal.org/project/flysystem_zip)
 - GCS (https://www.drupal.org/project/flysystem_gcs)
 - Swift (https://www.drupal.org/project/flysystem_swift)
 - Drupal Cache (https://www.drupal.org/project/flysystem_drupal_cache)
 - Aliyun OSS (https://www.drupal.org/project/flysystem_aliyun_oss)
 
The 'config' is the configuration passed into the Flysystem adapter.

Example configuration:

```php

$schemes = [
  'local-example' => [ // The name of the stream wrapper.

    'driver' => 'local', // The plugin key.

    'config' => [
      // Cache filesystem metadata. Not necessary for the local driver.
      'cache' => TRUE, 
      ...
      // This will be treated similarly to Drupal's private file system
      'root' => '/path/to/dir/outsite/drupal',
      // Or,
      // In order fo the public setting to work, the path must be relative
      // to the root of the Drupal install.
      'root' => 'sites/default/files/flysystem',
      ...
      'public' => TRUE,
    // Optional settings that apply to all adapters.
      // Defaults to Flysystem: scheme.
      'name' => 'Custom stream wrapper name',
      'description' => 'Custom description',
      // Uploads each file to an extra endpoint tha tis considered "write only"
      // Functions as a backup.
      // Note: only supports one replication endpoint.
      // Note: Use as a replication endpoint does not prevent that endpoint from
      //   also being used for other purposes. 
      'replicate' => 'ftpexample',
      // Serve Javascript or CSS via this stream wrapper. This is useful for
      // adapters that function as CDNs like the S3 adapter.
      //
      // Note:  if you have configured multiple flysystem schemes, you can only
      // configure one scheme to manage CSS and JS files.  If you do happen to
      // configure multiple schemes, the last scheme defined with the following
      // parameters will be the one actually used for storing and serving CSS and
      // JS.  @see https://www.drupal.org/project/flysystem/issues/3056455
      'serve_js' => TRUE, // Serve Javascript or CSS via this stream wrapper.
      'serve_css' => TRUE, // This is useful for adapters that function as
      // CDNs like the S3 adapter.
    ],
  // Used as a replication endpoint for 'local-example' (see the 'replicate'
  // option) but may still be used as a standalone endpoint.
    'ftpexample' => [
      'driver' => 'ftp',
      'config' => [
        'host' => 'ftp.example.com',
        'username' => 'username',
        'password' => 'password',
        // Optional config settings.
        'port' => 21,
        'root' => '/path/to/root',
        'passive' => TRUE,
        'ssl' => FALSE,
        'timeout' => 90,
        'permPrivate' => 0700,
        'permPublic' => 0700,
        'transferMode' => FTP_BINARY,
      ],
    ],
  ],
];

// Don't forget this!
$settings['flysystem'] = $schemes;
```

## USAGE ##
After configuring the endpoints, the default storage location may be changed on
the File System settings page (/admin/config/media/file-system). Once changed,
all new files will be automatically uploaded to the configured endpoint.

It is also possible to do a once-off synchronization of all files from one
endpoint to another, see /admin/config/media/file-system/flysystem for details.
This can be useful for sites which previously just used the local file system
and later added a remote file storage service.

## TROUBLESHOOTING ##

If you are having trouble with this module, check the status page at
admin/reports/status. The status page runs all the Flysystem checks and provides
useful error reporting.

## DELETING STYLES FOLDER -- a cautionary warning ##

This code in Drupal Core:

- https://git.drupalcode.org/project/drupal/-/blob/fd92a54070af95150b3e2277b97b047b4df7f995/core/modules/image/src/Entity/ImageStyle.php#L284-295

assumes that all read/write filewrappers will have image styles stored stored at the uri `wrapper://styles/{$imagestyle->id()}``. This is not always the case, particularly when modules create custom filewrappers such as this one. While this works most of the time, this could be a wrong assumption when the site needs to mount something like a FTP filesystem that just so happens to have a folder named "styles" and a subfolder with the image style id that contains things like CSS files or the like. This could result in irreparable file deletion.

More information can be found documented at https://www.drupal.org/project/drupal/issues/3284521
