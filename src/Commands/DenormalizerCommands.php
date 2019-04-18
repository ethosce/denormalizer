<?php

namespace Drupal\denormalizer\Commands;

use Drush\Commands\DrushCommands;

class DenormalizerCommands extends DrushCommands {

    public function __construct() {

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
    public function hello($options = ['reset' => false]) {
        $this->output()->writeln('Resets tables.');
    }
}