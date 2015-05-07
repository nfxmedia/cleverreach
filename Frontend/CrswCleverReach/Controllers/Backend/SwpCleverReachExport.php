<?php
/**
 * CleverReach first export backend controller
 */
class Shopware_Controllers_Backend_SwpCleverReachExport extends Shopware_Controllers_Backend_ExtJs {
    /**
     * start the first export
     */
    public function firstExportAction() {
        $shopID = $this->Request()->getParam('shopID');
        $limit_lower = ($this->Request()->getParam('limit_lower')) ?: 0;

        $config = $this->Plugin()->getConfig();
        $settings = $this->Plugin()->getSettings($shopID);

        $export_limit = $settings["export_limit"];

        if($export_limit > 50 || $export_limit == "")
            $export_limit = 50;
        if(!$this->Request()->getParam('export_type'))
            $result = $this->processExport($limit_lower, $export_limit, $shopID, $config);
        elseif($this->Request()->getParam('export_type') == 'interested')
            $result = $this->processNewsletterInterested($limit_lower, $export_limit, $shopID, $config);

        $this->View()->assign(array(
            'success' => true,
            'message' => $result["message"],
            'next_target' => $result["next_target"]
        ));
    }
    /**
     * process the first export for registered customers
     */
    protected function processExport($limit_lower, $export_limit, $shopID, $config) {
        $data = array();

        $customerRessource = \Shopware\Components\Api\Manager::getResource('customer');
        $customers = $customerRessource->getList($limit_lower, $export_limit, array('shopId' => $shopID));

        $orderRessource = \Shopware\Components\Api\Manager::getResource('Order');
        if($customers['total'] == 0) {

            $url = $this->Front()->Router()->assemble(array('controller' => 'SwpCleverReachExport', 'action' => 'firstExport', 'export_type' => 'interested', 'shopID' => $shopID));

            $result = $this->displayHTML($url, 0, $export_limit, 0);

            return $result;
        }

        foreach($customers['data'] as $customer) {
            $newsletter = Shopware()->Db()->fetchOne('SELECT COUNT(*) FROM s_campaigns_mailaddresses WHERE email="'.$customer['email'].'"');
            $customerInfo = $customerRessource->getOne($customer['id']);

            $userData = array();
            $attributesData = array();
            $attributesData[] = array('key' => 'firma', 'value' => $customerInfo['billing']['company']);
            $attributesData[] = array('key' => 'anrede', 'value' => ($customerInfo['billing']['salutation'] == 'ms') ? 'Frau' : 'Herr');
            $attributesData[] = array('key' => 'vorname', 'value' => $customerInfo['billing']['firstName']);
            $attributesData[] = array('key' => 'nachname', 'value' => $customerInfo['billing']['lastName']);
            $attributesData[] = array('key' => 'strasse', 'value' => $customerInfo['billing']['street'] . ' ' . $customerInfo['billing']['streetNumber']);
            $attributesData[] = array('key' => 'postleitzahl', 'value' => $customerInfo['billing']['zipCode']);
            $attributesData[] = array('key' => 'stadt', 'value' => $customerInfo['billing']['city']);
            $attributesData[] = array('key' => 'land', 'value' => Shopware()->Db()->fetchOne('SELECT countryname FROM s_core_countries WHERE id="'.$customerInfo['billing']['countryId'].'"'));
            $attributesData[] = array('key' => 'newsletter', 'value' => $newsletter);
            if($this->Plugin()->transferOrderCode()){
                $attributesData[] = array('key' => 'bestellcode', 'value' => $customerInfo["attribute"]["smShopcode"]);
            }

            $orders = array();
            $orderData = array();
            $orders = $orderRessource->getList(0, 1000, array('customerId' => $customer['id']));

            foreach($orders['data'] as $order) {
                if((int)$order['number'] === (int)'0')
                    continue;
                if((int)$order['orderStatusId'] === (int)'-1')
                    continue;

                $orderInfo = $orderRessource->getOne($order['id']);

                foreach($orderInfo['details'] as $orderProduct) {
                    $orderData[] = array(
                        'purchase_date' => strtotime($order['orderTime']->format('Y-m-d H:i:s')),
                        'order_id' => $orderProduct['orderId'],
                        'product' => $orderProduct['articleName'],
                        'product_id' => $orderProduct['articleId'],
                        'quantity' => $orderProduct['quantity'],
                        'price' => $orderProduct['price'],
                        'source' => 'Shopware Erst-Export'
                    );
                }
            }

            $userData['email'] = $customer['email'];
            $userData['registered'] = strtotime($customer['firstLogin']->format('Y-m-d H:i:s'));
            $userData['activated'] = strtotime($customer['firstLogin']->format('Y-m-d H:i:s'));
            $userData['source'] = 'Shopware Erst-Export';
            $userData['attributes'] = $attributesData;
            $userData['orders'] = $orderData;

            $customergroup = Shopware()->Db()->fetchOne('SELECT id FROM s_core_customergroups WHERE groupkey="'.$customer['groupKey'].'"');

            $listID = $this->getListID($customer['shopId'], $customergroup, $newsletter);

            if($listID){
                $data[$listID][] = $userData;
            }
        }

        $result = $this->sendBatchToCleverReach($data, $config);

        if($result != null){
            return $result;
        }

        if(($limit_lower+$export_limit) >= $customers['total']) {
            $url = $this->Front()->Router()->assemble(array('controller' => 'SwpCleverReachExport', 'action' => 'firstExport', 'export_type' => 'interested', 'shopID' => $shopID));

            $result = $this->displayHTML($url, 0, $export_limit, 0, true);

            return $result;
        } else {
            $url = $this->Front()->Router()->assemble(array('controller' => 'SwpCleverReachExport', 'action' => 'firstExport', 'limit_lower' => ($limit_lower+$export_limit), 'shopID' => $shopID));

            $result = $this->displayHTML($url, $limit_lower, $export_limit, $customers['total']);

            return $result;
        }
    }
    /**
     * process the export from the newsletter interested
     */
    protected function processNewsletterInterested($limit_lower, $export_limit, $shopID, $config) {
        $data = array();
        $user = array();

        $repository = Shopware()->Models()->getRepository('Shopware\Models\Shop\Shop');
        $shop = $repository->getActiveById($shopID);
        $shop->registerResources(Shopware()->Bootstrap());

        $total_count = Shopware()->Db()->fetchOne('SELECT COUNT(*) FROM s_campaigns_mailaddresses WHERE customer=0 AND groupID="'.Shopware()->Config()->newsletterdefaultgroup.'"');
        $interested_emails = Shopware()->Db()->fetchCol('SELECT email FROM s_campaigns_mailaddresses WHERE customer=0 AND groupID="'.Shopware()->Config()->newsletterdefaultgroup.'" LIMIT '.$limit_lower.', '.$export_limit);

        foreach($interested_emails as $email) {
            if($this->Plugin()->assertVersionGreaterThenLocal("5.0.0")){
                $emailData = Shopware()->Db()->fetchRow('SELECT salutation, firstname, lastname, street, zipcode, city, added FROM s_campaigns_maildata WHERE email="'.$email.'" AND deleted IS NULL');
            } else {
                $emailData = Shopware()->Db()->fetchRow('SELECT salutation, firstname, lastname, street, streetnumber, zipcode, city, added FROM s_campaigns_maildata WHERE email="'.$email.'" AND deleted IS NULL');
            }

            $attributesData = array();
            $attributesData[] = array('key' => 'anrede', 'value' => ($emailData['salutation'] == 'ms') ? 'Frau' : 'Herr');
            $attributesData[] = array('key' => 'vorname', 'value' => $emailData['firstname']);
            $attributesData[] = array('key' => 'nachname', 'value' => $emailData['lastname']);
            $attributesData[] = array('key' => 'strasse', 'value' => $emailData['street'] . ' ' . $emailData['streetnumber']);
            $attributesData[] = array('key' => 'postleitzahl', 'value' => $emailData['zipcode']);
            $attributesData[] = array('key' => 'stadt', 'value' => $emailData['city']);
            $attributesData[] = array('key' => 'newsletter', 'value' => "1");
            if($this->Plugin()->transferOrderCode()){
                $attributesData[] = array('key' => 'bestellcode', 'value' => "");
            }

            $user[] = array(
                'email' => $email,
                'registered' => strtotime($emailData['added']),
                'activated' => strtotime($emailData['added']),
                'source' => 'Shopware Erst-Export',
                'attributes' => $attributesData
            );
        }

        $listID = Shopware()->Db()->fetchOne('SELECT listID FROM swp_cleverreach_assignments WHERE customergroup=100 AND shop="'.$shopID.'"');
        $data[$listID] = $user;

        $result = $this->sendBatchToCleverReach($data, $config);
        if($result != null){
            return $result;
        }

        if(($limit_lower+$export_limit) >= $total_count) {
            $result = $this->displayHTML(false, $limit_lower, $export_limit, $total_count);

            return $result;
        } else {
            $url = $this->Front()->Router()->assemble(array('controller' => 'SwpCleverReachExport', 'action' => 'firstExport', 'export_type' => 'interested', 'limit_lower' => ($limit_lower+$export_limit), 'shopID' => $shopID));

            $result = $this->displayHTML($url, $limit_lower, $export_limit, $total_count);

            return $result;
        }
    }
    /**
     * get listID assigned to the customer
     */
    protected function getListID($shop, $customergroup, $status) {
        if($status == 0)
            $customergroup = 0;
        elseif($status == 100)
            $customergroup = 100;

        $listID = Shopware()->Db()->fetchOne('SELECT listID FROM swp_cleverreach_assignments WHERE shop="'.$shop.'" AND customergroup="'.$customergroup.'"');

        return $listID;
    }
    /**
     * send the users to CleverReach
     */
    protected function sendBatchToCleverReach($data, $config) {
        $api = new SoapClient($config["wsdl_url"]);

        foreach($data as $listID => $listData) {
            $this->addAttributes($api, $config["api_key"], $listID);

            try {
                $response = $api->receiverAddBatch($config["api_key"], $listID, $listData);
            } catch (Exception $e) {
                return array(
                    "message" => $e->getMessage(),
                    "next_target" => "");
            }
        }
        return null;
    }
    /**
     * set group attributes to the newsletter-group in CleverReach
     */
    protected function addAttributes($api, $apiKey, $listID) {
        $api->groupAttributeAdd($apiKey, $listID, "Firma", "text", '');
        $api->groupAttributeAdd($apiKey, $listID, "Anrede", "gender", '');
        $api->groupAttributeAdd($apiKey, $listID, "Vorname", "text", '');
        $api->groupAttributeAdd($apiKey, $listID, "Nachname", "text", '');
        $api->groupAttributeAdd($apiKey, $listID, "Strasse", "text", '');
        $api->groupAttributeAdd($apiKey, $listID, "Postleitzahl", "text", '');
        $api->groupAttributeAdd($apiKey, $listID, "Stadt", "text", '');
        $api->groupAttributeAdd($apiKey, $listID, "Land", "text", '');
        $api->groupAttributeAdd($apiKey, $listID, "Newsletter", "text", '');
        if($this->Plugin()->transferOrderCode()){
            $api->groupAttributeAdd($apiKey, $listID, "Bestellcode", "text", '');
        }
    }
    /**
     * display redirect content and progress bar
     */
    function displayHTML($next_target, $start, $limit, $total, $interested = false) {
        $process = ($start+$limit) / $total * 100;

        if($process > 100) $process = 100;

        if(($start == 0 && $interested) || !$next_target) $process = 100;

        $upper = (($start + $limit) > $total) ? $total : ($start + $limit);

        $html='<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
                   <html>
                           <head>
                                   <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />';

        //if($next_target)
        //        $html .= '<meta http-equiv="refresh" content="0; URL='.$next_target.'" />';

        $html .= '<title>CleverReach-Export</title>
                                   <style type="text/css"><!--
                                   .process_rating_light .process_rating_dark {
                                           background:#1AA6EF;
                                           border-color:#1584E8;
                                           height:15px;
                                           position:relative;
                                           text-align: center;
                                   }

                                   .process_rating_light {
                                           height:15px;
                                           position:relative;
                                           border:1px solid;
                                           display:box;
                                           width: 50%;
                                           margin:130px auto 0 auto;
                           }
                                   .process_status {
                                           background: #D8E5EE;
                                           font-family: Arial;
                                           font-size: 11px;
                                           text-align: center;
                                           width:100%;
                                           height: 100%;
                                   }
                           --></style>
                   </head>
                   <body>
                   <div class="process_status">
                           <div class="process_rating_light"><div class="process_rating_dark" style="width:'.$process.'%">'.round($process, 0).'%</div></div><br />';

        if($start == 0 && $interested){
                $html .= Shopware()->Snippets()->getNamespace('backend/swp_clever_reach/snippets')->get('first_export/message/customers_exported', "Kundenstamm exportiert.");
                $html .= '<br />'.Shopware()->Snippets()->getNamespace('backend/swp_clever_reach/snippets')->get('first_export/message/exporting_prospects', "Exportiere nun Interessenten.");
        }
        elseif(!$next_target)
                $html .= Shopware()->Snippets()->getNamespace('backend/swp_clever_reach/snippets')->get('first_export/message/export_finished', "Erst-Export abgeschlossen");
        else{
                $html .= Shopware()->Snippets()->getNamespace('backend/swp_clever_reach/snippets')->get('first_export/message/export_process_from', "Exportiere Datens&auml;tze von");
                $html .= '<br />'.($start+1).' '. Shopware()->Snippets()->getNamespace('backend/swp_clever_reach/snippets')->get('first_export/message/export_process_to', "bis") .' ';
                $html .= ($upper+1).' '.Shopware()->Snippets()->getNamespace('backend/swp_clever_reach/snippets')->get('first_export/message/export_process_from_total', "von Gesamt").' '.$total;
        }

        $html .= '</div></body>
           </html>';

        return array(
            "message" => $html,
            "next_target" => $next_target);
    }
    /**
     * Get an instance of the plugin
     * @return <type>
     */
    private function Plugin() {
        return Shopware()->Plugins()->Frontend()->CrswCleverReach();
    }
}
?>