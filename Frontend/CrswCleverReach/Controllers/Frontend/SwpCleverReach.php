<?php

/**
 * CleverReach Frontend Class
 * @version 4.0.2 / corrected syntax error in line 240 // 2013-10-18
 */
class Shopware_Controllers_Frontend_SwpCleverReach extends Enlight_Controller_Action {

    private $shopCategories;

    /**
     * init function for frontend controller
     */
    public static function init($mode, $params, $request, $extra_params) {
        //send data to CleverReach only if the Groups for this shop were set
        $shopID = Shopware()->Shop()->getId();
        $settings = self::Plugin()->getSettings($shopID);
        if ($settings["groups"] != true) {
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
            default:
                return;
        }

        self::sendToCleverReach($params['status'], $params['email'], $data, $order, $extra_params);
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
        if(self::Plugin()->transferOrderCode()){
            $data['bestellcode']= "";
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
        try{
            $data['land'] = Shopware()->Db()->fetchOne("SELECT countryname FROM s_core_countries WHERE id='" . $customer['billingaddress']['countryID'] . "'");
        } catch (Exception $ex) {
            //nothing to do; this shouldn't crash, but we had some strange behaviour for a client
        }

        if ($status == 0) {
            try{
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
        if(self::Plugin()->transferOrderCode()){
            $data['bestellcode']= $customer["additional"]["user"]["smShopcode"];
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
     */
    protected static function sendToCleverReach($status, $email, $data, $order, $extra_params) {
        $config = self::Plugin()->getConfig(); // api_key | wsdl_url

        $listAndForm = self::getListAndForm($order);
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
            $shopID = Shopware()->Shop()->getId();
            $settings = self::Plugin()->getSettings($shopID);
            //check if the customer already exists
            $response = $api->receiverGetByEmail($config["api_key"], $listID, $email, 0); //000 (0) > Basic readout with (de)activation dates
            if ($response->status == "ERROR") {
                if ($response->statuscode != "20") {
                    return;
                }
                //the customer is not registered yet => add
                $response = $api->receiverAdd($config["api_key"], $listID, $receiver);
                //new created user
                if ($formID) {
                    // deacitvate from newsletter; an opt-in email will be sent instead
                    $response = $api->receiverSetInactive($config["api_key"], $listID, $email);
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
            }
            if ($send_optin) {
                //send the optin email to the customer
                $doidata = array(
                    "user_ip" => $extra_params["client_ip"],
                    "user_agent" => $extra_params["user_agent"],
                    "referer" => $extra_params["referer"],
                    "postdata" => $postdata,
                    "info" => $settings["newsletter_extra_info"]
                );
                $response = $api->formsSendActivationMail($config["api_key"], $formID, $email, $doidata);
            }
        } else {
            // deacitvate from newsletter
            $response = $api->receiverSetInactive($config["api_key"], $listID, $email);
        }
    }

    /**
     * get list-ID assigned to the customer
     * +
     * get form-ID for opt-in
     */
    protected static function getListAndForm($order) {
        $shopID = Shopware()->Shop()->getId();
        $customer = Shopware()->System()->sMODULES['sAdmin']->sGetUserData();

        // 0 = Bestellkunden / 100 = Interessenten

        if (!$customer['additional']['user']['id'])
            $customergroup = 100;
        else {
            if (count($order) > 0) {
                if ($customer['additional']['user']['newsletter'] == 0)
                    $customergroup = 0;
                else
                    $customergroup = Shopware()->Db()->fetchOne("SELECT id FROM s_core_customergroups WHERE groupkey='" . $customer['additional']['user']['customergroup'] . "'");
            } else
                $customergroup = Shopware()->Db()->fetchOne("SELECT id FROM s_core_customergroups WHERE groupkey='" . $customer['additional']['user']['customergroup'] . "'");
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
        if(self::Plugin()->transferOrderCode()){
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
                    $out->image = $product['image']['src'][2];
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