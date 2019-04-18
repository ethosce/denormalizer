<?php

namespace Drupal\denormalizer\Service;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\ContentEntityType;
use Drupal\Core\Database\Connection;

/**
 * Class DenormalizerManager
 *
 * @package Drupal\denormalizer\Service
 */
class DenormalizerManager implements DenormalizerManagerInterface {
    /**
     * The entity type service.
     *
     * @var EntityTypeManagerInterface
     */
    protected $entityTypeManager;
    /**
     * The entity field manager service.
     *
     * @var EntityFieldManagerInterface
     */
    protected $entityFieldManager;
    /**
     * The database connection service.
     *
     * @var Connection
     */
    protected $connection;

    public function __construct(EntityTypeManagerInterface $entityTypeManager, EntityFieldManagerInterface $entityFieldManager, Connection $connection) {
        $this->entityTypeManager = $entityTypeManager;
        $this->entityFieldManager = $entityFieldManager;
        $this->connection = $connection;
    }

    /**
     * {@inheritdoc}
     */
    public function getContentEntityTypes() {
        $contentEntityTypes = [];
        $entityTypeDefinitions = $this->entityTypeManager->getDefinitions();

        /* @var $definition EntityTypeInterface */
        foreach ($entityTypeDefinitions as $definition) {
            if ($definition instanceof ContentEntityType) {
                $contentEntityTypes[] = $definition->id();
            }
        }

        return $contentEntityTypes;
    }

    /**
     * {@inheritdoc}
     */
    public function getContentEntityFieldSchema(string $contentEntityMachineName, string $bundle = NULL) {
        $fields = array();
        if ($contentEntityMachineName == 'node' ){
            if (!isset($bundle)){
                throw new \Exception('Missing bundle for Node Entity.');
            }
            $fieldDefinitions = $this->entityFieldManager->getFieldDefinitions('node', $bundle);

            foreach ($fieldDefinitions as $fieldDefinition){
                $schema = $fieldDefinition->getFieldStorageDefinition()->getSchema()['columns'];
                $fields[] = [
                    'name' => $fieldDefinition->getName(),
                    //'type' => $fieldDefinition->getType(),
                    'schema' => $fieldDefinition->getType() == 'entity_reference'? $schema['target_id']:$schema['value']
                ];
            }
            return $fields;
        }

        $fieldStorageDefinitions = $this->entityFieldManager->getFieldStorageDefinitions($contentEntityMachineName);

        foreach ($fieldStorageDefinitions as $fieldStorageDefinition){
            $schema = $fieldStorageDefinition->getSchema()['columns'];
            $fields[] = [
                'name' => $fieldStorageDefinition->getName(),
                'schema' => $fieldStorageDefinition->getType() == 'entity_reference'? $schema['target_id']:$schema['value']
            ];
        }

        return $fields;
    }

    /**
     * {@inheritdoc}
     */
    public function createDenormalizedTable(string $tableName, array $fieldDefinitions, string $key = 'default') {

        $schema[$tableName] = array(
            'fields' => array()
        );
        foreach ($fieldDefinitions as $fieldDefinition){
            if (isset($fieldDefinition['schema'])){
                $schema[$tableName]['fields'][$fieldDefinition['name']] = $fieldDefinition['schema'];
            }
        }
        //\Drupal\Core\Database\Database::setActiveConnection($key);
        $this->connection->schema()->createTable($tableName, $schema[$tableName]);
    }

    /**
     * {@inheritdoc}
     */
    public function createDatabase(string $name) {
        $this->connection->createDatabase($name);
    }
}