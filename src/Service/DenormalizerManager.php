<?php

namespace Drupal\denormalizer\Service;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\ContentEntityType;

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

    public function __construct(EntityTypeManagerInterface $entityTypeManager, EntityFieldManagerInterface $entityFieldManager) {
        $this->entityTypeManager = $entityTypeManager;
        $this->entityFieldManager = $entityFieldManager;
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
    public function getContentEntityFields(string $contentEntityMachineName) {
        $fields = array();
        $fieldStorageDefinitions = $this->entityFieldManager->getFieldStorageDefinitions($contentEntityMachineName);

        foreach ($fieldStorageDefinitions as $fieldStorageDefinition){
            $fields[] = $fieldStorageDefinition->getName();
        }

        return $fields;
    }

    /**
     * {@inheritdoc}
     */
    public function getFieldTable(string $fieldMachineName) {

    }
}