# Stream Utilities
This is a forked and updated version of the original package, twistor/stream-util.

![Status](https://github.com/github/docs/actions/workflows/tests.yml/badge.svg)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![Packagist Version](https://img.shields.io/packagist/v/codementality/stream-util.svg?style=flat-square)](https://packagist.org/packages/codementality/stream-util)

Helper functions for dealing with streams.

## Installation

```
composer require codementality/stream-util
```

## Usage

```php
use Codementality\StreamUtil;

$stream = fopen('php://temp', 'w+b');

fwrite($stream, 'asdfasfdas');

$cloned = StreamUtil::copy($stream, false); // Passing in true (the default),
                                            // will close the input stream.

StreamUtil::getSize($stream); // 10

StreamUtil::isAppendable($stream); // false

StreamUtil::isReadable($stream); // true

StreamUtil::isSeekable($stream); // true

StreamUtil::isWritable($stream); // true

StreamUtil::tryRewind($stream);  // true

StreamUtil::trySeek($stream, 0, SEEK_END); // true

// Metadata helpers.
StreamUtil::getMetaDataKey($stream, 'blocked') // false

StreamUtil::getUri($stream); // php://temp

StreamUtil::getUsuableUri($stream); // Returns a URI that can be used
                                    // with fopen().
                                    // false in this case.

// Mode helpers.
StreamUtil::modeIsAppendable('w+'); // false

StreamUtil::modeIsAppendOnly('a+'); // false

StreamUtil::modeIsReadable('w+');   // true

StreamUtil::modeIsReadOnly('r');    // true

StreamUtil::modeIsWritable('r+');   // true

StreamUtil::modeIsWriteOnly('w');   // true
```
