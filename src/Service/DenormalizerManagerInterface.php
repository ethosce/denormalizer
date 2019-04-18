<?php

namespace Drupal\denormalizer\Service;

/**
 * Defines a set of functionality to create denormalized table for content.
 *
 * @package Drupal\denormalizer\Service
 */
interface DenormalizerManagerInterface {

    /**
     * Retrieves content entity types.
     *
     * @return array
     *   The content entity types.
     */
    public function getContentEntityTypes();

    /**
     * Retrieves field schemas.
     *
     * @param string $contentEntityMachineName
     *   The content entity machine name.
     * @param string $bundle
     *   The bundle.
     * @return array
     *   The content entity fields with their schemas.
     */
    public function getContentEntityFieldSchema(string $contentEntityMachineName, string $bundle = NULL);

    /**
     * Creates denormalized table.
     *
     * @param string $tableName
     *   The name of the denormalized table.
     * @param array $fieldDefinitions
     *   Field definitions that include field name and Schema API definition array.
     * @param string $key
     *   The active connection defined in $databases in settings.php
     * @return boolean
     *   The table creation status.
     */
    public function createDenormalizedTable(string $tableName, array $fieldDefinitions, string $key = 'default');

    /**
     * Creates database if it doesn't exist.
     *
     * @param string $key
     *   The active connection defined in $databases in settings.php
     * @return boolean
     *   The database creation status.
     */
    public function createDatabase(string $key);
}