<?php

namespace CommonGateway\CoreBundle\Service\Cache;
/**
 * Interface to unify collections between data stores.
 *
 * @Author Ruben van der Linde <ruben@conduction.nl>, Wilco Louwerse <wilco@conduction.nl>, Robert Zondervan <robert@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category DataStore
 */
interface CollectionInterface
{
    /**
     * Aggregates search results to gain data about possible filters.
     *
     * @param  array $pipeline The pipeline to generate aggregations for.
     * @param  array $options  The options to be used in aggregation.
     * @return \Iterator
     */
    public function aggregate(array $pipeline, array $options = []): \Iterator;

    /**
     * Count the number of results that match the given filter.
     *
     * @param  array $filter  The filters used to determine the number of results.
     * @param  array $options The options used in the operation.
     * @return int
     */
    public function count(array $filter = [], array $options = []): int;

    /**
     * Create an index.
     *
     * @param  array|object $key     The key to generate an index for.
     * @param  array        $options Options to run in building the index.
     * @return string
     */
    public function createIndex(array|object $key, array $options = []): string;

    /**
     * Create a search index.
     *
     * @param  array|object $definition The definition of the search index.
     * @param  array        $options    Options to run in building the index.
     * @return string
     */
    public function createSearchIndex(array|object $definition, array $options = []): string;

    /**
     * Finds objects matching filter.
     *
     * @param  array $filter  The filters used in filtering results.
     * @param  array $options Options for calculating the results.
     * @return \Iterator
     */
    public function find(array $filter = [], array $options = []): \Iterator;

    /**
     * Finds one object matching filter (usually _id)
     *
     * @param  array $filter  The filters used in filtering results.
     * @param  array $options Options for calculating the results.
     * @return array|object|null
     */
    public function findOne(array $filter = [], array $options = []): array | null | object;

    /**
     * Finds one object (by _id) and delete it.
     *
     * @param  array $filter  The filters used in filtering results.
     * @param  array $options Options for calculating the results.
     * @return array|object|null
     */
    public function findOneAndDelete(array $filter = [], array $options = []): array|null|object;

    /**
     * Finds one object (by _id), and if it exists, replace it. If it does not exist, create it.
     *
     * @param  array|object $filter      The filters used in filtering results.
     * @param  array|object $replacement The object that should replace the object (or that should be added).
     * @param  array        $options     The options for the replacement command.
     * @return array|object|null
     */
    public function findOneAndReplace(array|object $filter, array|object $replacement, array $options = []): array|null|object;
}//end interface
