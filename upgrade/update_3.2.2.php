<?php
/**
 * Copyright 2020 Lengow SAS.
 *
 * Licensed under the Apache License, Version 2.0 (the "License"); you may
 * not use this file except in compliance with the License. You may obtain
 * a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
 * License for the specific language governing permissions and limitations
 * under the License.
 *
 * @author    Team Connector <team-connector@lengow.com>
 * @copyright 2018 Lengow SAS
 * @license   http://www.apache.org/licenses/LICENSE-2.0
 */

if (!LengowInstall::isInstallationInProgress()) {
    exit();
}

// *********************************************************
//                     lengow_orders
// *********************************************************

if (LengowInstall::checkTableExists(LengowOrder::TABLE_ORDER)) {
    if (!LengowInstall::checkFieldExists(LengowOrder::TABLE_ORDER, LengowOrder::FIELD_ORDER_TYPES)) {
        Db::getInstance()->execute('ALTER TABLE ' . _DB_PREFIX_ . 'lengow_orders ADD `order_types` TEXT NULL');
    }
}
