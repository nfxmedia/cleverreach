<?php

/**
 * CleverReach Frontend Class
 * @version 4.0.2 / corrected syntax error in line 240 // 2013-10-18
 */
class Shopware_Controllers_Frontend_SwpCleverReach extends Enlight_Controller_Action implements \Shopware\Components\CSRFWhitelistAware {

    private $shopCategories;

    /**
     * implements CSRFWhitelistAware
     * @return type
     */
    public function getWhitelistedCSRFActions()
    {
        return array(
            'searchProducts'
        );
    }
    /**
     * init function for frontend controller
     */
    public static function init($mode, $params, $request, $extra_params) {
        //send data to CleverReach only if the Groups for this shop were set
        $shopID = Shopware()->Shop()->getId();
        $config = self::Plugin()->getConfig($shopID);
        $pConfig = self::Plugin()->Config();
        if ($pConfig->enable_debug) {
            __d($shopID, "shopID");
            __d($config, "Config");
        }
        if (!$config["groups"]) {
            return;
        }

        $order = array();

        switch ($mode) {
            case 'content_form':
                $data = self::prepareDataFromContentform($params['status'], $request);
                break;
            case 'account':
                $data = self::prepareDataFromAccount($params['status']);
                break;
            case 'checkout_finish':
                $order = self::prepareDataFromCheckoutFinish($params);
                $status = ($request['sNewsletter'] == "1") ? : "0";

                $data = self::prepareDataFromAccount($status);
                break;
            case 'save_register':
                $data = self::prepareDataFromDb($params);
                $registerUser = $params;
                break;
            default:
                return;
        }

        try {
            self::sendToCleverReach($params['status'], $params['email'], $data, $order, $registerUser, $extra_params);
        } catch (Exception $ex) {
            if ($pConfig->enable_debug) {
                __d($ex->getMessage());
            }
        }
    }

    /**
     * prepare user data from content form
     */
    protected static function prepareDataFromContentform($status, $request) {
        $data['anrede'] = ($request['salutation'] == 'ms') ? 'Frau' : 'Herr';
        $data['vorname'] = $request['firstname'];
        $data['nachname'] = $request['lastname'];
        $data['strasse'] = $request['street'] . ' ' . $request['streetnumber'];
        $data['postleitzahl'] = $request['zipcode'];
        $data['stadt'] = $request['city'];
        $data['newsletter'] = $status;
        if (self::Plugin()->transferOrderCode()) {
            $data['bestellcode'] = "";
        }

        return $data;
    }

    /**
     * prepare user data from user account
     */
    protected static function prepareDataFromAccount($status) {
        $customer = Shopware()->System()->sMODULES['sAdmin']->sGetUserData();

        $data['firma'] = $customer['billingaddress']['company'];
        $data['anrede'] = ($customer['billingaddress']['salutation'] == 'ms') ? 'Frau' : 'Herr';
        $data['vorname'] = $customer['billingaddress']['firstname'];
        $data['nachname'] = $customer['billingaddress']['lastname'];
        $data['strasse'] = $customer['billingaddress']['street'] . ' ' . $customer['billingaddress']['streetnumber'];
        $data['postleitzahl'] = $customer['billingaddress']['zipcode'];
        $data['stadt'] = $customer['billingaddress']['city'];
        try {
            $data['land'] = Shopware()->Db()->fetchOne("SELECT countryname FROM s_core_countries WHERE id='" . $customer['billingaddress']['countryID'] . "'");
        } catch (Exception $ex) {
            //nothing to do; this shouldn't crash, but we had some strange behaviour for a client
        }

        if ($status == 0) {
            try {
                $count = Shopware()->Db()->fetchOne("SELECT COUNT(*) FROM s_campaigns_mailaddresses WHERE email='" . $customer['additional']['user']['email'] . "'");
            } catch (Exception $ex) {
                //nothing to do;
            }

            if ($count == "0")
                $status = "0";
            else
                $status = "1";
        }

        $data['newsletter'] = $status;
        if (self::Plugin()->transferOrderCode()) {
            $data['bestellcode'] = $customer["additional"]["user"]["smShopcode"];
        }

        return $data;
    }

    /**
     * prepare data from finished order
     */
    protected static function prepareDataFromCheckoutFinish(&$params) {
        $customer = Shopware()->System()->sMODULES['sAdmin']->sGetUserData();

        $order = array();

        $order = self::getOrderData();

        $params['email'] = $customer['additional']['user']['email'];
        $params['status'] = true;

        return $order;
    }

    /**
     * prepare data from DB
     */
    protected static function prepareDataFromDb(&$params) {
        $pConfig = self::Plugin()->Config();
        $select = Shopware()->Db()
                ->select()
                ->from(array('u' => 's_user'), array(
                    'email',
                    'customergroup'
                        )
                )
                ->joinLeft(array("ua" => "s_user_attributes"), "u.id = ua.userId", array(
                    'sm_shopcode'
                        )
                )
                ->joinLeft(array("ub" => "s_user_billingaddress"), "u.id = ub.userId", array(
                    'company',
                    "salutation",
                    "firstname",
                    "lastname",
                    "street",
                    "streetnumber" => ((self::Plugin()->assertVersionGreaterThenLocal("5.0.0")) ? "" : "streetnumber"),
                    "zipcode",
                    "city"
                        )
                )
                ->joinLeft(array("cc" => "s_core_countries"), "ub.countryID = cc.id", array(
                    'countryname'
                        )
                )
                ->where("u.id = '" . $params['userId'] . "'");
        $customer = Shopware()->Db()->fetchRow($select);

        if ($pConfig->enable_debug) {
            __d($customer, "Customer");
        }

        if($customer['company']){
            $data['firma'] = $customer['company'];
        }
        if($customer['salutation']){
            $data['anrede'] = ($customer['salutation'] == 'ms') ? 'Frau' : 'Herr';
        }
        if($customer['firstname']){
            $data['vorname'] = $customer['firstname'];
        }
        if($customer['lastname']){
            $data['nachname'] = $customer['lastname'];
        }
        if($customer['street']){
            $data['strasse'] = trim($customer['street'] . ' ' . $customer['streetnumber']);
        }
        if($customer['zipcode']){
            $data['postleitzahl'] = $customer['zipcode'];
        }
        if($customer['city']){
            $data['stadt'] = $customer['city'];
        }
        if($customer['countryren']){
            $data['land'] = $customer["countryren"];
        }

        try {
            $count = Shopware()->Db()->fetchOne("SELECT COUNT(*) FROM s_campaigns_mailaddresses WHERE email='" . $customer['email'] . "'");
        } catch (Exception $ex) {
            //nothing to do;
        }

        if ($count == "0")
            $status = "0";
        else
            $status = "1";

        $data['newsletter'] = $status;
        if (self::Plugin()->transferOrderCode()) {
            $data['bestellcode'] = $customer["sm_shopcode"];
        }

        $params['email'] = $customer['email'];
        $params['status'] = true;
        $params['customergroup'] = $customer['customergroup'];
        $params['newsletter'] = $status;

        return $data;
    }

    /**
     * get order data from finished order
     */
    protected static function getOrderData() {
        $orderData = array();
        $orderDataProduct = array();

        $order = Shopware()->Session()->sOrderVariables;
        $orderID = $order['sOrderNumber'];

        foreach ($order['sBasket']['content'] as $orderProduct) {
            $orderDataProduct = array();

            $orderDataProduct['purchase_date'] = time();
            $orderDataProduct['order_id'] = $orderID;
            $orderDataProduct['product'] = $orderProduct['articlename'];
            $orderDataProduct['product_id'] = $orderProduct['articleID'];
            $orderDataProduct['quantity'] = $orderProduct['quantity'];
            $orderDataProduct['price'] = str_replace(',', '.', $orderProduct['price']);
            $orderDataProduct['source'] = 'Shopware';

            if (Shopware()->Session()->SwpCleverReachMailingID)
                $orderDataProduct['mailings_id'] = Shopware()->Session()->SwpCleverReachMailingID;

            $orderData[] = $orderDataProduct;
        }

        return $orderData;
    }

    /**
     * send data to CleverReach
     * @param type $status
     * @param type $email
     * @param type $data
     * @param type $order
     * @param type $registerUser
     * @param type $extra_params
     * @return type
     */
    protected static function sendToCleverReach($status, $email, $data, $order, $registerUser, $extra_params) {
        $pConfig = self::Plugin()->Config();
        $shopID = Shopware()->Shop()->getId();
        $config = self::Plugin()->getConfig($shopID); // api_key | wsdl_url
        if ($pConfig->enable_debug) {
            __d($config, "Config");
            __d($email, "Email");
            __d($data, "Data");
            __d($status, "status");
        }
        if(!$config["status"]){
            return;
        }
        $listAndForm = self::getListAndForm($order, $registerUser);
        if ($pConfig->enable_debug) {
            __d($listAndForm, "listAndForm");
        }
        $listID = $listAndForm["listID"];
        $formID = $listAndForm["formID"];
        if (!$listID) {
            return; //the subscription group was not defined
        }

        $api = new SoapClient($config["wsdl_url"]);

        if ($status == true) {
            // add to newsletter
            $attributesData = array();

            self::addAttributes($api, $config["api_key"], $listID);
            $postdata = "";
            foreach ($data as $dataKey => $dataValue) {
                $attributesData[] = array('key' => $dataKey, 'value' => $dataValue);
                $postdata .= ($postdata) ? "," : "";
                $postdata .= $dataKey . ":" . $dataValue; //create postdata for opt-in
            }

            $receiver = array(
                'email' => $email,
                'attributes' => $attributesData
            );

            if (count($order) > 0)
                $receiver['orders'] = $order;

            $send_optin = false;
            //check if the customer already exists
            $response = $api->receiverGetByEmail($config["api_key"], $listID, $email, 0); //000 (0) > Basic readout with (de)activation dates
            if ($response->status == "ERROR") {
                if ($response->statuscode != "20") {
                    return;
                }
                //the customer is not registered yet => add
                $response = $api->receiverAdd($config["api_key"], $listID, $receiver);
                if ($pConfig->enable_debug) {
                    __d($response, "receiverAdd");
                }
                //new created user
                if ($formID) {
                    // deacitvate from newsletter; an opt-in email will be sent instead
                    $response = $api->receiverSetInactive($config["api_key"], $listID, $email);
                    if ($pConfig->enable_debug) {
                        __d($response, "receiverSetInactive");
                    }
                    $send_optin = true;
                }
            } else {
                //the customer is already registered => update
                if (!($formID)) {
                    $receiver['activated'] = time();
                    $receiver['deactivated'] = "0";
                }
                $response = $api->receiverUpdate($config["api_key"], $listID, $receiver);
                if ($formID && $response->status == "SUCCESS") {
                    if (!$response->data->active) {
                        // send opt-in if he is inactive
                        $send_optin = true;
                    }
                }
                if ($pConfig->enable_debug) {
                    __d($response, "receiverUpdate");
                }
            }
            if ($send_optin) {
                //send the optin email to the customer
                $doidata = array(
                    "user_ip" => $extra_params["client_ip"],
                    "user_agent" => $extra_params["user_agent"],
                    "referer" => $extra_params["referer"],
                    "postdata" => $postdata,
                    "info" => $config["newsletter_extra_info"]
                );
                $response = $api->formsSendActivationMail($config["api_key"], $formID, $email, $doidata);
                if ($pConfig->enable_debug) {
                    __d($response, "formsSendActivationMail");
                }
            }
        } else {
            // deacitvate from newsletter
            $response = $api->receiverSetInactive($config["api_key"], $listID, $email);
            if ($pConfig->enable_debug) {
                __d($response, "receiverSetInactive");
            }
        }
    }

    /**
     * get list-ID assigned to the customer
     * +
     * get form-ID for opt-in
     * @param type $order
     * @param type $registerUser
     * @return type
     */
    protected static function getListAndForm($order, $registerUser) {
        $shopID = Shopware()->Shop()->getId();
        $pConfig = self::Plugin()->Config();
        $customer = Shopware()->System()->sMODULES['sAdmin']->sGetUserData();

        $userId = $customer['additional']['user']['id'];
        $groupkey = $customer['additional']['user']['customergroup'];
        $newsletter = $customer['additional']['user']['newsletter'];
        if(!$userId){
            //check if it is an update for attributes
            if ($pConfig->enable_debug) {
                __d($registerUser, 'registerUser');
            }
            $userId = $registerUser["userId"];
            $groupkey = $registerUser["customergroup"];
            $newsletter = $registerUser["newsletter"];
        }
        // 0 = Bestellkunden / 100 = Interessenten

        if (!$userId)
            $customergroup = 100;
        else {
            if (count($order) > 0 || $registerUser) {
                if ($newsletter == 0)
                    $customergroup = 0;
                else
                    $customergroup = Shopware()->Db()->fetchOne("SELECT id FROM s_core_customergroups WHERE groupkey='" . $groupkey . "'");
            } else
                $customergroup = Shopware()->Db()->fetchOne("SELECT id FROM s_core_customergroups WHERE groupkey='" . $groupkey . "'");
        }

        $list = Shopware()->Db()->fetchRow("SELECT listID, formID FROM swp_cleverreach_assignments WHERE shop='" . $shopID . "' AND customergroup='" . $customergroup . "'");

        return $list;
    }

    /**
     * set group attributes to the newsletter-group in CleverReach
     */
    protected static function addAttributes($api, $apiKey, $listID) {
        $api->groupAttributeAdd($apiKey, $listID, "Firma", "text", '');
        $api->groupAttributeAdd($apiKey, $listID, "Anrede", "gender", '');
        $api->groupAttributeAdd($apiKey, $listID, "Vorname", "text", '');
        $api->groupAttributeAdd($apiKey, $listID, "Nachname", "text", '');
        $api->groupAttributeAdd($apiKey, $listID, "Strasse", "text", '');
        $api->groupAttributeAdd($apiKey, $listID, "Postleitzahl", "text", '');
        $api->groupAttributeAdd($apiKey, $listID, "Stadt", "text", '');
        $api->groupAttributeAdd($apiKey, $listID, "Land", "text", '');
        $api->groupAttributeAdd($apiKey, $listID, "Newsletter", "text", '');
        if (self::Plugin()->transferOrderCode()) {
            $api->groupAttributeAdd($apiKey, $listID, "Bestellcode", "text", '');
        }
    }

    /**
     * perform the products search from CleverReach newsletter creation
     */
    public function searchProductsAction() {
        Shopware()->Plugins()->Controller()->ViewRenderer()->setNoRender();

        $params = $this->Request()->getParams();
        switch ($params["get"]) {
            case "filter":
                $filters = false;
                $filter = false;

                $filter->name = "Produkt";
                $filter->description = "";
                $filter->required = false;
                $filter->query_key = "product";
                $filter->type = "input";
                $filters[] = $filter;

                echo json_encode($filters);

                exit(0);

                break;
            case 'search':
                $items = false;

                $items->settings->type = "product";
                $items->settings->link_editable = false;
                $items->settings->link_text_editable = false;
                $items->settings->image_size_editable = false;

                $search = $this->Request()->product;
                $shopID = $this->Request()->getParam('shopID');

                $categoryID = Shopware()->Db()->fetchOne("SELECT category_id FROM s_core_shops WHERE id='" . $shopID . "'");

                $this->shopCategories[] = $categoryID;
                $this->getCategories($categoryID);
                $this->shopCategories = join(",", $this->shopCategories);

                $sql = "
                        SELECT articles.id
                        FROM s_articles articles
                        JOIN s_articles_categories ac ON ac.articleID = articles.id
                        WHERE (articles.name LIKE '%" . $search . "%' OR articles.description LIKE '%" . $search . "%' OR articles.description_long LIKE '%" . $search . "%')
                            AND articles.active = 1
                            AND ac.categoryID IN (" . $this->shopCategories . ")
                ";

                $product_ids = Shopware()->Db()->fetchCol($sql);

                $product_ids = array_unique($product_ids);

                if (count($product_ids) == 0)
                    exit(0);

                foreach ($product_ids as $product_id) {
                    $out = false;

                    $repository = Shopware()->Models()->getRepository('Shopware\Models\Shop\Shop');
                    $shop = $repository->getActiveById($shopID);
                    $shop->registerResources(Shopware()->Bootstrap());

                    $url = Shopware()->Modules()->System()->sSYSTEM->sPathArticleImg;
                    $url = str_replace('/media/image', '', $url);

                    $product = Shopware()->System()->sMODULES['sArticles']->sGetArticleById($product_id);
                    if ($product['linkDetailsRewrited'])
                    //$url .= str_replace('http:///', '', $product['linkDetailsRewrited']);
                        $url = $product['linkDetailsRewrited'];
                    else
                        $url .= $product['linkDetails'];

                    $out->title = $product['articleName'];
                    $out->description = $product['description_long'];
                    if(self::Plugin()->assertVersionGreaterThenLocal("5.0.0")){
                        $out->image = $product['image']['thumbnails'][0]['source'];//2 is too large
                    } else {
                        $out->image = $product['image']['src'][2];
                    }
                    $out->price = $product['price'];
                    $out->url = $url;

                    $items->items[] = $out;
                }
                echo json_encode($items);

                exit(0);

                break;
        }
    }

    /**
     * This method returns the categories for which articles should be searched
     */
    private function getCategories($categories) {
        $sql = "SELECT id
                FROM s_categories
                WHERE parent IN ($categories)";
        $children = Shopware()->Db()->fetchAll($sql);
        $new_list = "";
        foreach ($children as $cat) {
            $this->shopCategories[] = $cat["id"];
            $new_list .= ($new_list) ? "," : "";
            $new_list .= $cat["id"];
        }
        if ($new_list) {
            $this->getCategories($new_list);
        }
    }

    /**
     * Get an instance of the plugin
     * @return <type>
     */
    private static function Plugin() {
        return Shopware()->Plugins()->Frontend()->CrswCleverReach();
    }

}

?>