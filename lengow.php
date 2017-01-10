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
 * @category   Lengow
 * @package    lengow
 * @author     Team Connector <team-connector@lengow.com>
 * @copyright  2017 Lengow SAS
 * @license    http://www.apache.org/licenses/LICENSE-2.0
 */

require_once _PS_MODULE_DIR_.'lengow'.DIRECTORY_SEPARATOR.'loader.php';

/**
 * Lengow
 */
class Lengow extends Module
{
    /**
     * Lengow Install Class
     */
    private $installClass;

    /**
     * Lengow Hook Class
     */
    private $hookClass;

    /**
     * Construct
     */
    public function __construct()
    {
        $this->name = 'lengow';
        $this->tab = 'export';
        $this->version = '3.0.0';
        $this->author = 'Lengow';
        $this->module_key = '92f99f52f2bc04ed999f02e7038f031c';
        $this->ps_versions_compliancy = array('min' => '1.4', 'max' => '1.7');

        parent::__construct();

        if (_PS_VERSION_ < '1.5') {
            $sep = DIRECTORY_SEPARATOR;
            require_once _PS_MODULE_DIR_.$this->name.$sep.'backward_compatibility'.$sep.'backward.php';
            $this->context = Context::getContext();
            $this->smarty = $this->context->smarty;
        }

        $this->displayName = $this->l('Lengow');
        $this->description = $this->l('Lengow allows you to easily export your product catalogue from your Prestashop store and sell on Amazon, Cdiscount, Google Shopping, Criteo, LeGuide.com, Ebay, Rakuten, Priceminister. Choose from our 1,800 available marketing channels!');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall the Lengow module?');

        $this->installClass = new LengowInstall($this);
        $this->hookClass = new LengowHook($this);

        if (self::isInstalled($this->name)) {
            if (LengowConfiguration::getGlobalValue('LENGOW_VERSION') != $this->version) {
                $this->installClass->update();
            }
        }

        $this->context = Context::getContext();
        $this->context->smarty->assign('lengow_link', new LengowLink());
    }

    /**
     * Configure Link
     * Redirect on lengow configure page
     */
    public function getContent()
    {
        $link = new LengowLink();
        $configLink = $link->getAbsoluteAdminLink('AdminLengowHome');
        Tools::redirect($configLink, '');
    }

    /**
     * Install process
     */
    public function install()
    {
        if (!parent::install()) {
            return false;
        }
        return $this->installClass->install();
    }

    /**
     * Uninstall process
     */
    public function uninstall()
    {
        if (!parent::uninstall()) {
            return false;
        }
        return $this->installClass->uninstall();
    }

    /**
     * Reset process
     */
    public function reset()
    {
        return $this->installClass->reset();
    }

    /**
     * Hook to display the icon
     *
     * @param array $args Arguments of hook
     */
    public function hookDisplayBackOfficeHeader($args)
    {
        return $this->hookClass->hookDisplayBackOfficeHeader($args);
    }

    /**
     * Hook on Home page
     *
     * @param array $args Arguments of hook
     */
    public function hookHome($args)
    {
        return $this->hookClass->hookHome($args);
    }

    /**
     * Hook on Payment page
     *
     * @param array $args Arguments of hook
     */
    public function hookPaymentTop($args)
    {
        return $this->hookClass->hookPaymentTop($args);
    }

    /**
     * Hook for generate tracker on front footer page
     *
     * @param array $args Arguments of hook
     */
    public function hookFooter($args)
    {
        return $this->hookClass->hookFooter($args);
    }

    /**
     * Hook on order confirmation page to init order's product list
     *
     * @param array $args Arguments of hook
     */
    public function hookOrderConfirmation($args)
    {
        return $this->hookClass->hookOrderConfirmation($args);
    }

    /**
     * Hook before an status update to synchronize status with lengow
     *
     * @param array $args Arguments of hook
     */
    public function hookUpdateOrderStatus($args)
    {
        return $this->hookClass->hookUpdateOrderStatus($args);
    }

    /**
     * Hook after an status update to synchronize status with lengow
     *
     * @param array $args Arguments of hook
     */
    public function hookPostUpdateOrderStatus($args)
    {
        return $this->hookClass->hookPostUpdateOrderStatus($args);
    }

    /**
     * Hook for update order if isset tracking number
     *
     * @param array $args Arguments of hook
     */
    public function hookActionObjectUpdateAfter($args)
    {
        return $this->hookClass->hookActionObjectUpdateAfter($args);
    }

    /**
     * Hook on admin page's order
     *
     * @param array $args Arguments of hook
     */
    public function hookAdminOrder($args)
    {
        return $this->hookClass->hookAdminOrder($args);
    }
}
