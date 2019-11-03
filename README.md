## Introduction

**Sajya lucene** is a general purpose text search engine written entirely in PHP 5. Since it stores its index on the filesystem and does not require a database server, it can add search capabilities to almost any PHP-driven website.  

###### Supports the following features:

- Ranked searching - best results returned first
- Many powerful query types: phrase queries, boolean queries, wildcard queries, proximity queries, range queries and many others.
- Search by specific field (e.g., title, author, contents)

## Installation

install package via composer

```php
$ composer require sajya/lucene
```


## Test

```bash
php vendor/bin/phpunit
```

## Donate & Support

Since the existence of a healthy open source ecosystem creates real value for the software industry, believe it is fair for maintainers and authors of such software to be compensated for their work with real money.

If you would like to support development by making a donation you can do so [here](https://www.paypal.me/tabuna/10usd). &#x1F60A;


## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
