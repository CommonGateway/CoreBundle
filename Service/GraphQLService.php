<?php

namespace CommonGateway\CoreBundle\Service;

use MongoDB\Client as MongoDBClient;
use Overblog\GraphQLBundle\Request\Executor as GraphQLExecutor;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @Author Conduction <conduction@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Service
 */
class GraphQLService
{
    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var MongoDBClient
     */
    private $mongoDbClient;

    /**
     * @var GraphQLExecutor
     */
    private $graphQLExecutor;

    /**
     * Defautl symfony constructor
     *
     * @param RequestStack $requestStack
     * @param MongoDBClient $mongoDbClient
     * @param GraphQLExecutor $graphQLExecutor
     */
    public function __construct(RequestStack $requestStack, MongoDBClient $mongoDbClient, GraphQLExecutor $graphQLExecutor)
    {
        $this->requestStack = $requestStack;
        $this->mongoDbClient = $mongoDbClient;
        $this->graphQLExecutor = $graphQLExecutor;
    }

    /**
     * This function executes a GraphQL request against the mango db
     *
     * @return mixed
     */
    public function execute()
    {
        // Request sould just be passed to this function
        $request = $this->requestStack->getCurrentRequest();
        $graphQLQuery = json_decode($request->getContent(), true);

        // Check if the query is an introspection query
        if (isset($graphQLQuery['__schema']) || isset($graphQLQuery['__type'])) {
            return $this->introspection($graphQLQuery);
        }

        // Here, we need to translate the GraphQL query to MongoDB query. This is a complex task and
        // depends heavily on your specific schema. For simplicity, let's assume that the query is
        // already in a form that MongoDB can understand.
        $mongoDbQuery = $graphQLQuery;

        $collection = $this->mongoDbClient->selectCollection('yourDatabase', 'yourCollection');
        $cursor = $collection->find($mongoDbQuery);

        // Translate MongoDB result into a format that GraphQL can understand
        $graphQLResult = [];
        foreach ($cursor as $document) {
            $graphQLResult[] = $document;
        }

        // Return the result
        return $this->graphQLExecutor->execute('yourSchema', null, $graphQLResult);
    }


    /**
     * Introspections are the documetnations of graph ql, also see https://graphql.org/learn/introspection/
     *
     * @return void
     */
    public function introspection(array $graphQLQuery):array
    {
        // How to do this

        return [];
    }


}
