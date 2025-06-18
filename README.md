# KISSearch - simple and extensible full text search for Neos

Search with the power of SQL full text queries. No need for additional infrastructure like ElasticSearch or MySQLite.
KISSearch works purely with your existing PostgreSQL, MariaDB or MySQL database.

Search configuration is more or less downwards compatible to Neos.SearchPlugin / SimpleSearch.

Supported Databases:

- MariaDB version >= 10.6
- MySQL version >= 8.0
- PostgreSQL -> supported very soon

## Why KISSearch?

- no additional infrastructure required (like ElasticSearch or MySQLite)
- no explicit index building after changing nodes required, run the SQL migration... that's it :)
- still performant due to full text indexing on database level
- easy to extend with additional search result types (f.e. tables from your database and/or custom flow entities like
  products, etc.)
- comes with Neos Content / Neos Documents as default result types
- search multiple sources with a single, performant SQL query

## Installation

Use case Neos+Flow project:

```
composer require sandstorm/kissearch-neos
```

Use case API only:
```
composer require sandstorm/kissearch-api
```

## Brief architecture overview

- This repository contains **three** packages:
    - `Sandstorm.KISSearch.Api` =>
      Core Package
    - `Sandstorm.KISSearch.Flow` =>
      Framework integration for Flow (read configuration from settings, use CDI for certain cases)
    - `Sandstorm.KISSearch.Neos` =>
      Ships the migration and query builder for full text searching the ContentRepository
- The KISSearch core is designed to be a pure composer library, so you may use KISSearch in a Laravel or Symphony
  project. As you can do with the new ContentRepository of Neos 9.
- Everything is optimized for query time, meaning: there is no need to boot Flow when firing search queries
- KISSearch abstracts the underlying database system to a certain degree (MariaDB, MySQL, Postgres, ...)
- There is a search result type extension API. Utilize this API for searching custom data that lives in your database
  (e.g. products in a shop)
- The Neos Documents search result type comes shipped with this package, internally it uses the search result type
  extension API. This can be seen as reference implementation.
- Each search result type can declare their own additional facette parameters (see f.e.
  the [neos_content parameter section](# neos_content additional parameters))
- Configuration happens via Settings.yaml when using KISSearch in a Flow project.

# Configuration

TODO