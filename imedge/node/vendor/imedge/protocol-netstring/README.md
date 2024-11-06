IMEdge\\Protocol\\NetString
==========================

This library implements the [NetString](https://en.wikipedia.org/wiki/Netstring)
protocol and provides a **NetStringReader**, a **NetStringWriter** and a bidirectional
**NetStringConnection** implementation for [AMPHP](https://amphp.org/).

[![Coding Standards](https://github.com/im-edge/protocol-netstring/actions/workflows/CodingStandards.yml/badge.svg)](https://github.com/im-edge/protocol-netstring/actions/workflows/CodingStandards.yml)
[![Unit Tests](https://github.com/im-edge/protocol-netstring/actions/workflows/UnitTests.yml/badge.svg)](https://github.com/im-edge/protocol-netstring/actions/workflows/UnitTests.yml)
[![Static Analysis](https://github.com/im-edge/protocol-netstring/actions/workflows/StaticAnalysis.yml/badge.svg)](https://github.com/im-edge/protocol-netstring/actions/workflows/StaticAnalysis.yml)
[![PHPStan Level 9](https://img.shields.io/badge/PHPStan-level%209-brightgreen.svg?style=flat)](https://phpstan.org/)
[![Minimum PHP Version: 8.1](https://img.shields.io/badge/php-%3E%3D%208.1-8892BF.svg)](https://php.net/)
[![License: MIT](https://poser.pugx.org/imedge/protocol-netstring/license)](https://choosealicense.com/licenses/mit/)
[![Version](https://poser.pugx.org/imedge/protocol-netstring/version)](https://packagist.org/packages/imedge/protocol-netstring)

Installation
------------

This package can be installed as a Composer dependency on PHP 8.1 and later.

```shell
composer require imedge/protocol-netstring
```

Usage
-----

### NetStringReader

#### Sample Code

```php
<?php

use Amp\ByteStream\ReadableBuffer;
use IMEdge\Protocol\NetString\NetStringReader;

$netString = new NetStringReader(new ReadableBuffer('5:Hello,6:world!,'));
foreach ($netString->packets() as $packet) {
    var_dump($packet);
}
```

#### Output

```
string(5) "Hello"
string(6) "world!"
```

### NetStringWriter

#### Sample Code

```php
<?php

use Amp\ByteStream\WritableBuffer;
use IMEdge\Protocol\NetString\NetStringWriter;

$netString = new NetStringWriter($out = new WritableBuffer());
$netString->write('Hello');
$netString->write(' ');
$netString->write('World!');
$netString->close();
var_dump($out->buffer());
```

#### Output

```
string(21) "5:Hello,1: ,6:World!,"
```


### NetStringConnection

A NetStingConnection allows for bidirectional NetString communication.

#### Sample Code

```php
<?php

use Amp\ByteStream\ReadableBuffer;
use Amp\ByteStream\WritableBuffer;
use IMEdge\Protocol\NetString\NetStringConnection;

$netString = new NetStringConnection(new ReadableBuffer('5:Hello,6:world!,'), $out = new WritableBuffer());
$netString->write('Hi!');
foreach ($netString->packets() as $packet) {
    var_dump($packet);
}
$out->close();
var_dump($out->buffer());
```

#### Output

```
string(5) "Hello"
string(6) "world!"
string(6) "3:Hi!"
```
