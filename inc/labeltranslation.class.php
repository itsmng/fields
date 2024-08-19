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

class PluginFieldsLabelTranslation extends CommonDBTM {
   static $rightname = 'config';

   static function canCreate() {
      return self::canUpdate();
   }

   static function canPurge() {
      return self::canUpdate();
   }

   /**
    * Install or update fields
    *
    * @param Migration $migration Migration instance
    * @param string    $version   Plugin current version
    *
    * @return boolean
    */
   static function install(Migration $migration, $version) {
      global $DB;

      $table = self::getTable();

      if (!$DB->tableExists($table)) {
         $migration->displayMessage(sprintf(__("Installing %s"), $table));

         $query = "CREATE TABLE IF NOT EXISTS `$table` (
                  `id`                         INT(11)       NOT NULL auto_increment,
                  `plugin_fields_itemtype`     VARCHAR(30)  NOT NULL,
                  `plugin_fields_items_id`     INT(11)      NOT NULL,
                  `language`                   VARCHAR(5)   NOT NULL,
                  `label`                      VARCHAR(255) DEFAULT NULL,
                  PRIMARY KEY                  (`id`),
                  KEY `plugin_fields_itemtype` (`plugin_fields_itemtype`),
                  KEY `plugin_fields_items_id` (`plugin_fields_items_id`),
                  KEY `language`               (`language`),
                  UNIQUE KEY `unicity` (`plugin_fields_itemtype`, `plugin_fields_items_id`, `language`)
               ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
            $DB->query($query) or die ($DB->error());
      }

      $migration->displayMessage("Updating $table");
      $migration->executeMigration();
      return true;
   }

   static function uninstall() {
      global $DB;

      $DB->query("DROP TABLE IF EXISTS `".self::getTable()."`");

      return true;
   }

   static function getTypeName($nb = 0) {
      return _n("Translation", "Translations", $nb);
   }

   static function createForItem(CommonDBTM $item) {

      $translation = new PluginFieldsLabelTranslation();
      $translation->add([
         'plugin_fields_itemtype' => $item::getType(),
         'plugin_fields_items_id' => $item->getID(),
         'language'               => $_SESSION['glpilanguage'],
         'label'                  => $item->fields['label']
      ]);
      return true;
   }

   function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
      $nb = countElementsInTable(
         self::getTable(),
         [
            'plugin_fields_itemtype' => $item::getType(),
            'plugin_fields_items_id' => $item->getID(),
         ]
      );
      return self::createTabEntry(self::getTypeName($nb), $nb);

   }

   static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
       self::showTranslations($item);
   }

   /**
    * Display all translations for a label
    *
    * @param CommonDBTM $item Item instance
    *
    * @return void
   **/
   static function showTranslations(CommonDBTM $item) {
      global $DB, $CFG_GLPI;

      $canedit = $item->can($item->getID(), UPDATE);
      $rand    = mt_rand();
      if ($canedit) {
         echo "<div id='viewtranslation".$item->getID()."$rand'></div>";

         echo Html::scriptBlock('
            addTranslation' . $item->getID() . $rand . ' = function() {
               $("#viewtranslation' . $item->getID() . $rand . '").load(
                  "' . Plugin::getWebDir('fields') . '/ajax/viewtranslations.php",
                  ' . json_encode([
                     'type'     => __CLASS__,
                     'itemtype' => $item::getType(),
                     'items_id' => $item->fields['id'],
                     'id'       => -1
                  ]) . '
               );
            };
         ');

         echo "<div class='center'>".
              "<a class='btn btn-sm btn-secondary' href='javascript:addTranslation".$item->getID()."$rand();'>".
              __('Add a new translation')."</a></div><br>";
      }

      $obj   = new self;
      $found = $obj->find(
         [
            'plugin_fields_itemtype' => $item::getType(),
            'plugin_fields_items_id' => $item->getID(),
         ],
         "language ASC"
      );

      if (count($found) > 0) {
         if ($canedit) {
            $massiveactionparams = [
                'container' => 'mass'.__CLASS__.$rand,
                'display_arrow' => false,
            ];
            Html::showMassiveActions($massiveactionparams);
         }
         $fields = [
            'language' => __("Language", "fields"),
            'label'    => __("Label", "fields"),
         ];
         $values = [];
         $massive_action = [];
         foreach ($found as $data) {
            if ($canedit) {
                $massive_action[] = sprintf('item[%s][%s]', self::class, $data["id"]);
            }
            if ($canedit) {
               echo Html::scriptBlock('
                  viewEditTranslation' . $data['id'] . $rand . ' = function() {
                     $("#viewtranslation' . $item->getID() . $rand . '").load(
                        "' . Plugin::getWebDir('fields') . '/ajax/viewtranslations.php",
                        ' . json_encode([
                           'type'     => __CLASS__,
                           'itemtype' => $item::getType(),
                           'items_id' => $item->getID(),
                           'id'       => $data['id']
                        ]) . '
                     );
                  };
               ');
            }
            $values[] = [
                'language' => Dropdown::getLanguageName($data['language']),
                'label'    => '<a href="javascript:viewEditTranslation'.$data['id'].$rand.'();">'.$data['label'].'</a>',
            ];
         }
         renderTwigTemplate('table.twig', [
            'id'         => 'mass'.__CLASS__.$rand,
            'fields'     => $fields,
            'values'     => $values,
            'massive_action' => $massive_action,
         ]);
      } else {
         echo "<table class='tab_cadre_fixe'><tr class='tab_bg_2'>";
         echo "<th class='b'>".__("No translation found")."</th></tr></table>";
      }

      return true;
   }

   /**
    * Display translation form
    *
    * @param string $itemtype Item type
    * @param int    $items_id Item ID
    * @param innt   $id       Translation ID (defaults to -1)
    *
    * @return void
    */
   function showForm($itemtype, $items_id, $id = -1) {
      global $CFG_GLPI;

      if ($id > 0) {
         $this->check($id, READ);
      } else {
         // Create item
         $this->check(-1, CREATE);

      }
      $this->showFormHeader();
      echo "<tr class='tab_bg_1'>";
      echo "<td>".__('Language')."&nbsp;:</td>";
      echo "<td>";
      echo "<input type='hidden' name='plugin_fields_itemtype' value='{$itemtype}'>";
      echo "<input type='hidden' name='plugin_fields_items_id' value='{$items_id}'>";
      if ($id > 0) {
         echo Dropdown::getLanguageName($this->fields['language']);
      } else {
         Dropdown::showLanguages("language",
                                 ['display_none' => false,
                                  'value'        => $_SESSION['glpilanguage'],
                                  'used'         => self::getAlreadyTranslatedForItem($itemtype, $items_id)]);
      }
      echo "</td><td colspan='2'>&nbsp;</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td><label for='label'>".__('Label')."</label></td>";
      echo "<td colspan='3'>";
      echo Html::input('label', [
         'value' => $this->fields["label"],
         'id'    => 'label'
      ]);
      echo "</td></tr>";

      $this->showFormButtons();
      return true;
   }

   /**
    * Get already translated languages for item
    *
    * @param string $itemtype Item type
    * @param int    $items_id Item ID
    *
    * @return array of already translated languages
   **/
   static function getAlreadyTranslatedForItem($itemtype, $items_id) {
      global $DB;

      $tab = [];
      foreach ($DB->request(self::getTable(),
                            "`plugin_fields_itemtype` = '$itemtype' AND
                             `plugin_fields_items_id` = '$items_id'") as $data) {
         $tab[$data['language']] = $data['language'];
      }
      return $tab;
   }

   /**
    * Get trnaslated label for item
    *
    * @param array $item Item
    *
    * @return string
    */
   static public function getLabelFor(array $item) {
      $obj   = new self;
      $found = $obj->find(['plugin_fields_itemtype' => $item['itemtype'],
                           'plugin_fields_items_id' => $item['id'],
                           'language' => $_SESSION['glpilanguage']]);

      if (count($found) > 0) {
         return array_values($found)[0]['label'];
      }

      return $item['label'];
   }
}
