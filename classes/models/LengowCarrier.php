<?php
/**
 * Copyright 2021 Lengow SAS.
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
 * @copyright 2021 Lengow SAS
 * @license   http://www.apache.org/licenses/LICENSE-2.0
 */

/**
 * Lengow Carrier Class
 */
class LengowCarrier extends Carrier
{
    /**
     * @var string Lengow carrier marketplace table name
     */
    const TABLE_CARRIER_MARKETPLACE = 'lengow_carrier_marketplace';

    /**
     * @var string Lengow default carrier table name
     */
    const TABLE_DEFAULT_CARRIER = 'lengow_default_carrier';

    /**
     * @var string Lengow marketplace carrier marketplace table name
     */
    const TABLE_MARKETPLACE_CARRIER_MARKETPLACE = 'lengow_marketplace_carrier_marketplace';

    /**
     * @var string Lengow marketplace carrier country table name
     */
    const TABLE_MARKETPLACE_CARRIER_COUNTRY = 'lengow_marketplace_carrier_country';

    /* Marketplace carrier fields */
    const FIELD_ID = 'id';
    const FIELD_CARRIER_MARKETPLACE_NAME = 'carrier_marketplace_name';
    const FIELD_CARRIER_MARKETPLACE_LABEL = 'carrier_marketplace_label';
    const FIELD_CARRIER_LENGOW_CODE = 'carrier_lengow_code';
    const FIELD_COUNTRY_ID = 'id_country';
    const FIELD_MARKETPLACE_ID = 'id_marketplace';
    const FIELD_CARRIER_ID = 'id_carrier';
    const FIELD_CARRIER_MARKETPLACE_ID = 'id_carrier_marketplace';

    /* Compatibility codes */
    const COMPATIBILITY_OK = 1;
    const NO_COMPATIBILITY = 0;
    const COMPATIBILITY_KO = -1;

    /**
     * Get all active PrestaShop carriers
     *
     * @param integer|null $idCountry PrestaShop country id
     *
     * @return array
     */
    public static function getActiveCarriers($idCountry = null)
    {
        $carriers = array();
        if ($idCountry) {
            $sql = 'SELECT * FROM ' . _DB_PREFIX_ . 'carrier c
                INNER JOIN ' . _DB_PREFIX_ . 'carrier_zone cz ON (cz.id_carrier = c.id_carrier)
                INNER JOIN ' . _DB_PREFIX_ . 'country co ON (co.id_zone = cz.id_zone)
                WHERE c.active = 1 AND deleted = 0 AND co.id_country = ' . (int) $idCountry;
        } else {
            $sql = 'SELECT * FROM ' . _DB_PREFIX_ . 'carrier WHERE active = 1 AND deleted = 0';
        }
        try {
            $collection = Db::getInstance()->ExecuteS($sql);
        } catch (PrestaShopDatabaseException $e) {
            return $carriers;
        }
        foreach ($collection as $row) {
            $idCarrier = (int) $row['id_reference'];
            $carriers[$idCarrier] = array(
                'name' => $row['name'],
                'external_module_name' => $row['external_module_name'],
            );
        }
        return $carriers;
    }

    /**
     * Get carrier id recovery by semantic search
     *
     * @param string $search Module name
     * @param integer|null $idCountry PrestaShop country id
     * @param string|null $idRelay Delivery relay id
     *
     * @return integer|false
     */
    public static function getIdCarrierBySemanticSearch($search, $idCountry = null, $idRelay = null)
    {
        $idCarrier = self::getIdCarrierByCarrierName($search, $idCountry);
        if (!$idCarrier) {
            $idCarrier = self::getIdCarrierByExternalModuleName($search, $idCountry, $idRelay);
        }
        if ($idCarrier) {
            $idCarrier = self::getIdActiveCarrierByIdCarrier($idCarrier, $idCountry);
        }
        return $idCarrier;
    }

    /**
     * Get carrier id for a given name
     *
     * @param string $search Carrier name
     * @param integer|null $idCountry PrestaShop country id
     *
     * @return integer|false
     */
    public static function getIdCarrierByCarrierName($search, $idCountry = null)
    {
        $search = Tools::strtolower(str_replace(' ', '', $search));
        $activeCarriers = self::getActiveCarriers($idCountry);
        foreach ($activeCarriers as $idCarrier => $carrier) {
            if (Tools::strtolower(str_replace(' ', '', $carrier['name'])) === $search) {
                return (int) $idCarrier;
            }
        }
        return false;
    }

    /**
     * Get carrier id by external module name
     *
     * @param string $search Module name
     * @param integer|null $idCountry PrestaShop country id
     * @param string|null $idRelay Delivery relay id
     *
     * @return integer|false
     */
    public static function getIdCarrierByExternalModuleName($search, $idCountry = null, $idRelay = null)
    {
        $carriers = array();
        $search = Tools::strtolower(str_replace(' ', '', $search));
        $activeCarriers = self::getActiveCarriers($idCountry);
        // use exact matching on the module name
        foreach ($activeCarriers as $idCarrier => $carrier) {
            if (empty($carrier['external_module_name'])) {
                continue;
            }
            $externalModuleName = Tools::strtolower(str_replace(' ', '', $carrier['external_module_name']));
            if ($externalModuleName === $search) {
                $carriers[] = array(
                    'id_carrier' => (int) $idCarrier,
                    'external_module_name' => $carrier['external_module_name'],
                );
            }
        }
        // use approximately matching on the module name
        if (empty($carriers)) {
            foreach ($activeCarriers as $idCarrier => $carrier) {
                if (empty($carrier['external_module_name'])) {
                    continue;
                }
                $externalModuleName = Tools::strtolower(str_replace(' ', '', $carrier['external_module_name']));
                similar_text($search, $externalModuleName, $percent);
                if ($percent > 70) {
                    $carriers[(int) $percent] = array(
                        'id_carrier' => $idCarrier,
                        'external_module_name' => $carrier['external_module_name'],
                    );
                }
            }
            krsort($carriers);
        }
        if (!empty($carriers)) {
            $carrier = current($carriers);
            if ($carrier['external_module_name'] === 'mondialrelay') {
                $idMondialRelayCarrier = self::getIdMondialRelayCarrier($idRelay);
                if ($idMondialRelayCarrier) {
                    return $idMondialRelayCarrier;
                }
            }
            return $carrier['id_carrier'];
        }
        return false;
    }

    /**
     * Get Default export carrier
     *
     * @return Carrier|false
     */
    public static function getDefaultExportCarrier()
    {
        $idCarrier = (int) LengowConfiguration::getGlobalValue(LengowConfiguration::DEFAULT_EXPORT_CARRIER_ID);
        if ($idCarrier > 0) {
            $idCarrierActive = self::getIdActiveCarrierByIdCarrier($idCarrier);
            $idCarrier = $idCarrierActive ?: $idCarrier;
            $carrier = new Carrier($idCarrier);
            if ($carrier->id) {
                return $carrier;
            }
        }
        return false;
    }

    /**
     * Get active carrier id by country and carrier
     *
     * @param integer $idCarrier PrestaShop carrier id
     * @param integer|null $idCountry PrestaShop country id
     *
     * @return integer|false
     */
    public static function getIdActiveCarrierByIdCarrier($idCarrier, $idCountry = null)
    {
        if ($idCountry) {
            $sql = 'SELECT * FROM ' . _DB_PREFIX_ . 'carrier c
                INNER JOIN ' . _DB_PREFIX_ . 'carrier_zone cz ON (cz.id_carrier = c.id_carrier)
                INNER JOIN ' . _DB_PREFIX_ . 'country co ON (co.id_zone = cz.id_zone)
                WHERE c.id_reference = ' . (int) $idCarrier . ' AND co.id_country = ' . (int) $idCountry;
        } else {
            $sql = 'SELECT * FROM ' . _DB_PREFIX_ . 'carrier c WHERE c.id_reference = ' . (int) $idCarrier;
        }
        $row = Db::getInstance()->getRow($sql);
        if ($row) {
            if (!((int) $row['deleted'] === 1)) {
                return (int) $row['id_carrier'];
            }
            $sql = 'SELECT * FROM ' . _DB_PREFIX_ . 'carrier c';
            if ($idCountry) {
                $sql .= ' INNER JOIN ' . _DB_PREFIX_ . 'carrier_zone cz ON (cz.id_carrier = c.id_carrier)
                        INNER JOIN ' . _DB_PREFIX_ . 'country co ON (co.id_zone = cz.id_zone)
                        WHERE c.deleted = 0 AND c.active = 1 AND co.id_country = ' . (int) $idCountry
                    . ' AND id_reference= ' . (int) $row['id_reference'];
            } else {
                $sql .= ' WHERE c.deleted = 0 AND c.active = 1 AND c.id_reference = ' . (int) $row['id_reference'];
            }
            $row2 = Db::getInstance()->getRow($sql);
            if ($row2) {
                return (int) $row2['id_carrier'];
            }
        }
        return false;
    }

    /**
     * Get reference carrier id by country and carrier
     *
     * @param integer $idCarrier PrestaShop carrier id
     * @param integer|null $idCountry PrestaShop country id
     *
     * @return integer|false
     */
    public static function getIdReferenceByIdCarrier($idCarrier, $idCountry = null)
    {
        if ($idCountry) {
            $sql = 'SELECT c.id_reference FROM ' . _DB_PREFIX_ . 'carrier as c
                INNER JOIN ' . _DB_PREFIX_ . 'carrier_zone as cz ON (cz.id_carrier = c.id_carrier)
                INNER JOIN ' . _DB_PREFIX_ . 'country as co ON (co.id_zone = cz.id_zone)
                WHERE c.id_carrier = ' . (int) $idCarrier . ' AND co.id_country = ' . (int) $idCountry;
        } else {
            $sql = 'SELECT c.id_reference FROM ' . _DB_PREFIX_ . 'carrier as c WHERE c.id_carrier = '
                . (int) $idCarrier;
        }
        $result = Db::getInstance()->getRow($sql);
        if ($result) {
            return (int) $result['id_reference'];
        }
        return false;
    }

    /**
     * Get carrier id by country id, marketplace id and carrier marketplace name
     *
     * @param integer $idCountry PrestaShop country id
     * @param string $idMarketplace Lengow marketplace id
     * @param string $carrierMarketplaceName Lengow marketplace carrier name
     *
     * @return integer|false
     */
    public static function getIdCarrierByCarrierMarketplaceName($idCountry, $idMarketplace, $carrierMarketplaceName)
    {
        if ($carrierMarketplaceName !== '') {
            // find in lengow marketplace carrier country table
            $result = Db::getInstance()->getRow(
                'SELECT lmcc.id_carrier FROM ' . _DB_PREFIX_ . 'lengow_marketplace_carrier_country as lmcc
                INNER JOIN ' . _DB_PREFIX_ . 'lengow_carrier_marketplace as lcm
                    ON lcm.id = lmcc.id_carrier_marketplace
                WHERE lmcc.id_country = ' . (int) $idCountry . '
                AND lmcc.id_marketplace = "' . (int) $idMarketplace . '"
                AND lcm.carrier_marketplace_name = "' . pSQL($carrierMarketplaceName) . '"'
            );
            if ($result) {
                return self::getIdActiveCarrierByIdCarrier($result[self::FIELD_CARRIER_ID], (int) $idCountry);
            }
        }
        return false;
    }

    /**
     * Get all carrier marketplace
     *
     * @return array
     */
    public static function getAllCarrierMarketplace()
    {
        try {
            $results = Db::getInstance()->ExecuteS('SELECT * FROM ' . _DB_PREFIX_ . 'lengow_carrier_marketplace');
        } catch (PrestaShopDatabaseException $e) {
            $results = false;
        }
        return is_array($results) ? $results : array();
    }

    /**
     * Get all carriers marketplace by marketplace id
     *
     * @param integer $idMarketplace Lengow marketplace id
     *
     * @return array
     */
    public static function getAllCarrierMarketplaceByIdMarketplace($idMarketplace)
    {
        try {
            $results = Db::getInstance()->ExecuteS(
                'SELECT 
                    lcm.carrier_marketplace_name,
                    lcm.carrier_marketplace_label,
                    lcm.id as id_carrier_marketplace,
                    lm.id as id_marketplace,
                    lmcm.id as id_marketplace_carrier_marketplace
                FROM ' . _DB_PREFIX_ . 'lengow_carrier_marketplace as lcm
                INNER JOIN ' . _DB_PREFIX_ . 'lengow_marketplace_carrier_marketplace as lmcm
                    ON lcm.id = lmcm.id_carrier_marketplace
                INNER JOIN ' . _DB_PREFIX_ . 'lengow_marketplace as lm
                    ON lm.id = lmcm.id_marketplace
                WHERE lm.id = "' . (int) $idMarketplace . '"'
            );
        } catch (PrestaShopDatabaseException $e) {
            $results = false;
        }
        return is_array($results) ? $results : array();
    }

    /**
     * Get carrier marketplace id
     *
     * @param string $carrierMarketplaceName Lengow carrier marketplace name
     *
     * @return integer|false
     */
    public static function getIdCarrierMarketplace($carrierMarketplaceName)
    {
        try {
            $results = Db::getInstance()->ExecuteS(
                'SELECT id, carrier_marketplace_name FROM ' . _DB_PREFIX_ . 'lengow_carrier_marketplace
                WHERE carrier_marketplace_name = "' . pSQL($carrierMarketplaceName) . '"'
            );
        } catch (PrestaShopDatabaseException $e) {
            $results = array();
        }
        // additional verification for non-case sensitive Databases
        if (!empty($results)) {
            foreach ($results as $result) {
                if ($result[self::FIELD_CARRIER_MARKETPLACE_NAME] === $carrierMarketplaceName) {
                    return (int) $result[self::FIELD_ID];
                }
            }
        }
        return false;
    }

    /**
     * Get carrier marketplace
     *
     * @param string $idCarrierMarketplace Lengow carrier marketplace id
     *
     * @return array|false
     */
    public static function getCarrierMarketplaceById($idCarrierMarketplace)
    {
        $result = Db::getInstance()->getRow(
            'SELECT * FROM ' . _DB_PREFIX_ . 'lengow_carrier_marketplace WHERE id = ' . (int) $idCarrierMarketplace
        );
        return $result ?: false;
    }

    /**
     * Sync Lengow carrier marketplaces
     */
    public static function syncCarrierMarketplace()
    {
        LengowMarketplace::loadApiMarketplace();
        if (LengowMarketplace::$marketplaces && !empty(LengowMarketplace::$marketplaces)) {
            foreach (LengowMarketplace::$marketplaces as $marketplaceName => $marketplace) {
                if (isset($marketplace->orders->carriers)) {
                    $idMarketplace = LengowMarketplace::getIdMarketplace($marketplaceName);
                    foreach ($marketplace->orders->carriers as $carrierMarketplaceName => $carrier) {
                        $idCarrierMarketplace = self::getIdCarrierMarketplace($carrierMarketplaceName);
                        if (!$idCarrierMarketplace) {
                            $idCarrierMarketplace = self::insertCarrierMarketplace(
                                $carrierMarketplaceName,
                                $carrier->label,
                                isset($carrier->lengow_code) ? $carrier->lengow_code : null
                            );
                        } else {
                            $params = array();
                            if ($carrier->label !== null && Tools::strlen($carrier->label) > 0) {
                                $params[self::FIELD_CARRIER_MARKETPLACE_LABEL] = pSQL($carrier->label);
                            }
                            if (isset($carrier->lengow_code)
                                && $carrier->lengow_code !== null
                                && Tools::strlen($carrier->lengow_code) > 0
                            ) {
                                $params[self::FIELD_CARRIER_LENGOW_CODE] = pSQL($carrier->lengow_code);
                            }
                            if (!empty($params)) {
                                self::updateCarrierMarketplace($idCarrierMarketplace, $params);
                            }
                        }
                        if ($idMarketplace && $idCarrierMarketplace) {
                            self::matchCarrierMarketplaceWithMarketplace($idMarketplace, $idCarrierMarketplace);
                        }
                    }
                }
            }
        }
    }

    /**
     * Insert a new carrier marketplace in the table
     *
     * @param string $carrierMarketplaceName Lengow carrier marketplace name
     * @param string $carrierMarketplaceLabel Lengow carrier marketplace label
     * @param string|null $carrierLengowCode Lengow carrier lengow code
     *
     * @return integer|false
     */
    public static function insertCarrierMarketplace(
        $carrierMarketplaceName,
        $carrierMarketplaceLabel,
        $carrierLengowCode = null
    ) {
        $params = array(
            self::FIELD_CARRIER_MARKETPLACE_NAME => pSQL($carrierMarketplaceName),
            self::FIELD_CARRIER_MARKETPLACE_LABEL => pSQL($carrierMarketplaceLabel),
        );
        if ($carrierLengowCode !== null && Tools::strlen($carrierLengowCode) > 0) {
            $params[self::FIELD_CARRIER_LENGOW_CODE] = pSQL($carrierLengowCode);
        }
        $db = Db::getInstance();
        $success = $db->insert(self::TABLE_CARRIER_MARKETPLACE, $params);
        return $success ? self::getIdCarrierMarketplace($carrierMarketplaceName) : false;
    }

    /**
     * Update a carrier marketplace
     *
     * @param integer $idCarrierMarketplace Lengow carrier marketplace id
     * @param array $params all parameters to update a carrier marketplace
     *
     * @return integer|false
     */
    public static function updateCarrierMarketplace($idCarrierMarketplace, $params)
    {
        $db = Db::getInstance();
        $success = $db->update(self::TABLE_CARRIER_MARKETPLACE, $params, 'id = ' . (int) $idCarrierMarketplace);
        return $success ? $idCarrierMarketplace : false;
    }

    /**
     * Match carrier marketplace with one marketplace
     *
     * @param integer $idMarketplace Lengow marketplace id
     * @param integer $idCarrierMarketplace Lengow carrier marketplace id
     *
     * @return boolean
     */
    public static function matchCarrierMarketplaceWithMarketplace($idMarketplace, $idCarrierMarketplace)
    {
        $db = Db::getInstance();
        try {
            $result = Db::getInstance()->ExecuteS(
                'SELECT id FROM ' . _DB_PREFIX_ . 'lengow_marketplace_carrier_marketplace
                WHERE id_marketplace = ' . (int) $idMarketplace . '
                AND id_carrier_marketplace = ' . (int) $idCarrierMarketplace
            );
        } catch (PrestaShopDatabaseException $e) {
            $result = array();
        }
        if (empty($result)) {
            $params = array(
                self::FIELD_MARKETPLACE_ID => (int) $idMarketplace,
                self::FIELD_CARRIER_MARKETPLACE_ID => (int) $idCarrierMarketplace,
            );
            $success = $db->insert(self::TABLE_MARKETPLACE_CARRIER_MARKETPLACE, $params);
        } else {
            $success = true;
        }
        return $success;
    }

    /**
     * Delete carrier marketplace matching
     *
     * @param integer $idMarketplaceCarrierMarketplace Lengow marketplace carrier marketplace id
     *
     * @return boolean
     */
    public static function deleteMarketplaceCarrierMarketplace($idMarketplaceCarrierMarketplace)
    {
        return Db::getInstance()->delete(
            self::TABLE_MARKETPLACE_CARRIER_MARKETPLACE,
            'id = ' . (int) $idMarketplaceCarrierMarketplace
        );
    }

    /**
     * Clean carrier marketplace matching for old carriers
     */
    public static function cleanCarrierMarketplaceMatching()
    {
        LengowMarketplace::loadApiMarketplace();
        if (LengowMarketplace::$marketplaces && !empty(LengowMarketplace::$marketplaces)) {
            foreach (LengowMarketplace::$marketplaces as $marketplaceName => $marketplace) {
                $idMarketplace = LengowMarketplace::getIdMarketplace($marketplaceName);
                if ($idMarketplace) {
                    // get all carrier saved in database
                    $carrierMarketplaces = self::getAllCarrierMarketplaceByIdMarketplace($idMarketplace);
                    // get all current marketplace carriers with api
                    $currentCarrierMarketplaces = array();
                    if (isset($marketplace->orders->carriers)) {
                        foreach ($marketplace->orders->carriers as $carrierMarketplaceName => $carrierMarketplace) {
                            $currentCarrierMarketplaces[$carrierMarketplaceName] = $carrierMarketplace->label;
                        }
                    }
                    // if the carrier is no longer on the marketplace, removal of matching
                    foreach ($carrierMarketplaces as $carrierMarketplace) {
                        if (!array_key_exists(
                            $carrierMarketplace[self::FIELD_CARRIER_MARKETPLACE_NAME],
                            $currentCarrierMarketplaces
                        )) {
                            // delete marketplace carrier matching
                            self::deleteMarketplaceCarrierMarketplace(
                                (int) $carrierMarketplace['id_marketplace_carrier_marketplace']
                            );
                            $idCarrierMarketplace = (int) $carrierMarketplace[self::FIELD_CARRIER_MARKETPLACE_ID];
                            // delete carrier marketplace id  from default carrier if is matched
                            self::cleanDefaultCarrierByIdMarketplace($idMarketplace, $idCarrierMarketplace);
                            // delete carrier marketplace id from marketplace carrier country if is matched
                            self::cleanMarketplaceCarrierCountryByIdMarketplace($idMarketplace, $idCarrierMarketplace);
                        }
                    }
                }
            }
        }
    }

    /**
     * Create default carrier for marketplace
     *
     * @param integer|null $idCountry PrestaShop country id
     * @param integer|null $idMarketplace Lengow marketplace id
     */
    public static function createDefaultCarrier($idCountry = null, $idMarketplace = null)
    {
        if ($idCountry === null) {
            $idCountry = (int) Configuration::get('PS_COUNTRY_DEFAULT');
        }
        $marketplaces = LengowMarketplace::getAllMarketplaces();
        foreach ($marketplaces as $marketplace) {
            $id = (int) $marketplace[LengowMarketplace::FIELD_ID];
            if ($idMarketplace !== null && $idMarketplace !== $id) {
                continue;
            }
            if (!self::getIdDefaultCarrier($idCountry, $id)) {
                self::insertDefaultCarrier($idCountry, $id);
            }
        }
    }

    /**
     * Clean default carrier when match carrier marketplace is deleted
     *
     * @param integer $idMarketplace Lengow marketplace id
     * @param integer $idCarrierMarketplace Lengow carrier marketplace id
     */
    public static function cleanDefaultCarrierByIdMarketplace($idMarketplace, $idCarrierMarketplace)
    {
        try {
            $results = Db::getInstance()->ExecuteS(
                'SELECT id FROM ' . _DB_PREFIX_ . 'lengow_default_carrier
                WHERE id_carrier_marketplace = ' . (int) $idCarrierMarketplace . '
                AND id_marketplace = ' . (int) $idMarketplace
            );
        } catch (PrestaShopDatabaseException $e) {
            $results = array();
        }
        if (!empty($results)) {
            foreach ($results as $result) {
                if (isset($result[self::FIELD_ID]) && $result[self::FIELD_ID] > 0) {
                    self::updateDefaultCarrier(
                        (int) $result[self::FIELD_ID],
                        array(self::FIELD_CARRIER_MARKETPLACE_ID => 0)
                    );
                }
            }
        }
    }

    /**
     * Get default carrier id
     *
     * @param integer $idCountry PrestaShop country id
     * @param integer $idMarketplace Lengow marketplace id
     *
     * @return integer|false
     */
    public static function getIdDefaultCarrier($idCountry, $idMarketplace)
    {
        try {
            $results = Db::getInstance()->ExecuteS(
                'SELECT id FROM ' . _DB_PREFIX_ . 'lengow_default_carrier
                WHERE id_country = ' . (int) $idCountry . ' AND id_marketplace = ' . (int) $idMarketplace
            );
            return !empty($results) ? (int) $results[0][self::FIELD_ID] : false;
        } catch (PrestaShopDatabaseException $e) {
            return false;
        }
    }

    /**
     * Get default carrier by country and marketplace
     *
     * @param integer $idCountry PrestaShop country id
     * @param integer $idMarketplace Lengow marketplace id
     * @param boolean $active get active carrier id
     *
     * @return integer|false
     */
    public static function getDefaultIdCarrier($idCountry, $idMarketplace, $active = false)
    {
        $idCarrier = false;
        $result = Db::getInstance()->getRow(
            'SELECT id_carrier FROM ' . _DB_PREFIX_ . 'lengow_default_carrier
            WHERE id_country = ' . (int) $idCountry . ' AND id_marketplace = ' . (int) $idMarketplace
        );
        if ($result
            && isset($result[self::FIELD_CARRIER_ID])
            && $result[self::FIELD_CARRIER_ID] !== null
            && (int) $result[self::FIELD_CARRIER_ID] > 0
        ) {
            $idCarrier = (int) $result[self::FIELD_CARRIER_ID];
            $idCarrier = $active ? self::getIdActiveCarrierByIdCarrier($idCarrier, $idCountry) : $idCarrier;
        }
        return $idCarrier;
    }

    /**
     * Get default carrier marketplace by country and marketplace
     *
     * @param integer $idCountry PrestaShop country id
     * @param integer $idMarketplace Lengow marketplace id
     *
     * @return integer|false
     */
    public static function getDefaultIdCarrierMarketplace($idCountry, $idMarketplace)
    {
        $idCarrier = false;
        $result = Db::getInstance()->getRow(
            'SELECT id_carrier_marketplace FROM ' . _DB_PREFIX_ . 'lengow_default_carrier
            WHERE id_country = ' . (int) $idCountry . ' AND id_marketplace = ' . (int) $idMarketplace
        );
        if ($result
            && isset($result[self::FIELD_CARRIER_MARKETPLACE_ID])
            && $result[self::FIELD_CARRIER_MARKETPLACE_ID] !== null
            && (int) $result[self::FIELD_CARRIER_MARKETPLACE_ID] > 0
        ) {
            $idCarrier = (int) $result[self::FIELD_CARRIER_MARKETPLACE_ID];
        }
        return $idCarrier;
    }

    /**
     * Get default carriers not matched listed by country id
     *
     * @return array
     */
    public static function getDefaultCarrierNotMatched()
    {
        $defaultCarriers = array();

        $sql = 'SELECT * FROM ' . _DB_PREFIX_ . 'lengow_default_carrier
            WHERE id_carrier IS NULL OR id_carrier = 0 ORDER BY id_country ASC';
        try {
            $results = Db::getInstance()->executeS($sql);
        } catch (PrestaShopDatabaseException $e) {
            $results = false;
        }
        if (is_array($results)) {
            foreach ($results as $result) {
                $defaultCarriers[(int) $result[self::FIELD_COUNTRY_ID]][] = $result;
            }
        }
        return $defaultCarriers;
    }

    /**
     * Has default carriers not matched
     *
     * @return boolean
     */
    public static function hasDefaultCarrierNotMatched()
    {
        $carrierNotMatched = self::getDefaultCarrierNotMatched();
        return !empty($carrierNotMatched);
    }

    /**
     * Get a list of countries
     *
     * @return array|false
     */
    public static function getCountries()
    {
        try {
            $results = Db::getInstance()->executeS(
                'SELECT ldc.id_country, c.iso_code, cl.name FROM ' . _DB_PREFIX_ . 'lengow_default_carrier as ldc
                INNER JOIN ' . _DB_PREFIX_ . 'country as c ON ldc.id_country = c.id_country
                INNER JOIN ' . _DB_PREFIX_ . 'country_lang as cl ON c.id_country = cl.id_country
                AND cl.id_lang = ' . (int) Context::getContext()->language->id . '
                GROUP BY ldc.id_country'
            );
            return !empty($results) ? $results : false;
        } catch (PrestaShopDatabaseException $e) {
            return false;
        }
    }

    /**
     * Insert a new default carrier or a new default carrier marketplace
     *
     * @param integer $idCountry PrestaShop country id
     * @param integer $idMarketplace Lengow marketplace id
     * @param array $additionalParams all additional parameters (id_carrier or id_carrier_marketplace)
     *
     * @return integer|false
     */
    public static function insertDefaultCarrier($idCountry, $idMarketplace, $additionalParams = array())
    {
        $params = array_merge(
            array(self::FIELD_COUNTRY_ID => (int) $idCountry, self::FIELD_MARKETPLACE_ID => (int) $idMarketplace),
            $additionalParams
        );
        $db = Db::getInstance();
        $success = $db->insert(self::TABLE_DEFAULT_CARRIER, $params);
        return $success ? self::getIdDefaultCarrier($idCountry, $idMarketplace) : false;
    }

    /**
     * Update a default carrier or a default carrier marketplace
     *
     * @param integer $idDefaultCarrier Lengow default carrier id
     * @param array $params all parameters to update default carrier (id_carrier or id_carrier_marketplace)
     *
     * @return integer|false
     */
    public static function updateDefaultCarrier($idDefaultCarrier, $params)
    {
        $db = Db::getInstance();
        $success = $db->update(self::TABLE_DEFAULT_CARRIER, $params, 'id = ' . (int) $idDefaultCarrier);
        return $success ? $idDefaultCarrier : false;
    }

    /**
     * Get marketplace carrier country id
     *
     * @param integer $idCountry PrestaShop country id
     * @param integer $idMarketplace Lengow marketplace id
     * @param integer $idCarrier PrestaShop carrier id
     *
     * @return integer|false
     */
    public static function getIdMarketplaceCarrierCountry($idCountry, $idMarketplace, $idCarrier)
    {
        try {
            $result = Db::getInstance()->ExecuteS(
                'SELECT id FROM ' . _DB_PREFIX_ . 'lengow_marketplace_carrier_country
                WHERE id_country = ' . (int) $idCountry . '
                AND id_marketplace = ' . (int) $idMarketplace . '
                AND id_carrier = ' . (int) $idCarrier
            );
            return !empty($result) ? (int) $result[0][self::FIELD_ID] : false;
        } catch (PrestaShopDatabaseException $e) {
            return false;
        }
    }

    /**
     * Clean table when match carrier marketplace is deleted
     *
     * @param integer $idMarketplace Lengow marketplace id
     * @param integer $idCarrierMarketplace Lengow carrier marketplace id
     */
    public static function cleanMarketplaceCarrierCountryByIdMarketplace($idMarketplace, $idCarrierMarketplace)
    {
        try {
            $results = Db::getInstance()->ExecuteS(
                'SELECT id FROM ' . _DB_PREFIX_ . 'lengow_marketplace_carrier_country
                WHERE id_carrier_marketplace = ' . (int) $idCarrierMarketplace . '
                AND id_marketplace = ' . (int) $idMarketplace
            );
        } catch (PrestaShopDatabaseException $e) {
            $results = array();
        }
        if (!empty($results)) {
            foreach ($results as $result) {
                if (isset($result[self::FIELD_ID]) && $result[self::FIELD_ID] > 0) {
                    self::updateMarketplaceCarrierCountry((int) $result[self::FIELD_ID], 0);
                }
            }
        }
    }

    /**
     * Get carrier marketplace id by marketplace, carrier and country
     *
     * @param integer $idCountry PrestaShop country id
     * @param integer $idMarketplace Lengow marketplace id
     * @param integer $idCarrier PrestaShop carrier id
     *
     * @return integer|false
     */
    public static function getIdCarrierMarketplaceByMarketplaceCarrierCountry($idCountry, $idMarketplace, $idCarrier)
    {
        $result = Db::getInstance()->getRow(
            'SELECT id_carrier_marketplace FROM ' . _DB_PREFIX_ . 'lengow_marketplace_carrier_country
            WHERE id_country = ' . (int) $idCountry . '
            AND id_marketplace = ' . (int) $idMarketplace . '
            AND id_carrier = ' . (int) $idCarrier
        );
        if ($result
            && $result[self::FIELD_CARRIER_MARKETPLACE_ID] !== null
            && (int) $result[self::FIELD_CARRIER_MARKETPLACE_ID] > 0
        ) {
            return (int) $result[self::FIELD_CARRIER_MARKETPLACE_ID];
        }
        return false;
    }

    /**
     * Get marketplace carrier country id
     *
     * @param integer $idCountry PrestaShop country id
     * @param integer $idMarketplace Lengow marketplace id
     *
     * @return array
     */
    public static function getAllMarketplaceCarrierCountryByIdMarketplace($idCountry, $idMarketplace)
    {
        $carriers = array();
        try {
            $results = Db::getInstance()->ExecuteS(
                'SELECT * FROM ' . _DB_PREFIX_ . 'lengow_marketplace_carrier_country
                WHERE id_country = ' . (int) $idCountry . '
                AND id_marketplace = ' . (int) $idMarketplace
            );
        } catch (PrestaShopDatabaseException $e) {
            $results = array();
        }
        if (!empty($results)) {
            foreach ($results as $result) {
                $carriers[(int) $result[self::FIELD_CARRIER_ID]] = (int) $result[self::FIELD_CARRIER_MARKETPLACE_ID];
            }
        }
        return $carriers;
    }

    /**
     * Insert a new marketplace carrier country
     *
     * @param integer $idCountry PrestaShop country id
     * @param integer $idMarketplace Lengow marketplace id
     * @param integer $idCarrier PrestaShop carrier id
     * @param integer $idCarrierMarketplace Lengow carrier marketplace id
     *
     * @return integer|false
     */
    public static function insertMarketplaceCarrierCountry(
        $idCountry,
        $idMarketplace,
        $idCarrier,
        $idCarrierMarketplace
    ) {
        $params = array(
            self::FIELD_COUNTRY_ID => (int) $idCountry,
            self::FIELD_MARKETPLACE_ID => (int) $idMarketplace,
            self::FIELD_CARRIER_ID => (int) $idCarrier,
            self::FIELD_CARRIER_MARKETPLACE_ID => (int) $idCarrierMarketplace,
        );
        $db = Db::getInstance();
        $success = $db->insert(self::TABLE_MARKETPLACE_CARRIER_COUNTRY, $params);
        return $success ? self::getIdMarketplaceCarrierCountry($idCountry, $idMarketplace, $idCarrier) : false;
    }

    /**
     * Update a marketplace carrier country
     *
     * @param integer $idMarketplaceCarrierCountry Lengow marketplace carrier country id
     * @param integer $idCarrierMarketplace Lengow carrier marketplace id
     *
     * @return integer|false
     */
    public static function updateMarketplaceCarrierCountry($idMarketplaceCarrierCountry, $idCarrierMarketplace)
    {
        $db = Db::getInstance();
        $success = $db->update(
            self::TABLE_MARKETPLACE_CARRIER_COUNTRY,
            array(self::FIELD_CARRIER_MARKETPLACE_ID => (int) $idCarrierMarketplace),
            'id = ' . (int) $idMarketplaceCarrierCountry
        );
        return $success ? $idMarketplaceCarrierCountry : false;
    }

    /**
     * Try to get a carrier marketplace code for action
     *
     * @param integer $idCountry PrestaShop country id
     * @param integer $idMarketplace Lengow marketplace id
     * @param integer $idCarrier PrestaShop carrier id
     *
     * @return string
     */
    public static function getCarrierMarketplaceCode($idCountry, $idMarketplace, $idCarrier)
    {
        $marketplaceCode = '';
        $idCarrier = self::getIdReferenceByIdCarrier($idCarrier, $idCountry);
        // if the carrier is properly matched, get a specific carrier marketplace id
        $idCarrierMarketplace = self::getIdCarrierMarketplaceByMarketplaceCarrierCountry(
            $idCountry,
            $idMarketplace,
            $idCarrier
        );
        if (!$idCarrierMarketplace) {
            // if the carrier is not matched, get a default carrier marketplace id
            $idCarrierMarketplace = self::getDefaultIdCarrierMarketplace($idCountry, $idMarketplace);
        }
        if ($idCarrierMarketplace) {
            // if the carrier marketplace is present, get carrier marketplace name
            $carrierMarketplace = self::getCarrierMarketplaceById($idCarrierMarketplace);
            if ($carrierMarketplace) {
                $marketplaceCode = $carrierMarketplace[self::FIELD_CARRIER_MARKETPLACE_NAME];
            }
        }
        // if the default carrier marketplace is not matched or empty, get PrestaShop carrier name
        if (Tools::strlen($marketplaceCode) === 0) {
            $idActiveCarrier = self::getIdActiveCarrierByIdCarrier($idCarrier, $idCountry);
            $idCarrier = $idActiveCarrier ? $idActiveCarrier : $idCarrier;
            $carrier = new Carrier($idCarrier);
            $marketplaceCode = $carrier->name;
        }
        return $marketplaceCode;
    }

    /**
     * Ensure carrier compatibility with SoColissimo and MondialRelay Modules
     *
     * @param integer $idOrder PrestaShop order id
     * @param integer $idCustomer PrestaShop customer id
     * @param integer $idCart PrestaShop cart id
     * @param integer $idCarrier PrestaShop carrier id
     * @param LengowAddress $shippingAddress order shipping address
     *
     * @throws LengowException mondial relay not found
     *
     * @return integer -1 = compatibility not ensured, 0 = not a carrier module, 1 = compatibility ensured
     */
    public static function carrierCompatibility($idOrder, $idCustomer, $idCart, $idCarrier, $shippingAddress)
    {
        // get SoColissimo carrier id
        $soColissimoCarrierId =_PS_VERSION_ < '1.7'
            ? Configuration::get('SOCOLISSIMO_CARRIER_ID')
            : Configuration::get('COLISSIMO_CARRIER_ID');
        if ($idCarrier === (int) $soColissimoCarrierId) {
            if (!LengowMain::isSoColissimoAvailable()) {
                return self::COMPATIBILITY_KO;
            }
            return self::addSoColissimo(
                $idCart,
                $idCustomer,
                $shippingAddress
            ) ? self::COMPATIBILITY_OK : self::COMPATIBILITY_KO;
        }
        // Mondial Relay
        if (!LengowMain::isMondialRelayAvailable()) {
            return self::COMPATIBILITY_KO;
        }
        $mr = new MondialRelay();
        if ($mr->isMondialRelayCarrier($idCarrier)) {
            $relay = self::getMRRelay($shippingAddress->id, $shippingAddress->idRelay, $mr);
            if (!$relay) {
                throw new LengowException(
                    LengowMain::setLogMessage(
                        'log.import.error_mondial_relay_not_found',
                        array('id_relay' => $shippingAddress->idRelay)
                    )
                );
            }
            return self::addMondialRelay($relay, $idOrder, $idCustomer, $idCarrier, $idCart)
                ? self::COMPATIBILITY_OK
                : self::COMPATIBILITY_KO;
        }
        return self::NO_COMPATIBILITY;
    }

    /**
     * Save order in SoColissimo table
     *
     * @param integer $idCart PrestaShop cart id
     * @param integer $idCustomer PrestaShop customer id
     * @param LengowAddress $shippingAddress shipping address
     *
     * @throws LengowException colissimo missing file
     *
     * @return boolean
     */
    public static function addSoColissimo($idCart, $idCustomer, $shippingAddress)
    {
        $sep = DIRECTORY_SEPARATOR;
        $moduleName = _PS_VERSION_ < '1.7' ? 'socolissimo' : 'colissimo_simplicite';
        $filePath = _PS_MODULE_DIR_ . $moduleName . $sep . 'classes' . $sep . 'SCFields.php';
        $loaded = include_once $filePath;
        if (!$loaded) {
            throw new LengowException(
                LengowMain::setLogMessage(
                    'log.import.error_colissimo_missing_file',
                    array('file_path' => $filePath)
                )
            );
        }
        $customer = new LengowCustomer($idCustomer);
        $params = array();
        if (!empty($shippingAddress->idRelay)) {
            $deliveryMode = 'A2P';
            $soColissimo = new SCFields($deliveryMode);
            $params['PRID'] = (string) $shippingAddress->idRelay;
            $params['PRCOMPLADRESS'] = (string) $shippingAddress->other;
            $params['PRADRESS1'] = (string) $shippingAddress->address1;
            // not a param in SoColissimo -> error ?
            $params['PRADRESS2'] = (string) $shippingAddress->address2;
            $params['PRADRESS3'] = (string) $shippingAddress->address2;
            $params['PRZIPCODE'] = (string) $shippingAddress->postcode;
            $params['PRTOWN'] = (string) $shippingAddress->city;
            $params['CEEMAIL'] = (string) $customer->email;
        } else {
            $deliveryMode = 'DOM';
            $soColissimo = new SCFields($deliveryMode);
            $params['CECOMPLADRESS'] = (string) $shippingAddress->other;
            $params['CEADRESS2'] = (string) $shippingAddress->address2;
            $params['CEADRESS3'] = (string) $shippingAddress->address1;
        }
        // common params
        $params['DELIVERYMODE'] = $deliveryMode;
        $params['CENAME'] = (string) $shippingAddress->lastname;
        $params['CEFIRSTNAME'] = (string) $shippingAddress->firstname;
        $params['CEPHONENUMBER'] = (string) $shippingAddress->phone;
        $params['CECOMPANYNAME'] = (string) $shippingAddress->company;
        $params['CEZIPCODE'] = (string) $shippingAddress->postcode;
        $params['CETOWN'] = (string) $shippingAddress->city;
        $params['PRPAYS'] = (string) Country::getIsoById($shippingAddress->id_country);
        $tableName = _PS_VERSION_ < '1.7' ? 'socolissimo_delivery_info' : 'colissimo_delivery_info';
        $sql = 'INSERT INTO ' . _DB_PREFIX_ . $tableName . '
            (`id_cart`,
            `id_customer`,
            `delivery_mode`,
            `prid`,
            `prname`,
            `prfirstname`,
            `prcompladress`,
            `pradress1`,
            `pradress2`,
            `pradress3`,
            `pradress4`,
            `przipcode`,
            `prtown`,
            `cecountry`,
            `cephonenumber`,
            `ceemail`,
            `cecompanyname`,
            `cedeliveryinformation`,
            `cedoorcode1`,
            `cedoorcode2`,
            `codereseau`,
            `cename`,
            `cefirstname`)
            VALUES (' . (int) $idCart . ', ' . (int) $idCustomer . ',';
        if ($soColissimo->delivery_mode === SCFields::RELAY_POINT) {
            $sql .= '\'' . pSQL($deliveryMode) . '\',
                ' . (isset($params['PRID']) ? '\'' . pSQL($params['PRID']) . '\'' : '\'\'') . ',
                ' . (isset($params['CENAME']) ? '\'' . pSQL($params['CENAME']) . '\'' : '\'\'') . ',
                ' . (
                isset($params['CEFIRSTNAME']) ? '\'' . Tools::ucfirst(pSQL($params['CEFIRSTNAME'])) . '\'' : '\'\''
                ) . ',
                ' . (isset($params['PRCOMPLADRESS']) ? '\'' . pSQL($params['PRCOMPLADRESS']) . '\'' : '\'\'') . ',
                ' . (isset($params['PRNAME']) ? '\'' . pSQL($params['PRNAME']) . '\'' : '\'\'') . ',
                ' . (isset($params['PRADRESS1']) ? '\'' . pSQL($params['PRADRESS1']) . '\'' : '\'\'') . ',
                ' . (isset($params['PRADRESS3']) ? '\'' . pSQL($params['PRADRESS3']) . '\'' : '\'\'') . ',
                ' . (isset($params['PRADRESS4']) ? '\'' . pSQL($params['PRADRESS4']) . '\'' : '\'\'') . ',
                ' . (isset($params['PRZIPCODE']) ? '\'' . pSQL($params['PRZIPCODE']) . '\'' : '\'\'') . ',
                ' . (isset($params['PRTOWN']) ? '\'' . pSQL($params['PRTOWN']) . '\'' : '\'\'') . ',
                ' . (isset($params['PRPAYS']) ? '\'' . pSQL($params['PRPAYS']) . '\'' : '\'\'') . ',
                ' . (isset($params['CEPHONENUMBER']) ? '\'' . pSQL($params['CEPHONENUMBER']) . '\'' : '\'\'') . ',
                ' . (isset($params['CEEMAIL']) ? '\'' . pSQL($params['CEEMAIL']) . '\'' : '\'\'') . ',
                ' . (isset($params['CECOMPANYNAME']) ? '\'' . pSQL($params['CECOMPANYNAME']) . '\'' : '\'\'') . ',
                ' . (
                isset($params['CEDELIVERYINFORMATION']) ? '\'' . pSQL($params['CEDELIVERYINFORMATION']) . '\'' : '\'\''
                ) . ',
                ' . (isset($params['CEDOORCODE1']) ? '\'' . pSQL($params['CEDOORCODE1']) . '\'' : '\'\'') . ',
                ' . (isset($params['CEDOORCODE2']) ? '\'' . pSQL($params['CEDOORCODE2']) . '\'' : '\'\'') . ',
                ' . (isset($params['CODERESEAU']) ? '\'' . pSQL($params['CODERESEAU']) . '\'' : '\'\'') . ',
                ' . (isset($params['CENAME']) ? '\'' . Tools::ucfirst(pSQL($params['CENAME'])) . '\'' : '\'\'') . ',
                ' . (
                isset($params['CEFIRSTNAME']) ? '\'' . Tools::ucfirst(pSQL($params['CEFIRSTNAME'])) . '\'' : '\'\''
                ) . ')';
        } else {
            $sql .= '\'' . pSQL($deliveryMode) . '\',\'\',
                ' . (isset($params['CENAME']) ? '\'' . Tools::ucfirst(pSQL($params['CENAME'])) . '\'' : '\'\'') . ',
                ' . (
                isset($params['CEFIRSTNAME']) ? '\'' . Tools::ucfirst(pSQL($params['CEFIRSTNAME'])) . '\'' : '\'\''
                ) . ',
                ' . (isset($params['CECOMPLADRESS']) ? '\'' . pSQL($params['CECOMPLADRESS']) . '\'' : '\'\'') . ',
                ' . (isset($params['CEADRESS1']) ? '\'' . pSQL($params['CEADRESS1']) . '\'' : '\'\'') . ',
                ' . (isset($params['CEADRESS4']) ? '\'' . pSQL($params['CEADRESS4']) . '\'' : '\'\'') . ',
                ' . (isset($params['CEADRESS3']) ? '\'' . pSQL($params['CEADRESS3']) . '\'' : '\'\'') . ',
                ' . (isset($params['CEADRESS2']) ? '\'' . pSQL($params['CEADRESS2']) . '\'' : '\'\'') . ',
                ' . (isset($params['CEZIPCODE']) ? '\'' . pSQL($params['CEZIPCODE']) . '\'' : '\'\'') . ',
                ' . (isset($params['CETOWN']) ? '\'' . pSQL($params['CETOWN']) . '\'' : '\'\'') . ',
                ' . (isset($params['PRPAYS']) ? '\'' . pSQL($params['PRPAYS']) . '\'' : '\'\'') . ',
                ' . (isset($params['CEPHONENUMBER']) ? '\'' . pSQL($params['CEPHONENUMBER']) . '\'' : '\'\'') . ',
                ' . (isset($params['CEEMAIL']) ? '\'' . pSQL($params['CEEMAIL']) . '\'' : '\'\'') . ',
                ' . (isset($params['CECOMPANYNAME']) ? '\'' . pSQL($params['CECOMPANYNAME']) . '\'' : '\'\'') . ',
                ' . (
                isset($params['CEDELIVERYINFORMATION']) ? '\'' . pSQL($params['CEDELIVERYINFORMATION']) . '\'' : '\'\''
                ) . ',
                ' . (isset($params['CEDOORCODE1']) ? '\'' . pSQL($params['CEDOORCODE1']) . '\'' : '\'\'') . ',
                ' . (isset($params['CEDOORCODE2']) ? '\'' . pSQL($params['CEDOORCODE2']) . '\'' : '\'\'') . ',
                ' . (isset($params['CODERESEAU']) ? '\'' . pSQL($params['CODERESEAU']) . '\'' : '\'\'') . ',
                ' . (isset($params['CENAME']) ? '\'' . Tools::ucfirst(pSQL($params['CENAME'])) . '\'' : '\'\'') . ',
                ' . (
                isset($params['CEFIRSTNAME']) ? '\'' . Tools::ucfirst(pSQL($params['CEFIRSTNAME'])) . '\'' : '\'\''
                ) . ')';
        }
        return Db::getInstance()->execute($sql);
    }

    /**
     * Get mondial relay carrier id for a specific delivery mode
     *
     * @param string|null $idRelay Delivery relay id
     *
     * @return integer|false
     */
    public static function getIdMondialRelayCarrier($idRelay = null)
    {
        if (LengowInstall::checkTableExists('mr_method')) {
            $sql = 'SELECT c.id_reference as id_carrier FROM ' . _DB_PREFIX_ . 'carrier c
                INNER JOIN ' . _DB_PREFIX_ . 'mr_method mrm ON (mrm.id_carrier = c.id_carrier)
                WHERE mrm.is_deleted = 0 AND dlv_mode = ' . ($idRelay === null ? '\'LD1\'' : '\'24R\'');
            $result = Db::getInstance()->getRow($sql);
            if ($result) {
                return (int) $result['id_carrier'];
            }
        }
        return false;
    }

    /**
     * Check if relay ID is correct
     *
     * @param integer $idAddressDelivery PrestaShop shipping address id
     * @param string $idRelay relay id
     * @param MondialRelay $mr Mondial Relay module
     *
     * @throws LengowException mondial relay missing file
     *
     * @return boolean
     */
    public static function getMRRelay($idAddressDelivery, $idRelay, $mr)
    {
        $sep = DIRECTORY_SEPARATOR;
        if (empty($idRelay)) {
            return false;
        }
        $loaded = include_once _PS_MODULE_DIR_ . 'mondialrelay' . $sep . 'classes' . $sep . 'MRRelayDetail.php';
        if (!$loaded) {
            throw new LengowException(
                LengowMain::setLogMessage(
                    'log.import.error_mondial_relay_missing_file',
                    array('ps_module_dir' => _PS_MODULE_DIR_)
                )
            );
        }
        $params = array(
            'id_address_delivery' => (int) $idAddressDelivery,
            'relayPointNumList' => array($idRelay),
        );
        $mrRd = new MRRelayDetail($params, $mr);
        try {
            $mrRd->init();
            $mrRd->send();
            $result = $mrRd->getResult();
        } catch (Exception $e) {
            return false;
        }
        if (empty($result['error'][0]) && array_key_exists($idRelay, $result['success'])) {
            return $result['success'][$idRelay];
        }
        return false;
    }

    /**
     * Save order in MR table
     *
     * @param mixed $relay relay info
     * @param integer $idOrder PrestaShop order id
     * @param integer $idCustomer PrestaShop customer id
     * @param integer $idCarrier PrestaShop carrier id
     * @param integer $idCart PrestaShop cart id
     * @param integer $insurance insurance
     *
     * @return boolean
     */
    public static function addMondialRelay($relay, $idOrder, $idCustomer, $idCarrier, $idCart, $insurance = 0)
    {
        $mdArrayKeys = array(
            'Num',
            'LgAdr1',
            'LgAdr2',
            'LgAdr3',
            'LgAdr4',
            'CP',
            'Ville',
            'Pays',
        );
        // get column names specific to the order
        $query = 'INSERT INTO `' . _DB_PREFIX_ . 'mr_selected`
            (`id_customer`,
            `id_method`,
            `id_cart`,
            `id_order`,
            `MR_insurance`,
            `date_add`,';
        // get column names specific to a relay
        if (is_array($relay)) {
            foreach ($relay as $nameKey => $value) {
                $query .= '`MR_Selected_'.MRTools::bqSQL($nameKey).'`, ';
            }
        } elseif (is_object(($relay))) {
            foreach ($mdArrayKeys as $key) {
                if (isset($relay->{$key})) {
                    $query .= '`MR_Selected_'.MRTools::bqSQL($key).'`, ';
                }
            }
        }
        // get specific values from an order
        $query = rtrim($query, ', ') . ') VALUES ('
            . (int) $idCustomer . ', '
            . (int) $idCarrier . ', '
            . (int) $idCart . ', '
            . (int) $idOrder . ', '
            . (int) $insurance . ', '
            . 'NOW(), ';
        // get specific values for a relay
        if (is_array($relay)) {
            foreach ($relay as $nameKey => $value) {
                $query .= '"' . pSQL($value) . '", ';
            }
        } elseif (is_object(($relay))) {
            foreach ($mdArrayKeys as $key) {
                if (isset($relay->{$key})) {
                    $query .= '"' . pSQL($relay->{$key}) . '", ';
                }
            }
        }
        // clean query and execute
        $query = rtrim($query, ', ') . ')';
        $db = Db::getInstance();
        return $db->execute($query);
    }
}
