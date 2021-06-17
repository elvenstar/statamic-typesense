# Statamic Typesense Search Driver

Typesense is a *relatively new* search engine and the driver code may change as new features are developed. This is still very much a beta or preview until the next typesense release when some of the quirks will be fixed.

***Disclaimer: It's a dev release. I would not recommend using this in production until more testing has been done and some core bugs have been resolved.***

### Installation

```bash
composer require elvenstar/statamic-typesense
```

The Typesense library also requires a Guzzle adapter, install the appropriate one according to which Guzzle version that Laravel uses. 

```bash
composer require php-http/guzzle7-adapter
```

Publish the `typesense.php` config file we will use for configuration:

```
php artisan vendor:publish --tag="statamic-typesense"
```

Add the following variables to your env file:

```txt
TYPESENSE_API_KEY=
TYPESENSE_URL=localhost     // For Typesense Cloud use xxx.a1.typesense.net
TYPESENSE_PORT=8108         // For Typesense Cloud use 443
TYPESENSE_PROTOCOL=http     // For Typesense Cloud use https
```

Add the new driver to the `statamic.search` config file:

```php
    'drivers' => [
        
        // other drivers
        
        'typesense' => [
            'credentials' => [
                'api_key' => env('TYPESENSE_API_KEY', ''),
                'nodes' => [
                    'host' => env('TYPESENSE_URL', ''),
                ],
                'port' => env('TYPESENSE_PORT', '443'),
                'protocol' => env('TYPESENSE_PROTOCOL', 'https'),
                'nearest_node' => env('TYPESENSE_NEAREST_NODE', null),
                'connection_timeout' => env('TYPESENSE_TIMEOUT', 2),
            ],
        ],
    ],
```

### Advanced Fields

When you create an index, we will use the `auto` schema by default. This uses a wildcard field with the name `.*` to let Typesense automatically detect the type of the fields. We also have to specify that the ID is a string for each schema so we can use our Statamic UUIDs.

```json
{
  "name": "articles",  
  "fields": [
    {"name": ".*", "type": "auto" },
    {"name":  "id", "type":  "string" }
  ]
}
```

You can find these default settings in the `config/typesense.php` file, and you can add any other default fields (as long as this field exists in every collection!)

If you want to add more advanced options such as facets or sort order, then you can also define these in the `config/typesense.php` file. You can read more about this in the Typesense documentation.

```php
    'schema' => [
        'articles' => [
            'fields' => [
                ['name' => 'synopsis', 'optional' => true],
                ['name' => 'category', 'facet' => true],
                ['name' => 'rating', 'type' => 'int32'],
                ['name' => 'published', 'type' => 'bool'],
                
            ],
            'default_sorting_field' => 'date',
        ],
    ],
```

These options will be merged with the default schema when the index is created on Typesense:

```json
{
  "name": "articles",
  "fields": [
    {"name": ".*", "type": "auto" },
    {"name":  "id", "type":  "string" },
    {"name": "synopsis", "optional": "true"},
    {"name": "category", "facet": "true"},
    {"name": "rating", "type": "int32"},
    {"name": "published", "type":  "bool"},
  ],
  "default_sorting_field": "rating"
}
```

Only the fields you have defined in the `indexes` in the `config/static/search.php` file will be saved to Typesense... so you don't need to worry about including `index: false` in the Schema definitions. If you don't want the field to be included in the search index then don't include it in the `indexes` array.


### Quirks

1. Typesense requires the schema to be defined before inserting data, if you update the blueprint and fields in Statamic -- you'll need to delete the collection on Typesense and create it again with the new schema.
2. Typesense returns all fields data, so if your `content` field is large it can make the search results payload quite large (this should be fixed in the next release).
3. Typesense with multiple-nodes hasn't been tested yet.


### Multisearch Quirks
Multi-search works a bit differently in typesense and it'll return a nested list of results for each index... so you lose a lot of relevancy as you may just get a list of *bad* results from the first index because it was at the top of the list, while the *good* results are not shown because the index was last in the list.

To get around this I recommend keeping a `default` search index that works on the `local` driver, and [connect indexes](https://statamic.dev/search#connecting-indexes) to specific collections using Typesense. 

```php
    'default' => [
        'driver' => 'local',
        'searchables' => 'all',
        'fields' => ['title'],
    ],
```

Then with [connect indexes](https://statamic.dev/search#connecting-indexes) you can use Typesense when you're browsing a specific collection or on the front-end you can use a javascript client to query the results in Typesense.

### Front-end examples
1. [Single-index search](https://gist.github.com/tao/b827a4c3a4c0fad06fa52eee4208f0cc)
2. [Multi-index search](https://gist.github.com/tao/a40862ecc19043e977a6dbb93f13badb)
