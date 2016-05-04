{*
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
 *  @author    Team Connector <team-connector@lengow.com>
 *  @copyright 2016 Lengow SAS
 *  @license   http://www.apache.org/licenses/LICENSE-2.0
 *}

<div class="lgw-container" id="lengow_mainsettings_wrapper" xmlns="http://www.w3.org/1999/html">
    <form class="lengow_form" method="POST">
        <input type="hidden" name="action" value="process">
        <div class="lgw-box">
            <h2>{$locale->t('global_setting.screen.notification_alert_title')|escape:'htmlall':'UTF-8'}</h2>
            {html_entity_decode($mail_report|escape:'htmlall':'UTF-8')}
        </div>
        <div class="lgw-box">
            <h2>{$locale->t('global_setting.screen.preprod_mode_title')|escape:'htmlall':'UTF-8'}</h2>
            <p>{$locale->t('global_setting.screen.preprod_mode_description')|escape:'htmlall':'UTF-8'}</p>
            {html_entity_decode($preprod_report|escape:'htmlall':'UTF-8')}
            <div id="lengow_wrapper_preprod" style="display:none;">
                {html_entity_decode($preprod_wrapper|escape:'htmlall':'UTF-8')}
            </div>
        </div>
        <div class="lgw-box">
            <h2>{$locale->t('global_setting.screen.log_file_title')|escape:'htmlall':'UTF-8'}</h2>
            <p>{html_entity_decode($locale->t('global_setting.screen.log_file_description')|escape:'htmlall':'UTF-8')}</p>
            <select id="select_log" class="lengow_select">
                <option value="" disabled selected hidden>
                    {$locale->t('global_setting.screen.please_choose_log')|escape:'htmlall':'UTF-8'}
                </option>
                {foreach from=$list_file item=file}
                    <option value="{$lengow_link->getAbsoluteAdminLink('AdminLengowMainSetting', true)|escape:'htmlall':'UTF-8'}&action=download&file={$file['short_path']|escape:'htmlall':'UTF-8'}">
                    {$file['name']|escape:'htmlall':'UTF-8'}</option>
                {/foreach}
                <option value="{$lengow_link->getAbsoluteAdminLink('AdminLengowMainSetting', true)|escape:'htmlall':'UTF-8'}&action=download_all" >
                    {$locale->t('global_setting.screen.download_all_files')|escape:'htmlall':'UTF-8'}
                </option>
            </select>
            <button type="button" id="download_log" class="lgw-btn lgw-btn-white">
                <i class="fa fa-download"></i> {$locale->t('global_setting.screen.button_download_file')|escape:'htmlall':'UTF-8'}
            </button>
        </div>
        <div class="lgw-box lgw-box-vold">
            <h2>{$locale->t('global_setting.screen.uninstall_module')|escape:'htmlall':'UTF-8'}</h2>
            <!--<p>{$locale->t('global_setting.screen.uninstall_module_description')|escape:'htmlall':'UTF-8'}</p>-->
            <p>{$locale->t('global_setting.screen.i_want_uninstall')|escape:'htmlall':'UTF-8'}</p>
            <a href="#openDeleteModal">
                <button type="button" class="lgw-btn lgw-btn-red lengow_delete_module" name="delete_module">
                    {$locale->t('global_setting.screen.button_i_want_uninstall')|escape:'htmlall':'UTF-8'}
                </button>
            </a>

        </div>
        <div class="lgw-container putasse" >
            <div class="form-group container">
                <div class="lengow_main_setting_block_content">
                    <div class="col-sm-offset-2 col-sm-10">
                        <button type="submit" class="lgw-btn lengow_submit_main_setting">
                            {$locale->t('global_setting.screen.button_save')|escape:'htmlall':'UTF-8'}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
<script type="text/javascript" src="/modules/lengow/views/js/lengow/main_setting.js"></script>
