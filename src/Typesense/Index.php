<?php

namespace Elvenstar\StatamicTypesense\Typesense;

use Illuminate\Support\Facades\Log;
use Typesense\Client as TypesenseClient;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Support\Arr;
use Statamic\Search\Documents;
use Statamic\Search\Index as BaseIndex;
use Typesense\Exceptions\TypesenseClientError;

class Index extends BaseIndex
{
    protected $client;

    public function __construct(TypesenseClient $client, $name, $config)
    {
        $this->client = $client;

        parent::__construct($name, $config);
    }

    public function search($query)
    {
        return (new Query($this))->query($query);
    }

    public function insert($document)
    {
        $id = $document->id();
        $fields = $this->prepareFields($document);

        $this->getIndex()->documents[$id]->update($fields);
    }

    public function delete($document)
    {
        $id = $document->id();
        $this->getIndex()->documents[$id]->delete();
    }

    public function exists()
    {
        return null !== collect($this->client->collections->retrieve())->first(function ($index) {
            return $index['name'] == $this->name;
        });
    }

    protected function insertDocuments(Documents $documents)
    {
        try {
            return $this->getIndex()->documents->import($documents->all());
        } catch (ConnectException $e) {
            throw new \Exception('Error connecting to Typesense. Check your API credentials.', 0, $e);
        }
    }

    protected function deleteIndex()
    {
        $this->getIndex()->delete();
    }

    public function update()
    {
        $this->deleteIndex();

        // Prepare documents for update
        $searchables = $this->searchables()->all()->map(function($entry) {
           return $this->prepareFields($entry);
        });

        // Create index prior to update
        $this->create();

        // Update documents
        $documents = new Documents($searchables);
        $this->insertDocuments($documents);

        return $this;
    }

    public function searchUsingApi($query)
    {
        try {
            // typesense requires each search to specify the query_by fields,
            // use the fields specified in the config file for each index.
            $fields = config("statamic.search.indexes.{$this->name}.fields");
            $query_by = implode(', ', $fields);

            $search_query = [
                'q'         => $query,
                'query_by'  => $query_by,
            ];

            if (str_contains($query_by, 'content')) {
                $search_query['exclude_fields'] = 'content';
            }

            $response = $this->getIndex()->documents->search($search_query);
        } catch (TypesenseClientError $e) {
            $this->handleTypesenseException($e);
        }

        return collect($response['hits'])->map(function ($hit) {
            $item = $hit['document'];
            $item['reference'] = $hit['document']['id'];

            return $item;
        });
    }

    public function multisearchUsingApi($query)
    {
        try {
            $searchRequests = [
                'searches' => collect(config('statamic.search.indexes'))->map(function ($item, $key) {
                    // typesense requires each search to specify the query_by fields,
                    // use the fields specified in the config file for each index.
                    $query_by = implode(', ', $item['fields']);
                    
                    $search_query = [
                        'collection' => $key,
                        'query_by' => $query_by,
                    ];
                    
                    if (str_contains($query_by, 'content')) {
                        $search_query['exclude_fields'] = 'content';
                    }
                    
                    return $search_query;
                })->values()->all(),
            ];

            // Search parameters that are common to all searches go here
            $commonSearchParams =  [
                'q' => $query,
                'include_fields' => 'id, title',
            ];

            $response = $this->client->multiSearch->perform($searchRequests, $commonSearchParams);
        } catch (TypesenseClientError $e) {
            $this->handleTypesenseException($e);
        }

        $response = collect($response['results'])
            ->map(function ($result) {
                if (array_key_exists('found', $result)) {
                    return $result['hits'];
                }
            })
            ->flatten(1)
            ->map(function ($hit) {
                try {
                    $item = $hit['document'];
                    $item['reference'] = $item['id'];
                    return $item;
                } catch (\Exception $e) {
                    return; // ignore errors
                }
            })->take(10);

        return $response;
    }

    private function getIndex()
    {
        return $this->client->collections[$this->name];
    }

    private function create()
    {
        $schema = config('typesense.schema');
        $fields = config('typesense.defaults.fields');
        $name = $this->name;

        // create basic index schema
        $index = compact('name');

        // Merge top-level configuration
        $index = array_merge($index, $schema[$name] ?? []);

        // Merge fields into index
        $index['fields'] = array_merge($fields, $schema[$name]['fields'] ?? []);

        // Create index
        $this->client->collections->create($index);
    }

    private function prepareFields($entry)
    {
        $fields = $this->searchables()->fields($entry);

        // remove null values (required until typesense 0.21 release)
        $fields = array_filter($fields, function($a) { return $a !== null; });

        return $fields;
    }

    private function handleTypesenseException($e)
    {
        // TODO: custom error parsing for typesense exceptions

        throw $e;
    }
}
