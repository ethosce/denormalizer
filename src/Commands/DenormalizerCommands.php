<?php

namespace Drupal\denormalizer\Commands;

use Drupal\denormalizer\Service\DenormalizerManagerInterface;
use Drush\Commands\DrushCommands;

/**
 * Class DenormalizerCommands.
 *
 * @package Drupal\denormalizer\Commands
 */
class DenormalizerCommands extends DrushCommands {

    protected $denormalizerManager;

    /**
     * Creates a new denormalizer drush command.
     *
     * @param DenormalizerManagerInterface $denormalizerManager
     *   The denormalizer manager service
     */
    public function __construct(DenormalizerManagerInterface $denormalizerManager) {
        $this->denormalizerManager = $denormalizerManager;
    }

    /**
     * Denormalize tables. Makes a delicious denormalized schema
     *
     * @command denormalizer:denormalize
     * @aliases dnz
     * @options reset Resets tables.
     * @usage drush denormalizer:denormalize --reset
     *   Resets tables.
     */
    public function denormalize($options = ['reset' => false]) {

        print_r($this->denormalizerManager->getContentEntityTypes());

        print_r($this->denormalizerManager->getContentEntityFields('user'));
    }
}