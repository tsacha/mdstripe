<?php
/**
 * 2016 DM Productions B.V.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.md
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to info@dmp.nl so we can send you a copy immediately.
 *
 *  @author     DM Productions B.V. <info@dmp.nl>
 *  @copyright  2010-2016 DM Productions B.V.
 *  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__).'/../vendor/autoload.php';

/**
 * Class MdStripeObjectModel
 */
class MdStripeObjectModel extends ObjectModel
{
    /**
     *  Create the database table with its columns. Similar to the createColumn() method.
     *
     * @param string|null $className Class name
     *
     * @return bool Indicates whether the database was successfully added
     */
    public static function createDatabase($className = null)
    {
        if (empty($className)) {
            $className = get_called_class();
        }

        $definition = self::getDefinition($className);
        $sql = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.bqSQL($definition['table']).'` (';
        $sql .= $definition['primary'].' INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,';
        foreach ($definition['fields'] as $fieldName => $field) {
            if ($fieldName === $definition['primary']) {
                continue;
            }
            $sql .= $fieldName.' '.$field['db_type'];
            if (isset($field['required'])) {
                $sql .= ' NOT NULL';
            }
            if (isset($field['default'])) {
                $sql .= ' DEFAULT \''.$field['default'].'\'';
            }
            $sql .= ',';
        }
        $sql = trim($sql, ',');
        $sql .= ')';

        try {
            return (bool) Db::getInstance()->execute($sql);
        } catch (PrestaShopDatabaseException $exception) {
            self::dropDatabase($className);

            return false;
        }
    }

    /**
     * Drop the database for this ObjectModel
     *
     * @param string|null $className Class name
     *
     * @return bool Indicates whether the database was successfully dropped
     */
    public static function dropDatabase($className = null)
    {
        if (empty($className)) {
            $className = get_called_class();
        }

        $definition = ObjectModel::getDefinition($className);
        $sql = 'DROP TABLE IF EXISTS `'._DB_PREFIX_.bqSQL($definition['table']).'`';

        return (bool) Db::getInstance()->execute($sql);
    }

    /**
     * Get columns in database
     *
     * @param string|null $className Class name
     *
     * @return array|false|mysqli_result|null|PDOStatement|resource
     */
    public static function getDatabaseColumns($className = null)
    {
        if (empty($className)) {
            $className = get_called_class();
        }

        $definition = ObjectModel::getDefinition($className);
        $sql = 'SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=\''._DB_NAME_.'\' AND TABLE_NAME=\''._DB_PREFIX_.pSQL($definition['table']).'\'';

        return Db::getInstance()->executeS($sql);
    }

    /**
     * Add a column in the table relative to the ObjectModel.
     * This method uses the $definition property of the ObjectModel,
     * with some extra properties.
     *
     * Example:
     * 'table'        => 'tablename',
     * 'primary'      => 'id',
     * 'fields'       => array(
     *     'id'     => array('type' => self::TYPE_INT, 'validate' => 'isInt'),
     *     'number' => array(
     *         'type'     => self::TYPE_STRING,
     *         'db_type'  => 'varchar(20)',
     *         'required' => true,
     *         'default'  => '25'
     *     ),
     * ),
     *
     * The primary column is created automatically as INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT. The other columns
     * require an extra parameter, with the type of the column in the database.
     *
     * @param string      $name             Column name
     * @param string      $columnDefinition Column type definition
     * @param string|null $className        Class name
     *
     * @return bool Indicates whether the column was successfully created
     */
    public static function createColumn($name, $columnDefinition, $className = null)
    {
        if (empty($className)) {
            $className = get_called_class();
        }

        $definition = self::getDefinition($className);
        $sql = 'ALTER TABLE `'._DB_PREFIX_.bqSQL($definition['table']).'`';
        $sql .= ' ADD COLUMN `'.bqSQL($name).'` '.bqSQL($columnDefinition['db_type']).'';
        if ($name === $definition['primary']) {
            $sql .= ' INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT';
        } else {
            if (isset($columnDefinition['required']) && $columnDefinition['required']) {
                $sql .= ' NOT NULL';
            }
            if (isset($columnDefinition['default'])) {
                $sql .= ' DEFAULT "'.pSQL($columnDefinition['default']).'"';
            }
        }

        return (bool) Db::getInstance()->execute($sql);
    }

    /**
     *  Create in the database every column detailed in the $definition property that are
     *  missing in the database.
     *
     * @param string|null $className Class name
     *
     * @return bool Indicates whether the missing columns were successfully created
     */
    public static function createMissingColumns($className = null)
    {
        if (empty($className)) {
            $className = get_called_class();
        }

        $success = true;

        $definition = self::getDefinition($className);
        $columns = self::getDatabaseColumns();
        foreach ($definition['fields'] as $columnName => $columnDefinition) {
            //column exists in database
            $exists = false;
            foreach ($columns as $column) {
                if ($column['COLUMN_NAME'] === $columnName) {
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                $success &= self::createColumn($columnName, $columnDefinition);
            }
        }

        return $success;
    }
}
