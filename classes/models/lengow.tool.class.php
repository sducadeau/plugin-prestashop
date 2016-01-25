<?php
/**
 * Copyright 2016 Lengow SAS.
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
 * @copyright 2016 Lengow SAS
 * @license   http://www.apache.org/licenses/LICENSE-2.0
 */


/**
 * The Lengow Tool Class.
 *
 */
class LengowTool
{

    /**
     * Is user log in ?
     * @return bool
     */
    public function isLogged()
    {
        return (bool)Context::getContext()->cookie->lengow_toolbox;
    }

    /**
     * Logoff user
     */
    public function logOff()
    {
        unset(Context::getContext()->cookie->lengow_toolbox);
        Tools::redirect(_PS_BASE_URL_.__PS_BASE_URI__.'modules/lengow/toolbox/', '');
    }

    public function getCurrentUri()
    {
        return $_SERVER['SCRIPT_NAME'];
    }

    /**
     * Process Login Form to log User
     * @param $accessToken
     * @param $secretToken
     */
    public function processLogin($accountId, $secretToken)
    {
        if (Tools::strlen($accountId)>0 && Tools::strlen($secretToken)>0) {
            if ($this->checkBlockedIp()) {
                Tools::redirect(_PS_BASE_URL_.__PS_BASE_URI__.'modules/lengow/toolbox/login.php?blockedIP=1', '');
            }
        }
        if (_PS_VERSION_ < '1.5') {
            $shopCollection = array(array('id_shop' => 1));
        } else {
            $sql = 'SELECT id_shop FROM '._DB_PREFIX_.'shop WHERE active = 1';
            $shopCollection = Db::getInstance()->ExecuteS($sql);
        }
        foreach ($shopCollection as $shop) {
            $ai = LengowConfiguration::get('LENGOW_ACCOUNT_ID', false, null, (int)$shop['id_shop']);
            $st = LengowConfiguration::get('LENGOW_SECRET_TOKEN', false, null, (int)$shop['id_shop']);

            if (Tools::strlen($ai) > 0 && Tools::strlen($st) > 0) {
                if ($ai == $accountId && $st == $secretToken) {
                    Context::getContext()->cookie->lengow_toolbox = true;
                    $this->unblockIp();
                    Tools::redirect(_PS_BASE_URL_.__PS_BASE_URI__.'modules/lengow/toolbox/', '');
                }
            }
        }
        if (Tools::strlen($accountId)>0 && Tools::strlen($secretToken)>0) {
            $this->checkIp();
        }
    }

    /**
     * Check if Current IP is blocked
     * @return bool
     */
    public function checkBlockedIp()
    {
        $remoteIp = $_SERVER['REMOTE_ADDR'];
        $blockedIp = json_decode(LengowConfiguration::get('LENGOW_ACCESS_BLOCK_IP_3'));
        if (is_array($blockedIp) && in_array($remoteIp, $blockedIp)) {
            return true;
        }
        return false;
    }

    /**
     * Check IP with number tentative
     *
     * @param int $counter
     * @return void
     */
    public function checkIp($counter = 1)
    {
        $remoteIp = $_SERVER['REMOTE_ADDR'];
        if ($counter>3) {
            return;
        }
        $blockedIp = json_decode(LengowConfiguration::getGlobalValue('LENGOW_ACCESS_BLOCK_IP_'.$counter));
        if (!is_array($blockedIp) || !in_array($remoteIp, $blockedIp)) {
            LengowConfiguration::updateGlobalValue(
                'LENGOW_ACCESS_BLOCK_IP_'.$counter,
                is_array($blockedIp) ?
                    json_encode(array_merge($blockedIp, array($remoteIp))) :
                    json_encode(array($remoteIp))
            );
        } else {
            $this->checkIp($counter+1);
        }
    }

    /**
     * Unblock All IP tentative if success login
     */
    public function unblockIp()
    {
        $remoteIp = $_SERVER['REMOTE_ADDR'];
        for ($i = 1; $i <= 3; $i++) {
            $blockedIp = json_decode(LengowConfiguration::getGlobalValue('LENGOW_ACCESS_BLOCK_IP_'.$i));
            $blockedIp = array_diff($blockedIp, $remoteIp);
            LengowConfiguration::updateGlobalValue(
                'LENGOW_ACCESS_BLOCK_IP_'.$i,
                $blockedIp
            );
        }
    }

}
