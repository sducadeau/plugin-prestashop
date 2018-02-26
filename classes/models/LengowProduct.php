<?php
/**
 * Copyright 2017 Lengow SAS.
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
 * @copyright 2017 Lengow SAS
 * @license   http://www.apache.org/licenses/LICENSE-2.0
 */

/**
 * Lengow Product Class
 */
class LengowProduct extends Product
{
    /**
     * @var array API nodes containing relevant data
     */
    public static $productApiNodes = array(
        'marketplace_product_id',
        'marketplace_status',
        'merchant_product_id',
        'marketplace_order_line_id',
        'quantity',
        'amount'
    );

    /**
     * @var Context Prestashop context instance
     */
    protected $context;

    /**
     * @var array product images
     */
    protected $images;

    /**
     * @var string image size
     */
    protected $imageSize;

    /**
     * @var Category Prestashop category instance
     */
    protected $categoryDefault;

    /**
     * @var string name of the default category
     */
    protected $categoryDefaultName;

    /**
     * @var boolean is product in sale
     */
    protected $isSale = false;

    /**
     * @var array combination of product's attributes
     */
    protected $combinations = null;

    /**
     * @var array product's features
     */
    protected $features;

    /**
     * @var Carrier Prestashop carrier instance
     */
    protected $carrier;

    /**
     * @var string all product variations
     */
    protected $variation;

    /**
     * Load a new product
     *
     * @param integer $idProduct Prestashop product id
     * @param integer $idLang Prestashop lang id
     * @param array $params all export parameters
     *
     * @throws LengowException
     */
    public function __construct($idProduct = null, $idLang = null, $params = array())
    {
        parent::__construct($idProduct, false, $idLang);
        $this->carrier = isset($params["carrier"]) ? $params["carrier"] : null;
        $this->imageSize = isset($params["image_size"]) ? $params["image_size"] : self::getMaxImageType();
        $this->context = Context::getContext();
        $this->context->language = isset($params["language"]) ? $params["language"] : Context::getContext()->language;
        // The applicable tax may be BOTH the product one AND the state one (moreover this variable is some deadcode)
        $this->tax_name = 'deprecated';
        $this->manufacturer_name = Manufacturer::getNameById((int)$this->id_manufacturer);
        $this->supplier_name = Supplier::getNameById((int)$this->id_supplier);
        $address = null;
        if (is_object($this->context->cart)
            && $this->context->cart->{Configuration::get('PS_TAX_ADDRESS_TYPE')} != null
        ) {
            $address = $this->context->cart->{Configuration::get('PS_TAX_ADDRESS_TYPE')};
        }
        if (LengowMain::compareVersion()) {
            $this->tax_rate = $this->getTaxesRate(new Address($address));
        } else {
            $cart = Context::getContext()->cart;
            if (is_object($cart) && $cart->{Configuration::get('PS_TAX_ADDRESS_TYPE')} != null) {
                $this->tax_rate = Tax::getProductTaxRate($this->id, $cart->{Configuration::get('PS_TAX_ADDRESS_TYPE')});
            } else {
                $this->tax_rate = Tax::getProductTaxRate($this->id, null);
            }
        }
        $this->new = $this->isNew();
        $this->base_price = $this->price;
        if ($this->id) {
            //reset attribute cache
            if (LengowMain::inTest()) {
                Product::getDefaultAttribute($this->id, 0, true);
            }
            $this->price = Product::getPriceStatic(
                (int)$this->id,
                false,
                null,
                2,
                null,
                false,
                true,
                1,
                false,
                null,
                null,
                null,
                $this->specificPrice
            );
            $this->unit_price = ($this->unit_price_ratio != 0 ? $this->price / $this->unit_price_ratio : 0);
        }
        if (LengowMain::compareVersion()) {
            $this->loadStockData();
        }
        if ($this->id_category_default && $this->id_category_default > 1) {
            $this->categoryDefault = new Category((int)$this->id_category_default, $idLang);
            $this->categoryDefaultName = $this->categoryDefault->name;
        } else {
            $categories = self::getProductCategories($this->id);
            if (!empty($categories)) {
                $this->categoryDefault = new Category($categories[0], $idLang);
                $this->categoryDefaultName = $this->categoryDefault->name;
            }
        }
        $this->images = $this->getImages($idLang);
        $today = date('Y-m-d H:i:s');
        if (isset($this->specificPrice) && is_array($this->specificPrice)) {
            if (array_key_exists('from', $this->specificPrice) && array_key_exists('to', $this->specificPrice)) {
                if ($this->specificPrice['from'] <= $today && $today <= $this->specificPrice['to']) {
                    $this->isSale = true;
                }
            }
        }
        $this->makeFeatures();
        $this->makeAttributes();
    }

    /**
     * Get data of current product
     *
     * @param string $name data name
     * @param integer $idProductAttribute Prestashop product attribute id
     *
     * @return string
     */
    public function getData($name, $idProductAttribute = null)
    {
        switch ($name) {
            case 'id':
                if ($idProductAttribute) {
                    return $this->id . '_' . $idProductAttribute;
                }
                return $this->id;
            case 'name':
                return LengowMain::cleanData($this->name);
            case 'reference':
                if ($idProductAttribute && $this->combinations[$idProductAttribute]['reference']) {
                    return $this->combinations[$idProductAttribute]['reference'];
                }
                return LengowMain::cleanData($this->reference);
            case 'supplier_reference':
                if ($idProductAttribute && $this->combinations[$idProductAttribute]['supplier_reference']) {
                    return $this->combinations[$idProductAttribute]['supplier_reference'];
                }
                return LengowMain::cleanData($this->getSupplierReference());
            case 'manufacturer':
                return LengowMain::cleanData($this->manufacturer_name);
            case 'category':
                return LengowMain::cleanData($this->categoryDefaultName);
            case 'breadcrumb':
                if ($this->categoryDefault) {
                    $breadcrumb = '';
                    $categories = $this->categoryDefault->getParentsCategories();
                    foreach ($categories as $category) {
                        $breadcrumb = $category['name'] . ' > ' . $breadcrumb;
                    }
                    return rtrim($breadcrumb, ' > ');
                }
                return LengowMain::cleanData($this->categoryDefaultName);
            case 'description':
                return LengowMain::cleanHtml(LengowMain::cleanData($this->description));
            case 'short_description':
                return LengowMain::cleanHtml(LengowMain::cleanData($this->description_short));
            case 'description_html':
                return LengowMain::cleanData($this->description);
            case 'short_description_html':
                return LengowMain::cleanData($this->description_short);
            case 'price':
                if ($idProductAttribute) {
                    return $this->getPrice(true, $idProductAttribute, 2, null, false, false, 1);
                }
                return $this->getPrice(true, null, 2, null, false, false, 1);
            case 'wholesale_price':
                if ($idProductAttribute && $this->combinations[$idProductAttribute]['wholesale_price']) {
                    return LengowMain::formatNumber($this->combinations[$idProductAttribute]['wholesale_price']);
                }
                return LengowMain::formatNumber($this->wholesale_price);
            case 'price_duty_free':
                if ($idProductAttribute) {
                    return $this->getPrice(false, $idProductAttribute, 2, null, false, false, 1);
                }
                return $this->getPrice(false, null, 2, null, false, false, 1);
            case 'price_sale':
                if ($idProductAttribute) {
                    return $this->getPrice(true, $idProductAttribute, 2, null, false, true, 1);
                }
                return $this->getPrice(true, null, 2, null, false, true, 1);
            case 'price_sale_duty_free':
                if ($idProductAttribute) {
                    return $this->getPrice(false, $idProductAttribute, 2, null, false, true, 1);
                }
                return $this->getPrice(false, null, 2, null, false, true, 1);
            case 'price_sale_percent':
                if ($idProductAttribute) {
                    $price = $this->getPrice(true, $idProductAttribute, 2, null, false, false, 1);
                    $priceSale = $this->getPrice(true, $idProductAttribute, 2, null, true, true, 1);
                } else {
                    $price = $this->getPrice(true, null, 2, null, false, false, 1);
                    $priceSale = $this->getPrice(true, null, 2, null, true, true, 1);
                }

                if ($priceSale && $price) {
                    return LengowMain::formatNumber(($priceSale / $price) * 100);
                }
                return 0;
            case 'quantity':
                if ($idProductAttribute) {
                    return self::getRealQuantity($this->id, $idProductAttribute);
                }
                return self::getRealQuantity($this->id);
            case 'minimal_quantity':
                if ($idProductAttribute && $this->combinations[$idProductAttribute]['minimal_quantity']) {
                    return $this->combinations[$idProductAttribute]['minimal_quantity'];
                }
                return $this->minimal_quantity;
            case 'weight':
                if ($idProductAttribute && $this->combinations[$idProductAttribute]['weight']) {
                    $weight = $this->weight + $this->combinations[$idProductAttribute]['weight'];
                } else {
                    $weight = $this->weight;
                }
                return LengowMain::formatNumber($weight) . Configuration::get('PS_WEIGHT_UNIT');
            case 'ean':
                if ($idProductAttribute && $this->combinations[$idProductAttribute]['ean13']) {
                    return $this->combinations[$idProductAttribute]['ean13'];
                }
                return $this->ean13;
            case 'upc':
                if ($idProductAttribute && $this->combinations[$idProductAttribute]['upc']) {
                    return $this->combinations[$idProductAttribute]['upc'];
                }
                return $this->upc;
            case 'ecotax':
                if ($idProductAttribute && $this->combinations[$idProductAttribute]['ecotax']) {
                    return LengowMain::formatNumber($this->combinations[$idProductAttribute]['ecotax']);
                }
                if (isset($this->ecotaxinfos)) {
                    return LengowMain::formatNumber(($this->ecotaxinfos > 0) ? $this->ecotaxinfos : $this->ecotax);
                }
                return LengowMain::formatNumber($this->ecotax);
            case 'active':
                return $this->active ? 'Enabled' : 'Disabled';
            case 'language':
                return $this->context->language->iso_code;
            case 'available':
                if ($idProductAttribute) {
                    $quantity = self::getRealQuantity($this->id, $idProductAttribute);
                } else {
                    $quantity = self::getRealQuantity($this->id);
                }
                if ($quantity <= 0) {
                    return $this->available_later;
                }
                return $this->available_now;
            case 'url':
                if (version_compare(_PS_VERSION_, '1.5', '>')) {
                    if (version_compare(_PS_VERSION_, '1.6.1.0', '>')) {
                        return $this->context->link->getProductLink(
                            $this,
                            null,
                            null,
                            null,
                            null,
                            null,
                            $idProductAttribute,
                            true,
                            false,
                            true
                        );
                    }
                    if (version_compare(_PS_VERSION_, '1.6.0.14', '>')) {
                        return $this->context->link->getProductLink(
                            $this,
                            null,
                            null,
                            null,
                            null,
                            null,
                            $idProductAttribute,
                            true
                        );
                    }
                    return $this->context->link->getProductLink(
                        $this,
                        null,
                        null,
                        null,
                        null,
                        null,
                        $idProductAttribute
                    );
                }
                return $this->context->link->getProductLink($this);
            case 'price_shipping':
                if ($idProductAttribute && $idProductAttribute != null) {
                    $price = $this->getData('price_sale', $idProductAttribute);
                    $weight = $this->getData('weight', $idProductAttribute);
                } else {
                    $price = $this->getData('price_sale');
                    $weight = $this->getData('weight');
                }
                $idZone = $this->context->country->id_zone;
                $idCurrency = $this->context->cart->id_currency;
                if (!$this->carrier) {
                    return LengowMain::formatNumber(0);
                }
                $shippingMethod = $this->carrier->getShippingMethod();
                $shippingCost = 0;
                if (!defined('Carrier::SHIPPING_METHOD_FREE') || $shippingMethod != Carrier::SHIPPING_METHOD_FREE) {
                    if ($shippingMethod == Carrier::SHIPPING_METHOD_WEIGHT) {
                        $shippingCost = LengowMain::formatNumber(
                            $this->carrier->getDeliveryPriceByWeight($weight, (int)$idZone)
                        );
                    } else {
                        $shippingCost = LengowMain::formatNumber(
                            $this->carrier->getDeliveryPriceByPrice(
                                $price,
                                (int)$idZone,
                                (int)$idCurrency
                            )
                        );
                    }
                }
                // Check if product have single shipping cost
                if ($this->additional_shipping_cost > 0) {
                    $shippingCost += $this->additional_shipping_cost;
                }
                // Tax calculation
                $defaultCountry = Configuration::get('PS_COUNTRY_DEFAULT');
                if (_PS_VERSION_ < '1.5') {
                    $idTaxRulesGroup = $this->carrier->id_tax_rules_group;
                } else {
                    $idTaxRulesGroup = $this->carrier->getIdTaxRulesGroup();
                }
                $taxRules = LengowTaxRule::getLengowTaxRulesByGroupId(
                    Configuration::get('PS_LANG_DEFAULT'),
                    $idTaxRulesGroup
                );
                foreach ($taxRules as $taxRule) {
                    if (isset($taxRule['id_country']) && $taxRule['id_country'] == $defaultCountry) {
                        $tr = new TaxRule($taxRule['id_tax_rule']);
                    }
                }
                if (isset($tr)) {
                    $t = new Tax($tr->id_tax);
                    $taxCalculator = new TaxCalculator(array($t));
                    $taxes = $taxCalculator->getTaxesAmount($shippingCost);
                    if (!empty($taxes)) {
                        foreach ($taxes as $taxe) {
                            $shippingCost += $taxe;
                        }
                    }
                }
                return LengowMain::formatNumber($shippingCost);
            case 'id_parent':
                return $this->id;
            case 'delivery_time':
                return $this->carrier->delay[$this->context->language->id];
            case 'sale_from':
                return $this->isSale ? $this->specificPrice['from'] : '';
            case 'sale_to':
                return $this->isSale ? $this->specificPrice['to'] : '';
            case 'tags':
                if (_PS_VERSION_ < '1.5') {
                    $results = Tag::getProductTags($this->id);
                    if (!($results && array_key_exists($this->context->language->id, $results))) {
                        return '';
                    }
                    $tags = '';
                    foreach ($results[$this->context->language->id] as $tagName) {
                        $tags .= $tagName . ', ';
                    }
                    $tags = rtrim($tags, ', ');
                } else {
                    $tags = $this->getTags($this->context->language->id);
                }
                return LengowMain::cleanData($tags);
            case 'meta_title':
                return LengowMain::cleanData($this->meta_title);
            case 'meta_keywords':
                return LengowMain::cleanData($this->meta_keywords);
            case 'meta_description':
                return LengowMain::cleanData($this->meta_description);
            case 'url_rewrite':
                if (version_compare(_PS_VERSION_, '1.4', '>')) {
                    if (version_compare(_PS_VERSION_, '1.6.1.0', '>')) {
                        return $this->context->link->getProductLink(
                            $this,
                            null,
                            null,
                            null,
                            null,
                            null,
                            $idProductAttribute,
                            false,
                            false,
                            true
                        );
                    }
                    return $this->context->link->getProductLink(
                        $this,
                        $this->link_rewrite,
                        null,
                        null,
                        null,
                        null,
                        $idProductAttribute
                    );
                }
                return $this->context->link->getProductLink($this, $this->link_rewrite);
            case 'type':
                if ($idProductAttribute) {
                    return 'child';
                }
                if (empty($this->combinations)) {
                    return 'simple';
                }
                return 'parent';
            case 'variation':
                return $this->variation;
            case 'currency':
                return Context::getContext()->currency->iso_code;
            case 'condition':
                return $this->condition;
            case 'supplier':
                return $this->supplier_name;
            case 'availability':
                if ($idProductAttribute) {
                    $quantity = self::getRealQuantity($this->id, $idProductAttribute);
                } else {
                    $quantity = self::getRealQuantity($this->id);
                }
                if ($quantity <= 0 && !$this->isAvailableWhenOutOfStock($this->out_of_stock)) {
                    return 0;
                }
                return 1;
            //speed up export
            case 'image_1':
            case 'image_2':
            case 'image_3':
            case 'image_4':
            case 'image_5':
            case 'image_6':
            case 'image_7':
            case 'image_8':
            case 'image_9':
            case 'image_10':
                //speed up export
                switch ($name) {
                    case 'image_1':
                        $idImage = 0;
                        break;
                    case 'image_2':
                        $idImage = 1;
                        break;
                    case 'image_3':
                        $idImage = 2;
                        break;
                    case 'image_4':
                        $idImage = 3;
                        break;
                    case 'image_5':
                        $idImage = 4;
                        break;
                    case 'image_6':
                        $idImage = 5;
                        break;
                    case 'image_7':
                        $idImage = 6;
                        break;
                    case 'image_8':
                        $idImage = 7;
                        break;
                    case 'image_9':
                        $idImage = 8;
                        break;
                    case 'image_10':
                        $idImage = 9;
                        break;
                }
                if ($idProductAttribute) {
                    if (isset($this->combinations[$idProductAttribute]['images'][$idImage])) {
                        return $this->combinations[$idProductAttribute]['images'][$idImage];
                    }
                    return '';
                }
                return isset($this->images[$idImage]) ? $this->context->link->getImageLink(
                    $this->link_rewrite,
                    $this->id . '-' . $this->images[$idImage]['id_image'],
                    $this->imageSize
                ) : '';
            default:
                if (isset($this->features[$name])) {
                    return LengowMain::cleanData($this->features[$name]['value']);
                } elseif (!is_null($idProductAttribute) &&
                    isset($this->combinations[$idProductAttribute]['attributes'][$name][1])
                ) {
                    return LengowMain::cleanData($this->combinations[$idProductAttribute]['attributes'][$name][1]);
                } elseif (isset($this->{$name})) {
                    return LengowMain::cleanData($this->{$name});
                }
                return '';
        }
    }

    /**
     * Get data attribute of current product
     *
     * @param integer $idProductAttribute Prestashop product attribute id
     * @param string $name the data name attribute
     *
     * @return string
     */
    public function getDataAttribute($idProductAttribute, $name)
    {
        return isset($this->combinations[$idProductAttribute]['attributes'][$name][1])
            ? $this->combinations[$idProductAttribute]['attributes'][$name][1]
            : '';
    }

    /**
     * Get data feature of current product
     *
     * @param string $name the data name feature
     *
     * @return string
     */
    public function getDataFeature($name)
    {
        return isset($this->features[$name]['value']) ? $this->features[$name]['value'] : '';
    }

    /**
     * Make the feature of current product
     */
    public function makeFeatures()
    {
        $features = $this->getFrontFeatures($this->context->language->id);
        if ($features) {
            foreach ($features as $feature) {
                $this->features[$feature['name']] = $feature;
            }
        }
    }

    /**
     * Make the attributes of current product
     */
    public function makeAttributes()
    {
        $combArray = array();
        $combinations = $this->getAttributesGroups($this->context->language->id);
        if (is_array($combinations)) {
            $cImages = $this->getImageUrlCombination();
            foreach ($combinations as $c) {
                $attributeId = $c['id_product_attribute'];
                $priceToConvert = Tools::convertPrice($c['price'], $this->context->currency);
                $price = Tools::displayPrice($priceToConvert, $this->context->currency);
                if (array_key_exists($attributeId, $combArray)) {
                    $combArray[$attributeId]['attributes'][$c['group_name']] = array(
                        $c['group_name'],
                        $c['attribute_name'],
                        $c['id_attribute']
                    );
                } else {
                    $combArray[$attributeId] = array(
                        'id_product_attribute' => $attributeId,
                        'attributes' => array(
                            $c['group_name'] => array(
                                $c['group_name'],
                                $c['attribute_name'],
                                $c['id_attribute']
                            )
                        ),
                        'wholesale_price' => isset($c['wholesale_price']) ? $c['wholesale_price'] : '',
                        'price' => $price,
                        'ecotax' => isset($c['ecotax']) ? $c['ecotax'] : '',
                        'weight' => $c['weight'],
                        'reference' => $c['reference'],
                        'ean13' => isset($c['ean13']) ? $c['ean13'] : '',
                        'upc' => isset($c['upc']) ? $c['upc'] : '',
                        'supplier_reference' => isset($c['supplier_reference']) ? $c['supplier_reference'] : '',
                        'minimal_quantity' => isset($c['minimal_quantity']) ? $c['minimal_quantity'] : '',
                        'images' => isset($cImages[$attributeId]) ? $cImages[$attributeId] : array(),
                    );
                }
                if (LengowMain::compareVersion()) {
                    $combArray[$attributeId]['available_date'] = strftime($c['available_date']);
                }
            }
        }
        if (isset($combArray)) {
            foreach ($combArray as $idProductAttribute => $productAttribute) {
                $name = '';
                /* In order to keep the same attributes order */
                asort($productAttribute['attributes']);
                foreach ($productAttribute['attributes'] as $attribute) {
                    $name .= $attribute[0] . ', ';
                }
                if (!$this->variation) {
                    $this->variation = rtrim($name, ', ');
                }
                if (LengowMain::compareVersion()) {
                    $combArray[$idProductAttribute]['available_date'] = (
                    $productAttribute['available_date'] != 0
                        ? date('Y-m-d', strtotime($productAttribute['available_date']))
                        : '0000-00-00'
                    );
                }
            }
        }
        $this->combinations = $combArray;
    }

    /**
     * Get combinations of current product
     *
     * @return array
     */
    public function getCombinations()
    {
        return $this->combinations;
    }

    /**
     * Get count images of current product
     *
     * @return integer
     */
    public function getCountImages()
    {
        return count($this->images);
    }

    /**
     * OVERRIDE NATIVE FONCTION : add supplier_reference, ean13, upc, wholesale_price and ecotax
     * Get all available attribute groups
     *
     * @param integer $idLang Prestashop lang id
     *
     * @return array
     */
    public function getAttributesGroups($idLang)
    {
        if (LengowMain::compareVersion()) {
            if (!Combination::isFeatureActive()) {
                return array();
            }
            $sql = 'SELECT
                ag.`id_attribute_group`,
                ag.`is_color_group`,
                agl.`name` AS group_name,
                agl.`public_name` AS public_group_name,
				a.`id_attribute`,
                al.`name` AS attribute_name,
                a.`color` AS attribute_color,
                pa.`id_product_attribute`,
				IFNULL(stock.quantity, 0) as quantity,
                product_attribute_shop.`price`,
                product_attribute_shop.`ecotax`,
                pa.`weight`,
				product_attribute_shop.`default_on`,
                pa.`reference`,
                product_attribute_shop.`unit_price_impact`,
				pa.`minimal_quantity`,
                pa.`available_date`,
                ag.`group_type`,
                ps.`product_supplier_reference` AS `supplier_reference`,
                pa.`ean13`,
                pa.`upc`,
                pa.`wholesale_price`,
                pa.`ecotax`
				FROM `' . _DB_PREFIX_ . 'product_attribute` pa
				' . Shop::addSqlAssociation('product_attribute', 'pa') . '
				' . Product::sqlStock('pa', 'pa') . '
				LEFT JOIN `' . _DB_PREFIX_ . 'product_supplier` ps
                ON (ps.`id_product_attribute` = pa.`id_product_attribute` AND ps.`id_product` = ' . (int)$this->id . ')
				LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute_combination` pac
                ON pac.`id_product_attribute` = pa.`id_product_attribute`
				LEFT JOIN `' . _DB_PREFIX_ . 'attribute` a ON a.`id_attribute` = pac.`id_attribute`
				LEFT JOIN `' . _DB_PREFIX_ . 'attribute_group` ag ON ag.`id_attribute_group` = a.`id_attribute_group`
				LEFT JOIN `' . _DB_PREFIX_ . 'attribute_lang` al ON a.`id_attribute` = al.`id_attribute`
				LEFT JOIN `' . _DB_PREFIX_ . 'attribute_group_lang` agl
                ON ag.`id_attribute_group` = agl.`id_attribute_group`
				' . Shop::addSqlAssociation('attribute', 'a') . '
				WHERE pa.`id_product` = ' . (int)$this->id . '
					AND al.`id_lang` = ' . (int)$idLang . '
					AND agl.`id_lang` = ' . (int)$idLang . '
				GROUP BY id_attribute_group, id_product_attribute
				ORDER BY ag.`position` ASC, a.`position` ASC, agl.`name` ASC';
        } else {
            $sql = 'SELECT 
                ag.`id_attribute_group`,
                ag.`is_color_group`,
                agl.`name` group_name,
                agl.`public_name` public_group_name,
                a.`id_attribute`,
                al.`name` attribute_name,
				a.`color` attribute_color,
                pa.*
				FROM `' . _DB_PREFIX_ . 'product_attribute` pa
				LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute_combination` pac
                ON (pac.`id_product_attribute` = pa.`id_product_attribute`)
				LEFT JOIN `' . _DB_PREFIX_ . 'attribute` a ON (a.`id_attribute` = pac.`id_attribute`)
				LEFT JOIN `' . _DB_PREFIX_ . 'attribute_group` ag ON (ag.`id_attribute_group` = a.`id_attribute_group`)
				LEFT JOIN `' . _DB_PREFIX_ . 'attribute_lang` al ON (a.`id_attribute` = al.`id_attribute`)
				LEFT JOIN `' . _DB_PREFIX_ . 'attribute_group_lang` agl
                ON (ag.`id_attribute_group` = agl.`id_attribute_group`)
				WHERE pa.`id_product` = ' . (int)$this->id . '
                AND al.`id_lang` = ' . (int)$idLang . '
                AND agl.`id_lang` = ' . (int)$idLang . '
				ORDER BY agl.`public_name`, al.`name`';
        }
        try {
            return Db::getInstance()->executeS($sql);
        } catch (PrestaShopDatabaseException $e) {
            return array();
        }
    }

    /**
     * Get supplier reference
     *
     * @return string
     */
    public function getSupplierReference()
    {
        if ($this->supplier_reference != '' || _PS_VERSION_ < '1.5') {
            return $this->supplier_reference;
        }
        $sql = 'SELECT `product_supplier_reference`
            FROM `' . _DB_PREFIX_ . 'product_supplier`
            WHERE `id_product` = \'' . pSQL($this->id) . '\'
            AND `id_product_attribute` = 0';
        $result = Db::getInstance()->getRow($sql);
        return $result['product_supplier_reference'];
    }

    /**
     * Publish or Un-publish to Lengow
     *
     * @param integer $productId Prestashop product id
     * @param integer $value publish value (1 : publish, 0 : unpublish)
     * @param integer $shopId Prestashop shop id
     *
     * @return boolean
     */
    public static function publish($productId, $value, $shopId)
    {
        if (!$value) {
            $sql = 'DELETE FROM ' . _DB_PREFIX_ . 'lengow_product
             WHERE id_product = ' . (int)$productId . ' AND id_shop = ' . (int)$shopId;
            Db::getInstance()->Execute($sql);
        } else {
            try {
                $sql = 'SELECT id_product FROM ' . _DB_PREFIX_ . 'lengow_product
                    WHERE id_product = ' . (int)$productId . ' AND id_shop = ' . (int)$shopId;
                $results = Db::getInstance()->ExecuteS($sql);
                if (count($results) == 0) {
                    if (_PS_VERSION_ < '1.5') {
                        return Db::getInstance()->autoExecute(
                            _DB_PREFIX_ . 'lengow_product',
                            array(
                                'id_product' => (int)$productId,
                                'id_shop' => (int)$shopId
                            ),
                            'INSERT'
                        );
                    } else {
                        return Db::getInstance()->insert(
                            'lengow_product',
                            array(
                                'id_product' => (int)$productId,
                                'id_shop' => (int)$shopId
                            )
                        );
                    }
                }
            } catch (PrestaShopDatabaseException $e) {
                return false;
            }
        }
        return true;
    }

    /**
     * For a given product, returns its real quantity
     *
     * @param integer $idProduct Prestashop product id
     * @param integer $idProductAttribute Prestashop product attribute id
     * @param integer $idWarehouse Prestashop wharehouse id
     * @param integer $idShop Prestashop shop id
     *
     * @return integer
     */
    public static function getRealQuantity($idProduct, $idProductAttribute = 0, $idWarehouse = null, $idShop = null)
    {
        if (version_compare(_PS_VERSION_, '1.5', '<')) {
            if ($idProductAttribute == 0 || $idProductAttribute == null) {
                return Product::getQuantity($idProduct);
            }
            return Product::getQuantity($idProduct, $idProductAttribute);
        } else {
            return parent::getRealQuantity($idProduct, $idProductAttribute, $idWarehouse, $idShop);
        }
    }

    /**
     * Compares found id with API ids and checks if they match
     *
     * @param LengowProduct $product Lengow product instance
     * @param array $apiDatas product ids from the API
     *
     * @return boolean if valid or not
     */
    protected static function isValidId($product, $apiDatas)
    {
        $attributes = array('reference', 'ean13', 'upc', 'id');
        if (count($product->getCombinations()) > 0) {
            foreach ($product->getCombinations() as $combination) {
                foreach ($attributes as $attributeName) {
                    foreach ($apiDatas as $idApi) {
                        if (!empty($idApi)) {
                            if ($attributeName == 'id') {
                                // Compatibility with old plugins
                                $id = str_replace('\_', '_', $idApi);
                                $id = str_replace('X', '_', $id);
                                $ids = explode('_', $id);
                                $id = $ids[0];
                                if (is_numeric($id) && $product->{$attributeName} == $id) {
                                    return true;
                                }
                            } elseif ($combination[$attributeName] === $idApi) {
                                return true;
                            }
                        }
                    }
                }
            }
        } else {
            foreach ($attributes as $attributeName) {
                foreach ($apiDatas as $idApi) {
                    if (!empty($idApi)) {
                        if ($attributeName == 'id') {
                            // Compatibility with old plugins
                            $id = str_replace('\_', '_', $idApi);
                            $id = str_replace('X', '_', $id);
                            $ids = explode('_', $id);
                            $id = $ids[0];
                            if (is_numeric($id) && $product->{$attributeName} == $id) {
                                return true;
                            }
                        }
                        if ($product->{$attributeName} === $idApi) {
                            return true;
                        }
                    }
                }
            }
        }
        return false;
    }

    /**
     * Extract cart data from API
     *
     * @param mixed $api product datas
     *
     * @return array
     */
    public static function extractProductDataFromAPI($api)
    {
        $temp = array();
        foreach (self::$productApiNodes as $node) {
            $temp[$node] = $api->{$node};
        }
        $temp['price_unit'] = (float)$temp['amount'] / (float)$temp['quantity'];
        return $temp;
    }

    /**
     * Retrieves the product sku
     *
     * @param string $attributeName attribute name
     * @param string $attributeValue attribute value
     * @param integer $idShop Prestashop shop id
     * @param array $apiDatas product ids from the API
     *
     * @return array|false
     */
    public static function matchProduct($attributeName, $attributeValue, $idShop, $apiDatas = array())
    {
        if (empty($attributeValue) || empty($attributeName)) {
            return false;
        }
        switch (Tools::strtolower($attributeName)) {
            case 'reference':
                return self::findProduct('reference', $attributeValue, $idShop);
            case 'ean':
                return self::findProduct('ean13', $attributeValue, $idShop);
            case 'upc':
                return self::findProduct('upc', $attributeValue, $idShop);
            default:
                $idsProduct = array();
                // Compatibility with old plugins
                $sku = str_replace('\_', '_', $attributeValue);
                $sku = str_replace('X', '_', $sku);
                $sku = explode('_', $sku);
                if (isset($sku[0]) && preg_match('/^[0-9]*$/', $sku[0]) && count($sku) < 3) {
                    $idsProduct['id_product'] = (int)$sku[0];
                    if (isset($sku[1])) {
                        if (preg_match('/^[0-9]*$/', $sku[1]) && count($sku) === 2) {
                            // Compatibility with old plugins -> XXX_0 product without variation
                            if ($sku[1] != 0) {
                                $idsProduct['id_product_attribute'] = (int)$sku[1];
                            }
                        } else {
                            return false;
                        }
                    }
                    $idBool = self::checkProductId($idsProduct['id_product'], $apiDatas);
                    $idAttBool = true;
                    if (isset($idsProduct['id_product_attribute'])) {
                        $idAttBool = self::checkProductAttributeId(
                            new LengowProduct($idsProduct['id_product']),
                            $idsProduct['id_product_attribute']
                        );
                    }
                    if ($idBool && $idAttBool) {
                        return $idsProduct;
                    }
                }
                return false;
        }
    }

    /**
     * Check if product id found is correct
     *
     * @param integer $idProduct Prestashop product id
     * @param array $apiDatas product ids from the API
     *
     * @return boolean
     */
    protected static function checkProductId($idProduct, $apiDatas)
    {
        if (empty($idProduct)) {
            return false;
        }
        $product = new LengowProduct($idProduct);
        if ($product->name == '' || !self::isValidId($product, $apiDatas)) {
            return false;
        }
        return true;
    }

    /**
     * Check if the product attribute exists
     *
     * @param LengowProduct $product Lengow product instance
     * @param integer $idProductAttribute Prestashop product attribute id
     *
     * @return boolean
     */
    protected static function checkProductAttributeId($product, $idProductAttribute)
    {
        if ($idProductAttribute == 0) {
            return false;
        }
        if (!array_key_exists($idProductAttribute, $product->getCombinations())) {
            return false;
        }
        return true;
    }

    /**
     * Return the product and its attribute ids
     *
     * @param string $key attribute key
     * @param string $value attribute value
     * @param integer $idShop Prestashop shop id
     *
     * @return integer|false
     */
    protected static function findProduct($key, $value, $idShop)
    {
        if (empty($key) || empty($value)) {
            return false;
        }
        if (_PS_VERSION_ >= '1.5') {
            $query = new DbQuery();
            $query->select('p.id_product');
            $query->from('product', 'p');
            $query->innerJoin('product_shop', 'ps', 'p.id_product = ps.id_product');
            $query->where('p.' . pSQL($key) . ' = \'' . pSQL($value) . '\'');
            $query->where('ps.`id_shop` = \'' . (int)$idShop . '\'');
            $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow($query);
            // If no result, search in attribute
            if ($result == '') {
                $query = new DbQuery();
                $query->select('pa.id_product, pa.id_product_attribute');
                $query->from('product_attribute', 'pa');
                $query->innerJoin('product_shop', 'ps', 'pa.id_product = pa.id_product');
                $query->where('pa.' . pSQL($key) . ' = \'' . pSQL($value) . '\'');
                $query->where('ps.`id_shop` = \'' . (int)$idShop . '\'');
                $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow($query);
            }
        } else {
            $sql = 'SELECT p.`id_product`
				FROM `' . _DB_PREFIX_ . 'product` p
				WHERE p.`' . pSQL($key) . '` = \'' . pSQL($value) . '\'';
            $result = Db::getInstance()->getRow($sql);

            if ($result == '') {
                $sql = 'SELECT pa.`id_product`, pa.`id_product_attribute`
					FROM `' . _DB_PREFIX_ . 'product_attribute` pa
					WHERE pa.`' . pSQL($key) . '` = \'' . pSQL($value) . '\'';
                $result = Db::getInstance()->getRow($sql);
            }
        }
        return $result;
    }

    /**
     * Search a product by its reference, ean, upc and id
     *
     * @param string $attributeValue attribute value
     * @param integer $idShop Prestashop shop id
     * @param array $apiDatas product ids from the API
     *
     * @return array|false
     */
    public static function advancedSearch($attributeValue, $idShop, $apiDatas)
    {
        $attributes = array('reference', 'ean', 'upc', 'ids'); // Product class attribute to search
        $idsProduct = array();
        $find = false;
        $i = 0;
        $count = count($attributes);
        while (!$find && $i < $count) {
            $idsProduct = self::matchProduct($attributes[$i], $attributeValue, $idShop, $apiDatas);
            if (!empty($idsProduct)) {
                $find = true;
            }
            $i++;
        }
        if ($find) {
            return $idsProduct;
        }
        return false;
    }

    /**
     * Calculate product without taxes using TaxManager
     *
     * @param array $product product
     * @param integer $idAddress Prestashop address id used to get tax rate
     * @param Context $context Prestashop context instance
     *
     * @return float
     */
    public static function calculatePriceWithoutTax($product, $idAddress, $context)
    {
        $taxAddress = new LengowAddress((int)$idAddress);
        if (_PS_VERSION_ >= '1.5') {
            $taxManager = TaxManagerFactory::getManager(
                $taxAddress,
                Product::getIdTaxRulesGroupByIdProduct((int)$product['id_product'], $context)
            );
            $taxCalculator = $taxManager->getTaxCalculator();
            return $taxCalculator->removeTaxes($product['price_wt']);
        } else {
            $rate = Tax::getProductTaxRate((int)$product['id_product'], (int)$idAddress);
            return $product['price_wt'] / (1 + $rate / 100);
        }
    }


    /**
     * get image url of product variations
     *
     * @return array|false
     */
    public function getImageUrlCombination()
    {
        $cImages = array();
        $psImages = $this->getCombinationImages($this->id_lang);
        $maxImage = 10;
        if ($psImages) {
            foreach ($psImages as $productAttributeId => $images) {
                foreach ($images as $image) {
                    if (!isset($cImages[$productAttributeId]) || count($cImages[$productAttributeId]) < $maxImage) {
                        $cImages[$productAttributeId][] = $this->context->link->getImageLink(
                            $this->link_rewrite,
                            $this->id . '-' . $image['id_image'],
                            $this->imageSize
                        );
                    }
                }
            }
            return $cImages;
        }
        return false;
    }

    /**
     * Get Max Image Type
     *
     * @throws LengowException cant find image size
     *
     * @return string
     */
    public static function getMaxImageType()
    {
        $sql = 'SELECT name FROM ' . _DB_PREFIX_ . 'image_type WHERE products = 1 ORDER BY width DESC';
        try {
            $result = Db::getInstance()->executeS($sql);
        } catch (PrestaShopDatabaseException $e) {
            $result = false;
        }
        if ($result) {
            return $result[0]['name'];
        } else {
            throw new LengowException(LengowMain::setLogMessage('log.export.error_cant_find_image_size'));
        }
    }
}
