<?php

namespace Drupal\denormalizer;

class Denormalizer {

  // The DN tables to be exported/created.
  private $dw_tables = array();

  /**
   * Generate all SQL for views.
   *
   * @return array
   *   Array of SQL select statements.
   */
  function build() {
    global $databases;
    $other_database = $databases['default']['default'];
    $other_database['prefix'] = '';
    Database::addConnectionInfo('external', 'default', $other_database);

    $d = denormalizer_get_info();

    foreach ($d as $denormalizer_view => $dn_info) {
      $this->add($denormalizer_view, $dn_info);
    }
  }

  /**
   * Create a view.
   *
   * @param string $denormalizer_view
   *   Name of view to create.
   * @param string $entity_type
   *   Entity type to create from.
   * @param array<string> $bundles
   *   Bundles to include. Blank for all bundles (all entity fields).
   *
   * @return string
   *   SQL to use to pull denormalized data or create a view.
   */
  function generateQuery($denormalizer_view, $dn_info) {
    $schema = drupal_get_schema();

    module_load_include('inc', 'entity', 'entity.info');
    $property_info = array();
    if (isset($dn_info['entity_type']) && $property_info = entity_get_property_info($dn_info['entity_type'])) {
      $property_info = entity_get_property_info($dn_info['entity_type'])['properties'];
    }
    else if (empty($dn_info['external'])) {
      $property_info = entity_metadata_convert_schema($dn_info['base table']);
    }

    $q = db_select($dn_info['base table'], "dn_$denormalizer_view");

    if (!empty($dn_info['external'])) {
      $q->fields("dn_$denormalizer_view");
    }

    $properties_columns = array();
    foreach ($property_info as $property => $info) {
      if (isset($info['schema field'])) {
        if ($info['schema field'] == 'vuuid') {
          // ??? this column does not exist
          continue;
        }

        if (!empty($schema[$dn_info['base table']]['fields'][$info['schema field']]['serialize'])) {
          // No serialized fields.
          continue;
        }

        $properties_columns[$info['schema field']] = $info['schema field'];
        if (isset($info['type']) && $info['type'] == 'date') {
          $q->addExpression("from_unixtime(dn_$denormalizer_view.{$info['schema field']})", "{$info['schema field']}");
        }
        else {
          $q->fields('dn_' . $denormalizer_view, array($info['schema field']));
        }
      }
    }

    // Track fields we already processed.
    $used = array();
    if (isset($dn_info['entity_type']) && $dn_info['fields'] !== FALSE) {
      $entity_type = $dn_info['entity_type'];
      $entity_info = entity_get_info($entity_type);
      $field_info = field_info_fields();
      $instance_info = field_info_instances($dn_info['entity_type']);
      $bundles = $dn_info['bundles'];

      foreach ($instance_info as $bundle => $fields) {
        if (empty($bundles) || in_array($bundle, $bundles)) {
          // All bundles or specific bundle?
          foreach ($fields as $field_name => $info) {
            // We don't need to add fields multiple times for different bundles.
            if (!isset($used[$field_name])) {
              $used[$field_name] = $field_name;
              // Just single select fields for now.
              if ($field_info[$field_name]['cardinality'] == 1) {
                $cols = array();


                if ($field_info[$field_name]['type'] == 'date') {
                  // "Date" time, we need to special handle :( Cast into a real
                  // datetime so ETL tools can pick it up.
                  $q->addJoin('LEFT', 'field_data_' . $field_name, $field_name, "$field_name.entity_type = '$entity_type' AND $field_name.entity_id = dn_$denormalizer_view.{$entity_info['entity keys']['id']}", array());
                  $col1 = $field_name . '_' . 'value';
                  $q->addExpression("cast($col1 as datetime)", $col1);
                  if (!empty($field_info[$field_name]['settings']['todate'])) {
                    $col2 = $field_name . '_' . 'value2';
                    $q->addExpression("cast($col2 as datetime)", $col2);
                  }
                  // Skip this field.
                  continue;
                }

                foreach ($field_info[$field_name]['columns'] as $column => $info) {
                  // No full text fields.
                  if ($field_info[$field_name]['type'] == 'text_long' || $field_info[$field_name]['type'] == 'text_with_summary') {
                    continue;
                  }

                  $cols[] = $field_name . '_' . $column;
                }

                if (!empty($cols)) {
                  // Join field table. Add columns to query.
                  $q->addJoin('LEFT', 'field_data_' . $field_name, $field_name, "$field_name.entity_type = '$entity_type' AND $field_name.entity_id = dn_$denormalizer_view.{$entity_info['entity keys']['id']}", array());
                  $q->fields($field_name, $cols);
                }
              }
              elseif ($field_info[$field_name]['type'] == 'taxonomy_term_reference') {
                // Handle taxonomy terms. We get the values and concatenate them into one column.
                $q->addJoin('LEFT', 'field_data_' . $field_name, $field_name, "$field_name.entity_type = '$entity_type' AND $field_name.entity_id = dn_$denormalizer_view.{$entity_info['entity keys']['id']}", array());
                $q->addJoin('LEFT', 'taxonomy_term_data', "{$field_name}_tax", "{$field_name}_tid = {$field_name}_tax.tid");
                $q->addExpression("group_concat(distinct {$field_name}_tax.name SEPARATOR '|')", "{$field_name}_tid");
                // Group by primary key, so group_concat will work.
                $q->groupBy("`dn_$denormalizer_view`.{$entity_info['entity keys']['id']}");
              }
              elseif ($field_info[$field_name]['cardinality'] != 1) {
                if (in_array($field_info[$field_name]['module'], array('list'))) {
                  $q->addJoin('LEFT', 'field_data_' . $field_name, $field_name, "$field_name.entity_type = '$entity_type' AND $field_name.entity_id = dn_$denormalizer_view.{$entity_info['entity keys']['id']}", array());
                  $q->addExpression("group_concat(distinct {$field_name}_value SEPARATOR '|')", "{$field_name}_value");
                  // Group by primary key, so group_concat will work.
                  $q->groupBy("`dn_$denormalizer_view`.{$entity_info['entity keys']['id']}");
                }
              }
            }
          }
        }
      }

      // Filter on bundle.
      if (count($bundles) > 0) {
        $q->condition("dn_$denormalizer_view.{$entity_info['entity keys']['bundle']}", $bundles);
      }
    }

    return $q;
  }

  /**
   * Add a table to the denormalizer...list?
   *
   * @param type $denormalizer_view
   *   Name of the view.
   *
   * @param array $dn_info
   *   Info bit from hook_denormalizer_info().
   */
  function add($denormalizer_view, $dn_info) {
    $query = $this->generateQuery($denormalizer_view, $dn_info);
    $this->dw_tables[$denormalizer_view] = $query;
  }

  /**
   * Create all database views.
   *
   * @param bool $reset
   *   Whether or not to drop and recreate tables.
   */
  function execute($reset = FALSE) {
    $prefix = variable_get('denormalizer_view_prefix', 'snowflake_');
    $db_prefix = '';
    $db_target = denormalizer_target_db();

    if ($db = denormalizer_target_db()) {
      $db_prefix = "{$db}.";
      $target_exists = db_query('SELECT 1 FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = :name', array(':name' => $db_target))->fetchField();
      if ($reset) {
        if ($target_exists) {
          db_query("DROP DATABASE $db_target");
          drupal_set_message(t('Dropped %target database.', array('%target' => $db_target)));
          $target_exists = FALSE;
        }
      }

      if (!$target_exists) {
        db_query("CREATE DATABASE $db_target");
        drupal_set_message(t('Created %target database.', array('%target' => $db_target)));
      }
    }

    if (variable_get('denormalizer_sql_mode', 'views') == 'views') {
      $type = 'VIEW';
    }
    else {
      $type = 'TABLE';
    }

    $out = array();
    $dn_info = denormalizer_get_info();
    $all_start = microtime(TRUE);

    // Hack to get around https://stackoverflow.com/questions/36882149/error-1067-42000-invalid-default-value-for-created-at
    // Some tables have a CURRENT_TIMESTAMP so the derived table gets a zero date.
    db_query("SET sql_mode = 'REAL_AS_FLOAT,PIPES_AS_CONCAT,ANSI_QUOTES,IGNORE_SPACE,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER'");

    // A little more flexibility for handling multiple values.
    $group_concat_max_len = intval(variable_get('denormalizer_group_concat_max_len', 16384));
    db_query("SET group_concat_max_len = $group_concat_max_len");

    foreach ($this->dw_tables as $denormalizer_view => $final_q) {
      $target = "{$db_prefix}" . "`$prefix$denormalizer_view`";
      $start = microtime(TRUE);
      $id_key = denormalizer_get_primary_key($denormalizer_view);

      $reset = ($type == 'VIEW') || !db_query('SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :name', array(':schema' => $db_target, ':name' => $denormalizer_view))->fetchField();

      if (empty($reset)) {
        if (!variable_get('denormalizer_max_changed_' . $denormalizer_view)) {
          // No reset, but we can't reload.
          $count = db_query("SELECT COUNT(*) FROM $target")->fetchField();
          if ($count > 0) {
            db_query("TRUNCATE $target");
            $out[] = t('Emptied :target.', array(':target' => $target));
          }
        }
        else {
          if (isset($dn_info[$denormalizer_view]['changed_key']) && variable_get('denormalizer_max_changed_' . $denormalizer_view)) {
            // If there is a last changed key, only replicate records that have changed.
            $final_q->havingCondition($dn_info[$denormalizer_view]['changed_key'], variable_get('denormalizer_max_changed_' . $denormalizer_view), '>');
          }
        }

        // Allow altering query before update.
        drupal_alter('denormalizer', $final_q, $denormalizer_view, $dn_info[$denormalizer_view]);

        if (!empty($dn_info[$denormalizer_view]['external'])) {
          db_set_active('external');
        }

        $final_sql = denormalizer_dpq($final_q);
        db_set_active();
        try {
          $affected = db_query("REPLACE INTO {$db_prefix}$prefix{$denormalizer_view} $final_sql")->rowCount();
        } catch (Exception $e) {
          drupal_set_message(t('Could not denormalize @table: @message', array('@table' => $denormalizer_view, '@message' => $e->getMessage())), 'error');
        }
        $end = microtime(TRUE);
        $time = round($end - $start, 2);
        $out[] = t('Loaded @records to %view in @time seconds.', array('%view' => "{$db_prefix}$prefix{$denormalizer_view}", '@records' => $affected, '@time' => $time));
      }
      else {
        db_query("DROP TABLE IF EXISTS {$db_prefix}$prefix$denormalizer_view");
        db_query("DROP VIEW IF EXISTS {$db_prefix}$prefix$denormalizer_view");
        //$out[] = t('Dropped %name.', array('%name' => "{$db_prefix}$prefix$denormalizer_view"));

        if (!empty($dn_info[$denormalizer_view]['external'])) {
          db_set_active('external');
        }
        // Allow altering query before insert.
        drupal_alter('denormalizer', $final_q, $denormalizer_view, $dn_info[$denormalizer_view]);
        $final_sql = denormalizer_dpq($final_q);
        db_set_active();

        if ($type == 'TABLE') {
          // Create an empty table.
          // https://www.percona.com/blog/2018/01/10/why-avoid-create-table-as-select-statement/
          db_query("CREATE $type $target CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE = MYISAM AS $final_sql LIMIT 0")->rowCount();
          if ($id_key) {
            db_query("ALTER TABLE $target ADD PRIMARY KEY $id_key ($id_key)");
          }
          // Insert records into it.
          try {
            $affected = db_query("INSERT INTO $target $final_sql")->rowCount();
          } catch (Exception $e) {
            drupal_set_message(t('Could not denormalize @table: @message', array('@table' => $denormalizer_view, '@message' => $e->getMessage())), 'error');
          }
        }
        elseif ($type == 'VIEW') {
          try {
            $affected = db_query("CREATE $type $target AS $final_sql")->rowCount();
          } catch (Exception $e) {
            drupal_set_message(t('Could not denormalize @table: @message', array('@table' => $denormalizer_view, '@message' => $e->getMessage())), 'error');
          }
        }

        $end = microtime(TRUE);
        $time = round($end - $start, 2);
        $out[] = t('Created @type %view with @records records in @time seconds.', array('@type' => $type, '%view' => "{$db_prefix}$prefix{$denormalizer_view}", '@records' => $affected, '@time' => $time));
      }

      // Log the last ID and last changed value.
      if (isset($dn_info[$denormalizer_view]['base table'])) {
        $base_table = $dn_info[$denormalizer_view]['base table'];
      }
      else {
        $base_table = entity_get_info($dn_info[$denormalizer_view]['entity_type'])['base table'];
      }

      if ($type == 'TABLE') {
        if (isset($dn_info[$denormalizer_view]['changed_key'])) {
          if (!empty($dn_info[$denormalizer_view]['external'])) {
            db_set_active('external');
          }
          $changed = db_query("SELECT max({$dn_info[$denormalizer_view]['changed_key']}) from $target")->fetchField();
          db_set_active();
          variable_set('denormalizer_max_changed_' . $denormalizer_view, $changed);
        }
      }

      module_invoke_all('denormalizer_post_execute', $denormalizer_view, $dn_info[$denormalizer_view]);
    }
    $all_end = microtime(TRUE);
    $all_time = round($all_end - $all_start, 2);
    $out[] = t('Created all in @time seconds.', array('@time' => $all_time));
    drupal_set_message(implode("<br/>", $out));
  }

  /**
   * Show full SQL used to create all database items.
   */
  function getSql() {
    $db_source = denormalizer_source_db();
    $sql = '';


    if (variable_get('denormalizer_db') == 'external') {
      $db_target = denormalizer_target_db();
      $db_prefix = "{$db_target}.";
      $sql .= "CREATE DATABASE IF NOT EXISTS $db_target;\n\n";
    }


    if (variable_get('denormalizer_sql_mode', 'views') == 'views') {
      $type = 'VIEW';
    }
    else {
      $type = 'TABLE';
    }

    $prefix = variable_get('denormalizer_view_prefix', 'snowflake_');



    $dn_info = denormalizer_get_info();
    foreach ($this->dw_tables as $denormalizer_view => $final_q) {
      if (!empty($dn_info[$denormalizer_view]['external'])) {
        db_set_active('external');
      }

      // Allow altering query before insert.
      drupal_alter('denormalizer', $final_q, $denormalizer_view, $dn_info[$denormalizer_view]);

      $final_sql = denormalizer_dpq($final_q);
      $target = "{$db_prefix}{$prefix}{$denormalizer_view}";
      // Drop views and tables.
      $sql .= "DROP TABLE IF EXISTS $target;\n";
      $sql .= "DROP VIEW IF EXISTS $target;\n";
      $sql .= "CREATE $type $target AS $final_sql;\n";
      if ($key = denormalizer_get_primary_key($denormalizer_view)) {
        $sql .= "ALTER TABLE $target ADD PRIMARY KEY $key ($key);\n\n";
      }
      // Reset to default DB.
      db_set_active();
    }

    $page = array();

    $page['header']['#markup'] = t('You can use the SQL below to generate database views of denormalized data on a copy of this database. This uses the entity property and field metadata from the current site, so queries may fail if the data is different.');

    $page['sql']['#type'] = 'textarea';
    $page['sql']['#rows'] = '100';
    $page['sql']['#value'] = $sql;

    return drupal_render($page);
  }

}
