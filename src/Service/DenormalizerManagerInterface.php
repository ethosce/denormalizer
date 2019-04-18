<?php

namespace Drupal\denormalizer\Service;


interface DenormalizerManagerInterface {

    /**
     * Retrieves content entity types.
     *
     * @return array
     *   The content entity types.
     */
    public function getContentEntityTypes();

    /**
     * Retrieves fields associated to a content entity.
     *
     * @param string $contentEntityMachineName
     *   The content entity machine name.
     * @return array
     *   The content entity fields.
     */
    public function getContentEntityFields(string $contentEntityMachineName);

    /**
     * Retrieves table associated with a particular field name.
     *
     * @param string $fieldMachineName
     *   The field machine name.
     * @return array
     *   The field tables.
     */
    public function getFieldTable(string $fieldMachineName);
}