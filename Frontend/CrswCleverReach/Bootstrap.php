<?php

/**
 * Cleverreach Schnittstelle
 *
 * @link http://www.nfxmedia.de
 * @copyright Copyright (c) 2013-2014, nfx:MEDIA
 * @author ma, nf - cleverreach@nfxmedia.de
 * @package nfxMEDIA
 * @subpackage nfxCrswCleverReach
 * @version 5.0.1 / fix frontend searchProducts action (get the correct searched "product" parameter) + add Newsletter Opt-In Feature + remove data transfer from newsletter update during the ordering process + Plugin Manager easier configuration // 2013-11-05
 * @version 5.0.2 / use receiverGetByEmail to check if the customer already exists + remove "enable opt-in" checkbox (the forms list will alwyas be displayed; if the form is selected => the optin is enabled) + reorganize tabs // 2013-11-22
 * @version 5.0.3 / some design changes + 'newsletter_extra_info' value is 'Shopware' and it is not editable anymore + move 'export_limit' to ''First Export' tab + add 'Reset' button // 2013-11-28
 * @version 5.0.4 / fix the issue when the customer has an invalid customergroup // 2013-12-09
 * @version 5.0.5 / exclude cancelled orders from Erst-Export // 2014-04-23
 * @version 5.0.6 / fix the category tree for product search // 2014-11-14
 * @version 5.0.7 / add some try-catch to the frontend controller // 2014-12-02
 * @version 5.0.8 / add tooltips to some customer groups // 2014-12-08
 * @version 5.0.9 / the Shopware newsletter email is not sent anymore // 2015-01-21
 * @version 6.0.1 / SW5: remove streetnumber // 2015-03-20
 * @version 6.0.2 / add "individual adjustments" // 2015-04-09
 * @version 6.0.3 / fix linkDetailsRewrited // 2015-05-07
 * @version 6.0.4 / send Bestell-Code to CleverReach when the attributes are saved // 2015-05-14
 */
/*
 * development debug function
 */
if (!function_exists('__d')) {

    function __d($o, $msg = null) {
        $f = fopen(realpath(dirname(__FILE__)) . '/tmp/debug.shopware.' . date('Ymd', strtotime('Last Monday', time())), 'a+');

        if ($msg)
            fwrite($f, "$msg:\n");

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
        $this->createForm();
        $this->createTranslations();
        if ($version < "5.0.3") {
            $this->sql_v_5_0_3();
        }
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
            'parent' => $this->Menu()->findOneBy('label', 'Marketing')
        ));
    }

    /**
     * Creates the configuration fields
     * @return void
     */
    public function createForm() {
        $form = $this->Form();
        $form->setElement('text', 'individual_adjustments_code', array(
            'label' => 'individuelle Anpassungen',
            'value' => ''
        ));
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
                'individual_adjustments_code' => 'Individual adjustments'
            )
        );
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
            $settings = $this->getSettings($shopID);
            if ($settings["groups"] == true) {
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
                if ($list["listID"]) {
                    if ($args->getTemplate() == 'sNEWSLETTERCONFIRMATION' && $list["formID"]) {
                        return;
                    }
                }
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
        if($this->transferOrderCode()){
            $this->registerNamespace();
            $return = $args->getReturn();
            //__d($return, "Attributes");
            list($sql, $userId) = $return;
            $userId = $userId[0];
            $data = array();
            $data["userId"] = $userId;
            //__d($data, "UserId");
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
                name VARCHAR(250) NOT NULL DEFAULT '',
                value text NULL,
                PRIMARY KEY (name)
        )";
        $sqls[] = "CREATE TABLE IF NOT EXISTS swp_cleverreach_settings (
                shop INT(11) NOT NULL,
                type VARCHAR(100) NOT NULL,
                name VARCHAR(100) NOT NULL DEFAULT '',
                value text NULL,
                PRIMARY KEY (shop, type, name)
        )";
        $sqls[] = "
            TRUNCATE TABLE swp_cleverreach_configs;
        ";
        $sqls[] = "
            TRUNCATE TABLE swp_cleverreach_settings;
        ";
        $sqls[] = "
            INSERT INTO swp_cleverreach_configs(name, value) VALUES
            ('api_key',''),
            ('wsdl_url','http://api.cleverreach.com/soap/interface_v5.1.php?wsdl'),
            ('status',''),
            ('date','');
        ";
        $sqls[] = "
            INSERT INTO swp_cleverreach_settings(shop, type, name, value) VALUES
            (-1,'install','export_limit','50'),
            (-1,'install','newsletter_extra_info','Shopware'),
            (-1,'check','first_export','false'),
            (-1,'check','products_search','false'),
            (-1,'check','groups','false');
        ";

        foreach ($sqls as $sql)
            Shopware()->Db()->exec($sql);
    }

    /**
     * Update 'newsletter_extra_info' to 'Shopware' - this will not be editable anymore
     */
    protected function sql_v_5_0_3() {
        $sqls[] = "
            UPDATE swp_cleverreach_settings
            SET value = 'Shopware'
            WHERE name = 'newsletter_extra_info';
        ";

        foreach ($sqls as $sql)
            Shopware()->Db()->exec($sql);
    }

    /**
     * get api_key and wsdl_url from config
     * @return <type>
     */
    public function getConfig() {
        $params = array();
        $sql = "
            SELECT name, value
            FROM swp_cleverreach_configs
            ";
        $results = Shopware()->Db()->fetchAll($sql);
        foreach ($results as $result) {
            if ($result["value"] === "true")
                $result["value"] = true;
            if ($result["value"] === "false")
                $result["value"] = false;
            $params[$result["name"]] = $result["value"];
        }
        return $params;
    }

    /**
     * get the settings for a specific shop
     * @param <type> $shopId
     * @return <type>
     */
    public function getSettings($shopId) {
        $params = array();
        $sql = "
            SELECT cs1.name,
                    IFNULL(cs2.value, cs1.value) AS value
            FROM `swp_cleverreach_settings` cs1
            LEFT JOIN `swp_cleverreach_settings` cs2 ON cs1.type = cs2.type
                                                            AND cs1.name = cs2.name
                                                            AND cs2.shop = ?
            WHERE cs1.shop = -1
            ";
        $results = Shopware()->Db()->fetchAll($sql, array($shopId));
        foreach ($results as $result) {
            if ($result["value"] === "true")
                $result["value"] = true;
            if ($result["value"] === "false")
                $result["value"] = false;
            $params[$result["name"]] = $result["value"];
        }
        return $params;
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
     * Get version tag of this plugin to display in manager
     *
     * @return string
     */
    public function getVersion() {
        return '6.0.4';
    }

    /**
     * get the main info for the plugin
     */
    public function getInfo() {
        return array(
            'version' => $this->getVersion(),
            'autor' => 'CleverReach & nfx:MEDIA',
            'copyright' => 'Copyright (c) 2013-2014, CleverReach GmbH & Co. KG, nfx:MEDIA',
            'label' => 'CleverReach - E-Mail Marketing',
            'support' => 'http://support.cleverreach.de',
            'link' => 'http://www.cleverreach.de/frontend/?rk=shopware',
            'source' => '',
            'description' => '<iframe src="http://cloud-files.crsend.com/html/shopware_plugin/plugin_info.html"  style="min-height:450px; height:auto !important;width:100%" frameBorder="0"></iframe>',
            'license' => '',
            'changes' => '',
            'revision' => '4840'
        );
    }

}

?>
