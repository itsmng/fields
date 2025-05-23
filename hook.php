<?php

/**
 * -------------------------------------------------------------------------
 * Fields plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of Fields.
 *
 * Fields is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Fields is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Fields. If not, see <http://www.gnu.org/licenses/>.
 * -------------------------------------------------------------------------
 * @copyright Copyright (C) 2013-2022 by Fields plugin team.
 * @license   GPLv2 https://www.gnu.org/licenses/gpl-2.0.html
 * @link      https://github.com/pluginsGLPI/fields
 * -------------------------------------------------------------------------
 */

/**
 * Plugin install process
 *
 * @return boolean
 */
function plugin_fields_install()
{
    global $CFG_GLPI;

    set_time_limit(900);
    ini_set('memory_limit', '2048M');

    $plugin_fields = new Plugin();
    $plugin_fields->getFromDBbyDir('fields');
    $version = $plugin_fields->fields['version'];

    $classesToInstall = [
       'PluginFieldsField',
       'PluginFieldsDropdown',
       'PluginFieldsLabelTranslation',
       'PluginFieldsContainer',
       'PluginFieldsContainer_Field',
       'PluginFieldsValue',
       'PluginFieldsProfile',
       'PluginFieldsMigration'
    ];

    $migration = new Migration($version);
    echo "<center>";
    echo "<table class='tab_cadre_fixe'>";
    echo "<tr><th>".__("MySQL tables installation", "fields")."<th></tr>";

    echo "<tr class='tab_bg_1'>";
    echo "<td align='center'>";

    //load all classes
    $dir  = PLUGINFIELDS_DIR . "/inc/";
    include_once("{$dir}toolbox.class.php");
    foreach ($classesToInstall as $class) {
        if ($plug = isPluginItemType($class)) {
            $item = strtolower($plug['class']);
            if (file_exists("$dir$item.class.php")) {
                include_once("$dir$item.class.php");
            }
        }
    }

    //install
    foreach ($classesToInstall as $class) {
        if ($plug = isPluginItemType($class)) {
            $item = strtolower($plug['class']);
            if (file_exists("$dir$item.class.php")) {
                if (!call_user_func([$class,'install'], $migration, $version)) {
                    return false;
                }
            }
        }
    }

    echo "</td>";
    echo "</tr>";
    echo "</table></center>";

    return true;
}

/**
 * Plugin uninstall process
 *
 * @return boolean
 */
function plugin_fields_uninstall()
{
    global $DB;

    if (!class_exists('PluginFieldsProfile')) {
        Session::addMessageAfterRedirect(
            __("The plugin can't be uninstalled when the plugin is disabled", 'fields'),
            true,
            WARNING,
            true
        );
        return false;
    }

    $_SESSION['uninstall_fields'] = true;

    echo "<center>";
    echo "<table class='tab_cadre_fixe'>";
    echo "<tr><th>".__("MySQL tables uninstallation", "fields")."<th></tr>";

    echo "<tr class='tab_bg_1'>";
    echo "<td align='center'>";

    $classesToUninstall = [
       'PluginFieldsDropdown',
       'PluginFieldsContainer',
       'PluginFieldsContainer_Field',
       'PluginFieldsLabelTranslation',
       'PluginFieldsField',
       'PluginFieldsValue',
       'PluginFieldsProfile',
       'PluginFieldsMigration'
    ];

    foreach ($classesToUninstall as $class) {
        if ($plug = isPluginItemType($class)) {

            $dir  = PLUGINFIELDS_DIR . "/inc/";
            $item = strtolower($plug['class']);

            if (file_exists("$dir$item.class.php")) {
                include_once("$dir$item.class.php");
                if (!call_user_func([$class,'uninstall'])) {
                    return false;
                }
            }
        }
    }

    echo "</td>";
    echo "</tr>";
    echo "</table></center>";

    unset($_SESSION['uninstall_fields']);

    // clean display preferences
    $pref = new DisplayPreference();
    $pref->deleteByCriteria([
       'itemtype' => ['LIKE' , 'PluginFields%']
    ]);

    return true;
}

function plugin_fields_getAddSearchOptions($itemtype)
{
    if (isset($_SESSION['glpiactiveentities'])
        && is_array($_SESSION['glpiactiveentities'])
        && count($_SESSION['glpiactiveentities']) > 0) {

        $itemtypes = PluginFieldsContainer::getEntries('all');
        if ($itemtypes !== false && in_array($itemtype, $itemtypes)) {
            return PluginFieldsContainer::getAddSearchOptions($itemtype);
        }
    }

    return null;
}

// Define Dropdown tables to be manage in GLPI :
function plugin_fields_getDropdown()
{
    $dropdowns = [];

    $field_obj = new PluginFieldsField();
    $fields    = $field_obj->find(['type' => 'dropdown']);
    foreach ($fields as $field) {
        $field['itemtype'] = PluginFieldsField::getType();
        $label = PluginFieldsLabelTranslation::getLabelFor($field);
        $dropdowns["PluginFields".ucfirst($field['name'])."Dropdown"] = $label;
    }

    asort($dropdowns);
    return $dropdowns;
}


/**** MASSIVE ACTIONS ****/

// Display specific massive actions for plugin fields
function plugin_fields_MassiveActionsFieldsDisplay($options = [])
{
    $itemtypes = PluginFieldsContainer::getEntries('all');

    if (in_array($options['itemtype'], $itemtypes)) {
        PluginFieldsField::showSingle($options['itemtype'], $options['options'], true);
        return true;
    }

    // Need to return false on non display item
    return false;
}


/**** RULES ENGINE ****/

function plugin_fields_giveItem($itemtype, $ID, $data, $num)
{
    $searchopt = &Search::getOptions($itemtype);
    $table = $searchopt[$ID]["table"];
    $field = $searchopt[$ID]["field"];

    //fix glpi default Search::giveItem who for empty date display "--"
    if (strpos($table, "glpi_plugin_fields") !== false
        && isset($searchopt[$ID]["datatype"])
        && strpos($searchopt[$ID]["datatype"], "date") !== false
        && empty($data['raw']["ITEM_$num"])) {
        return " ";
    }

    return false;
}

/**
 * Load Fields classes in datainjection.
 * Called by Setup.php:44 if Datainjection is installed and active
**/
function plugin_datainjection_populate_fields()
{
    global $INJECTABLE_TYPES;

    $container = new PluginFieldsContainer();
    $found     = $container->find(['is_active' => 1]);
    foreach ($found as $id => $values) {
        $types = json_decode($values['itemtypes']);

        foreach ($types as $type) {
            $classname = "PluginFields"
                        . ucfirst($type. preg_replace('/s$/', '', $values['name']))
                        . 'Injection';
            $INJECTABLE_TYPES[$classname] = 'fields';
        }
    }
}
