# JSON API
The jsonapi module exposes a [JSON API](http://jsonapi.org/) implementation for data stored in Drupal.

## Installation / configuration

Install the module as every other module. Unlike the core REST module JSON API doesn't really require any kind of configuration by default.

## Usage

The jsonapi module exposes both config and content entity resources. On top of that it exposes one resource per bundle. The list of endpoints then looks like the following:

* `/api/article`: Exposes a collection of article content
* `/api/article/{node}`: Exposes an individual article
* `/api/block`: Exposes a collection of blocks
* `/api/block/{block}`: Exposes an individual block
