<?php

require_once __DIR__ . '/Components/CSRFWhitelistAware.php';
/**
 * Cleverreach Schnittstelle
 *
 * @link http://www.nfxmedia.de
 * @copyright Copyright (c) 2013-2014, nfx:MEDIA
 * @author ma, nf - cleverreach@nfxmedia.de
 * @package nfxMEDIA
 * @subpackage nfxCrswCleverReach
 */
/*
 * development debug function
 */
if (!function_exists('__d')) {

    function __d($o, $msg = null) {
        $f = fopen(realpath(dirname(__FILE__)) . '/tmp/debug.shopware.' . date('Ymd', strtotime('Last Monday', time())), 'a+');


        fwrite($f, "---" . date("Y-m-d H:i:s") . ": \n");
        if ($msg) {
            fwrite($f, "$msg: \n");
        }

        fwrite($f, print_r($o, true));
        fwrite($f, "\n");
        fclose($f);
    }

}
/*
 * development debug function
 */
if (!function_exists('__debug')) {

    function __debug($msg) {
        echo "<pre>";
        print_r($msg);
        echo "</pre>";
        echo "<hr />";
    }

}

/**
 * Shopware standard Plugin Class
 */
class Shopware_Plugins_Frontend_CrswCleverReach_Bootstrap extends Shopware_Components_Plugin_Bootstrap {

    /**
     * Get (nice) name for plugin manager list
     */
    protected $name = 'CleverReach';

    /**
     * stores the request in preDispatch so that request is available all times
     */
    protected $request;

    /**
     * stores some request parameters in preDispatch so those parameters available all times
     */
    protected $extra_params;

    /**
     * transfer s_user_attributes.sm_shopcode
     */
    const INDIVIDUAL_ADJUSTMENTS_CODE_TRANSFER_ORDER_CODE = "XkswKdowe!";

    /**
     * register plugin namespaces
     */
    public function registerNamespace() {
        static $done = false;

        if (!$done) {
            $done = true;
            Shopware()->Loader()->registerNamespace('Shopware', $this->Path() . '/');
        }
    }

    /**
     * Plugin install method
     */
    public function install() {
        if (!$this->assertVersionGreaterThen("4.0.0"))
            throw new Enlight_Exception("This Plugin needs min shopware 4.0.0");

        $this->createMenuItems();
        $this->subscribeEvents();
        $this->createTables();
        $this->createForm();
        $this->createTranslations();

        return array('success' => true, 'invalidateCache' => array('backend', 'proxy'));
    }

    /**
     * Updates the plugin
     * @return bool
     */
    public function update($version) {
        $this->subscribeEvents();
        $this->createTables();
        $this->createForm();
        $this->createTranslations();
        return array('success' => true, 'invalidateCache' => array('backend', 'proxy'));
    }

    /**
     * Plugin uninstall method
     */
    public function uninstall() {
        $sqls[] = 'DROP TABLE IF EXISTS swp_cleverreach_assignments';
        $sqls[] = 'DROP TABLE IF EXISTS swp_cleverreach_configs';
        $sqls[] = 'DROP TABLE IF EXISTS swp_cleverreach_settings';

        foreach ($sqls as $sql)
            Shopware()->Db()->exec($sql);

        return true;
    }

    /**
     * activates the plugin
     */
    public function enable() {
        return true;
    }

    /**
     * deactivates the plugin
     */
    public function disable() {
        return true;
    }

    /**
     * create menu entries for this plugin
     */
    protected function createMenuItems() {
        $parent = $this->createMenuItem(array(
            'label' => 'CleverReach',
            'controller' => 'SwpCleverReach',
            'class' => 'cleverreachicon',
            'action' => 'index',
            'active' => 1,
            'parent' => $this->Menu()->findOneBy(array('label' => 'Marketing'))
        ));
    }

    /**
     * Creates the configuration fields
     * @return void
     */
    public function createForm() {
        $index = 0;
        $positions = array();
        $form = $this->Form();
        $form->setElement('text', 'individual_adjustments_code', array(
            'label' => 'individuelle Anpassungen',
            'value' => '',
            'scope' => \Shopware\Models\Config\Element::SCOPE_SHOP
        ));
        $positions['individual_adjustments_code'] = $index++;
        $form->setElement('checkbox', 'enable_debug', array('label' => 'Debug Mode', 'value' => true));
        $positions['enable_debug'] = $index++;
    }

    /**
     * Inserts translations for the configuration fields into the db
     * @return void
     */
    public function createTranslations() {
        $form = $this->Form();

        Shopware()->Db()->query("DELETE FROM s_core_config_element_translations WHERE element_id IN (SELECT id FROM s_core_config_elements WHERE form_id = ?)"
                , array($form->getId()));

        $translations = array(
            'en_GB' => array(
                'individual_adjustments_code' => 'Individual adjustments',
                'enable_debug' => 'Debug Mode'
            )
        );
        if($this->assertVersionGreaterThen("5.2")){
            $this->addFormTranslations($translations);
        } else {
            $shopRepository = Shopware()->Models()->getRepository('\Shopware\Models\Shop\Locale');

            foreach ($translations as $locale => $snippets) {
                $localeModel = $shopRepository->findOneBy(array('locale' => $locale));

                foreach ($snippets as $element => $snippet) {
                    if ($localeModel === null)
                        continue;

                    $elementModel = $form->getElement($element);

                    if ($elementModel === null)
                        continue;

                    $translationModel = new \Shopware\Models\Config\ElementTranslation();
                    $translationModel->setLabel($snippet);
                    $translationModel->setLocale($localeModel);
                    $elementModel->addTranslation($translationModel);
                }
            }
        }
    }

    /**
     * create Events/Hooks for the plugin
     */
    protected function subscribeEvents() {
        // CleverReach Menü-Icons
        $this->subscribeEvent('Enlight_Controller_Action_PostDispatch_Backend_Index', 'onPostDispatchBackend');

        // Backend Controller - Menü-Items
        $this->subscribeEvent('Enlight_Controller_Dispatcher_ControllerPath_Backend_SwpCleverReach', 'onGetControllerPathBackend');
        $this->subscribeEvent('Enlight_Controller_Dispatcher_ControllerPath_Backend_SwpCleverReachExport', 'onGetControllerPathBackendExport');
        $this->subscribeEvent('Enlight_Controller_Dispatcher_ControllerPath_Backend_SwpCleverReachRegisterProductsSearch', 'onGetControllerPathBackendRegisterProductsSearch');

        // Frontend Controller
        $this->subscribeEvent('Enlight_Controller_Dispatcher_ControllerPath_Frontend_SwpCleverReach', 'onGetControllerPathFrontend');

        // grab conversation tracking id from newsletter-mail-link
        $this->subscribeEvent('Enlight_Controller_Action_PostDispatch', 'onPostDispatch');

        // Newsletter register / unregister Hooks
        $this->subscribeEvent('sAdmin::sSaveRegisterNewsletter::after', 'after_sSaveRegisterNewsletter');
        $this->subscribeEvent('sAdmin::sNewsletterSubscription::after', 'after_sNewsletterSubscription');
        $this->subscribeEvent('sAdmin::sUpdateNewsletter::after', 'after_sUpdateNewsletter');
        $this->subscribeEvent('Shopware_Controllers_Frontend_Checkout::finishAction::after', 'after_finishAction');
        $this->subscribeEvent('Shopware_Controllers_Frontend_Newsletter::sendMail::replace', 'onReplaceSendNewsletterEmail');

        $this->subscribeEvent('Enlight_Controller_Action_PreDispatch', 'onPreDispatch');

        //hook user attributes changes
        $this->subscribeEvent('Shopware_Modules_Admin_SaveRegisterMainDataAttributes_FilterSql', 'onSaveRegisterMainDataAttributes');
    }

    /**
     * Register the templates directory
     */
    protected function registeTemplateDir() {
        $this->Application()->Template()->addTemplateDir(
                $this->Path() . 'Views/'
        );
    }

    /**
     * Register the snippets directory
     */
    protected function registerSnippetsDir() {
        $this->Application()->Snippets()->addConfigDir(
                $this->Path() . 'Snippets/'
        );
    }

    /**
     * Include the CleverReach image to the Stylesheet
     */
    public function onPostDispatchBackend(Enlight_Event_EventArgs $args) {
        $response = $args->getSubject()->Response();

        if ($response->isException())
            return;

        $view = $args->getSubject()->View();
        $icon = base64_encode(file_get_contents($this->Path() . '/images/cleverreach.png'));
        $icon_questionmark = base64_encode(file_get_contents($this->Path() . '/images/questionmark.png'));
        $style = '<style type="text/css">.cleverreachicon { background: url(data:image/png;base64,' . $icon . ') no-repeat 0px 0px transparent;} .cleverreach_questionmark_icon { background: url(data:image/png;base64,' . $icon_questionmark . ') no-repeat center 0px transparent;}</style>';
        $view->extendsBlock('backend/base/header/css', $style, 'append');
    }

    /**
     * get the backend controller path
     */
    public function onGetControllerPathBackend(Enlight_Event_EventArgs $args) {
        $this->registerNamespace();
        $this->registeTemplateDir();
        $this->registerSnippetsDir();

        return $this->Path() . '/Controllers/Backend/SwpCleverReach.php';
    }

    /**
     * get the backend controller path for First Export
     */
    public function onGetControllerPathBackendExport(Enlight_Event_EventArgs $args) {
        $this->registerNamespace();
        $this->registeTemplateDir();
        $this->registerSnippetsDir();

        return $this->Path() . '/Controllers/Backend/SwpCleverReachExport.php';
    }

    /**
     * get the backend controller path for register products search
     */
    public function onGetControllerPathBackendRegisterProductsSearch(Enlight_Event_EventArgs $args) {
        $this->registerNamespace();
        $this->registeTemplateDir();
        $this->registerSnippetsDir();

        return $this->Path() . '/Controllers/Backend/SwpCleverReachRegisterProductsSearch.php';
    }

    /**
     * get the frontend controller path for this plugin
     */
    public function onGetControllerPathFrontend(Enlight_Event_EventArgs $args) {
        $this->registerNamespace();

        return $this->Path() . '/Controllers/Frontend/SwpCleverReach.php';
    }

    /**
     * set conversation tracking id from newsletter-mail-link
     */
    public function onPostDispatch(Enlight_Event_EventArgs $args) {
        $this->registerNamespace();

        $request = $args->getSubject()->Request();

        if ($request->getParam('crmailing'))
            Shopware()->Session()->SwpCleverReachMailingID = $request->getParam('crmailing');
    }

    /**
     * 1. case - register formular (not used - no newsletterbox in register form)
     */
    public function after_sSaveRegisterNewsletter(Enlight_Hook_HookArgs $args) {
        $this->registerNamespace();
    }

    /**
     * 1. case: frontend content form
     */
    public function after_sNewsletterSubscription(Enlight_Hook_HookArgs $args) {
        $this->registerNamespace();

        $params = $args->getArgs(); // 0 => E-Mail-Address | 1 => UNsubscribe Status

        $data = array();
        $data['email'] = $params[0];
        $data['status'] = !$params[1];

        Shopware_Controllers_Frontend_SwpCleverReach::init('content_form', $data, $this->request->getParams(), $this->extra_params);
    }

    /**
     * 1. case: logged in user -> My Account => newsletter settings
     * 2. case: in order process klicked the checkbox for the newsletter
     */
    public function after_sUpdateNewsletter(Enlight_Hook_HookArgs $args) {
        $this->registerNamespace();

        if ($this->request->getParam("controller") == "account") {
            // remove the case "2. case: in order process klicked the checkbox for the newsletter"
            // because after this action, it will be called the finish checkout action
            // so the data will be sent to Clever Reach anyway
            $params = $args->getArgs(); // 0 => Status | 1 => E-Mail-Address

            $data = array();
            $data['email'] = $params[1];
            $data['status'] = $params[0];

            Shopware_Controllers_Frontend_SwpCleverReach::init('account', $data, $this->request->getParams(), $this->extra_params);
        }
    }

    /**
     * send order to CleverReach
     */
    public function after_finishAction(Enlight_Hook_HookArgs $args) {
        $this->registerNamespace();

        $data = array();

        Shopware_Controllers_Frontend_SwpCleverReach::init('checkout_finish', $data, $this->request->getParams(), $this->extra_params);
    }

    /**
     * stores the request in preDispatch so that request is available all times
     */
    public function onPreDispatch(Enlight_Event_EventArgs $args) {
        $this->request = $args->getRequest();
        $this->extra_params = array(
            "referer" => $this->request->getHeader('referer'),
            "user_agent" => $this->request->getHeader('user-agent'),
            "client_ip" => $this->request->getClientIp(false)
        );
    }

    /**
     * Do not send the email from Shopware
     * @param Enlight_Hook_HookArgs $args
     */
    public function onReplaceSendNewsletterEmail(Enlight_Hook_HookArgs $args) {
        try {
            $shopID = Shopware()->Shop()->getId();
            $config = $this->getConfig($shopID);
            if ($this->Config()->enable_debug) {
                __d("onReplaceSendNewsletterEmail");
                __d($shopID, "shopID");
                __d($config, "config");
            }
            if ($config["groups"]) {
                $customer = Shopware()->System()->sMODULES['sAdmin']->sGetUserData();
                if (!$customer['additional']['user']['id'])
                    $customergroup = 100; //Interessenten
                else {
                    $customergroup = Shopware()->Db()->fetchOne("SELECT id FROM s_core_customergroups WHERE groupkey='" . $customer['additional']['user']['customergroup'] . "'");
                    if (!$customergroup) {
                        $customergroup = 100; //Interessenten
                    }
                }
                $list = Shopware()->Db()->fetchRow("SELECT listID, formID FROM swp_cleverreach_assignments WHERE shop='" . $shopID . "' AND customergroup='" . $customergroup . "'");
                if ($this->Config()->enable_debug) {
                    __d($args->getTemplate(), "email template");
                    __d($customergroup, "customergroup");
                    __d($list, "list");
                }
                if ($list["listID"]) {
                    if ($args->getTemplate() == 'sNEWSLETTERCONFIRMATION' && $list["formID"]) {
                        return;
                    }
                }
            }
            if ($this->Config()->enable_debug) {
                __d("send email " . $args->getTemplate());
            }
            $args->setReturn(
                    $args->getSubject()->executeParent(
                            $args->getMethod(), $args->getArgs()
                    )
            );
        } catch (Exception $ex) {
            
        }
    }

    /**
     * hook user attributes changes => send Bestell-Code to CleverReach
     * @param Enlight_Event_EventArgs $args
     */
    public function onSaveRegisterMainDataAttributes(Enlight_Event_EventArgs $args) {
        if ($this->Config()->enable_debug) {
            __d("onSaveRegisterMainDataAttributes");
        }
        if ($this->transferOrderCode()) {
            $this->registerNamespace();
            $return = $args->getReturn();
            if ($this->Config()->enable_debug) {
                __d($return, "Attributes");
            }
            list($sql, $userId) = $return;
            $userId = $userId[0];
            $data = array();
            $data["userId"] = $userId;
            if ($this->Config()->enable_debug) {
                __d($data, "UserId");
            }
            Shopware_Controllers_Frontend_SwpCleverReach::init('save_register', $data, $this->request->getParams(), $this->extra_params);
        }
    }

    /**
     * create database tables/columns for the plugin
     */
    protected function createTables() {
        $sqls[] = 'CREATE TABLE IF NOT EXISTS swp_cleverreach_assignments (
                shop INT(11) NOT NULL,
                customergroup INT(11) NOT NULL,
                listID INT(11) DEFAULT NULL,
                formID INT(11) DEFAULT NULL,
                PRIMARY KEY (shop, customergroup)
        )';
        $sqls[] = "CREATE TABLE IF NOT EXISTS swp_cleverreach_configs (
                shop INT(11) NOT NULL,
                api_key text NULL,
                wsdl_url text NULL,
                export_limit INT(11) default '50' NOT NULL,
                newsletter_extra_info text NULL,
                first_export tinyint(1) default '0' NULL,
                products_search tinyint(1) default '0' NULL,
                groups tinyint(1) default '0' NULL,
                status tinyint(1) default '0' NULL,
                `date` timestamp NULL,
                PRIMARY KEY (shop)
        )";
        $exists = Shopware()->Db()->fetchOne("SHOW TABLES LIKE 'swp_cleverreach_configs'");
        if ($exists) {
            $exists = Shopware()->Db()->fetchOne("SHOW COLUMNS FROM swp_cleverreach_configs WHERE Field LIKE 'shop';");
            if (!$exists) {
                $sqls[] = "ALTER TABLE `swp_cleverreach_configs` ADD COLUMN `date` timestamp NULL AFTER `value`;";
                $sqls[] = "ALTER TABLE `swp_cleverreach_configs` ADD COLUMN `status` tinyint(1) default '0' AFTER `value`;";
                $sqls[] = "ALTER TABLE `swp_cleverreach_configs` ADD COLUMN `groups` tinyint(1) default '0' AFTER `value`;";
                $sqls[] = "ALTER TABLE `swp_cleverreach_configs` ADD COLUMN `products_search` tinyint(1) default '0' AFTER `value`;";
                $sqls[] = "ALTER TABLE `swp_cleverreach_configs` ADD COLUMN `first_export` tinyint(1) default '0' AFTER `value`;";
                $sqls[] = "ALTER TABLE `swp_cleverreach_configs` ADD COLUMN `newsletter_extra_info` text NULL AFTER `value`;";
                $sqls[] = "ALTER TABLE `swp_cleverreach_configs` ADD COLUMN `export_limit` INT(11) default '50' NOT NULL AFTER `value`;";
                $sqls[] = "ALTER TABLE `swp_cleverreach_configs` ADD COLUMN `wsdl_url` text NULL AFTER `value`;";
                $sqls[] = "ALTER TABLE `swp_cleverreach_configs` ADD COLUMN `api_key` text NULL AFTER `value`;";
                $sqls[] = "ALTER TABLE `swp_cleverreach_configs` ADD COLUMN `shop` INT(11) NOT NULL AFTER `value`;";
                $sqls[] = "
                        INSERT INTO swp_cleverreach_configs(shop, api_key, wsdl_url, export_limit, newsletter_extra_info, first_export, products_search, groups, status, date)
                        SELECT DISTINCT shop, (SELECT value FROM `swp_cleverreach_configs` WHERE name= 'api_key') api_key,
                                (SELECT value FROM `swp_cleverreach_configs` WHERE name= 'wsdl_url') wsdl_url, 
                                COALESCE((SELECT z.value FROM `swp_cleverreach_settings` z WHERE shop=x.shop AND z.name = 'export_limit'),50) export_limit, 
                                COALESCE((SELECT z.value FROM `swp_cleverreach_settings` z WHERE shop=x.shop AND z.name = 'newsletter_extra_info'),'Shopware') newsletter_extra_info, 
                                COALESCE((SELECT CASE z.value WHEN 'true' THEN 1 ELSE 0 END FROM `swp_cleverreach_settings` z WHERE shop=x.shop AND z.name = 'first_export'),0) first_export, 
                                COALESCE((SELECT CASE z.value WHEN 'true' THEN 1 ELSE 0 END FROM `swp_cleverreach_settings` z WHERE shop=x.shop AND z.name = 'products_search'),0) products_search, 
                                COALESCE((SELECT CASE z.value WHEN 'true' THEN 1 ELSE 0 END FROM `swp_cleverreach_settings` z WHERE shop=x.shop AND z.name = 'groups'),0) groups, 
                                (SELECT CASE value WHEN 'true' THEN 1 WHEN 'false' THEN 0 ELSE NULL END FROM `swp_cleverreach_configs` WHERE name= 'status') `status`, 
                                (SELECT NULLIF(value,'') FROM `swp_cleverreach_configs` WHERE name= 'date') date 
                        FROM `swp_cleverreach_settings`  x WHERE shop <> -1;
                        ";
                $sqls[] = "
                        DELETE FROM swp_cleverreach_configs WHERE IFNULL(name,'') <> '';
                        ";
                $sqls[] = "
                        UPDATE swp_cleverreach_configs
                        SET export_limit = CASE WHEN NULLIF(export_limit,0) IS NULL THEN 50 ELSE export_limit END,
                            newsletter_extra_info = CASE WHEN newsletter_extra_info IS NULL THEN 'Shopware' ELSE newsletter_extra_info END,
                            first_export = CASE WHEN NULLIF(first_export,0) IS NULL THEN 0 ELSE first_export END,
                            products_search = CASE WHEN NULLIF(products_search,0) IS NULL THEN 0 ELSE products_search END,
                            groups = CASE WHEN NULLIF(groups,0) IS NULL THEN 0 ELSE groups END;
                        ";
                $sqls[] = "ALTER TABLE swp_cleverreach_configs DROP COLUMN `value`;";
                $sqls[] = "ALTER TABLE swp_cleverreach_configs DROP COLUMN `name`;";
                $sqls[] = "ALTER TABLE `swp_cleverreach_configs` ADD PRIMARY KEY (`shop`);";
            }
        }
        $sqls[] = 'DROP TABLE IF EXISTS swp_cleverreach_settings';
        foreach ($sqls as $sql) {
            Shopware()->Db()->exec($sql);
        }
    }

    /**
     * get api_key and wsdl_url from config
     * @param type $shopID
     * @return boolean
     */
    public function getConfig($shopID) {
        $sql = "
            SELECT *
            FROM swp_cleverreach_configs
            WHERE shop = ?
            ";
        $result = Shopware()->Db()->fetchRow($sql, array($shopID));
        return $result;
    }

    /**
     * update the configuration with a sql statement
     * @param type $shop
     * @param type $params
     */
    public function updateConfigs($shop, $params) {
        $set = "";
        foreach ($params as $name => $value) {
            $set .= ($set) ? " , " : " ";
            $set .= "`$name` = '" . $value . "'";
        }
        if ($set) {
            $exists = Shopware()->Db()->fetchOne("SELECT shop FROM swp_cleverreach_configs WHERE shop = ?", array($shop));
            if ($exists) {
                $sql = "
                    UPDATE swp_cleverreach_configs
                    SET $set
                    WHERE shop = ?
                    ";
            } else {
                $sql = "INSERT INTO swp_cleverreach_configs SET $set, newsletter_extra_info = 'Shopware', shop = ?";
            }
            Shopware()->Db()->query($sql, array($shop));
        }
    }

    /**
     * check if the order code should be transferred to CleverReach
     * @return boolean
     */
    public function transferOrderCode() {
        try {
            $individual_adjustments_code = $this->Config()->individual_adjustments_code;
            if ($individual_adjustments_code == self::INDIVIDUAL_ADJUSTMENTS_CODE_TRANSFER_ORDER_CODE) {
                $sql = "SHOW COLUMNS FROM `s_user_attributes` WHERE Field = 'sm_shopcode'";
                $exists = Shopware()->Db()->fetchOne($sql);
                if ($exists) {
                    return true;
                }
            }
        } catch (Exception $ex) {
            
        }
        return false;
    }

    /** Get Shopware version
     *
     * @param <type> $version
     * @return <type>
     */
    public function assertVersionGreaterThenLocal($version) {
        if ($this->assertVersionGreaterThen($version)) {
            return true;
        }
        return false;
    }

    /**
     * Reads Plugins Meta Information
     * @return string
     */
    public function getInfo() {
        $info = json_decode(file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'plugin.json'), true);

        if ($info) {
            return array(
                'version' => $info['currentVersion'],
                'author' => $info['author'],
                'copyright' => $info['copyright'],
                'label' => $this->getLabel(),
                'source' => $info['source'],
                'description' => $info['description'],
                'license' => $info['license'],
                'support' => $info['support'],
                'link' => $info['link'],
                'changes' => $info['changelog'],
                'revision' => '1'
            );
        } else {
            throw new Exception('The plugin has an invalid version file.');
        }
    }

    /**
     * Returns the current version of the plugin.
     *
     * @return string|void
     * @throws Exception
     */
    public function getVersion() {
        $info = json_decode(file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'plugin.json'), true);

        if ($info) {
            return $info['currentVersion'];
        } else {
            throw new Exception('The plugin has an invalid version file.');
        }
    }

    /**
     * Get (nice) name for plugin manager list
     *
     * @return string
     */
    public function getLabel() {
        $info = json_decode(file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'plugin.json'), true);

        if ($info) {
            return $info['label']["de"];
        } else {
            throw new Exception('The plugin has an invalid version file.');
        }
    }

}

?>
