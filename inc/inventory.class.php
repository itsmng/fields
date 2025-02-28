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

class PluginFieldsInventory extends CommonDBTM
{
    public static function updateFields($containersData, $itemtype, $items_id)
    {
        if (isset($containersData['ID'])) {
            // $containersData contains only one element, encapsulate it into an array
            $containersData = [$containersData];
        }
        foreach ($containersData as $key => $containerData) {
            $container = new PluginFieldsContainer();
            $container->getFromDB($containerData['ID']);
            $data = [];
            $data["items_id"] = $items_id;
            $data["itemtype"] = $itemtype;
            $data["plugin_fields_containers_id"] = $containerData['ID'];
            foreach ($containerData['FIELDS'] as $key => $value) {
                $data[strtolower($key)] = $value;
            }
            $container->updateFieldsValues($data, $itemtype, false);
        }
    }
}
