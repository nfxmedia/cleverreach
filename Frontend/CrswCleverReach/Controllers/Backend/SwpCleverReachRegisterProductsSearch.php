<?php

/**
 * CleverReach register products search backend controller
 */
class Shopware_Controllers_Backend_SwpCleverReachRegisterProductsSearch extends Shopware_Controllers_Backend_ExtJs {

    /**
     * register products search URL to CleverReach
     */
    public function registerProductsSearchAction() {
        try {
            $shopID = $this->Request()->getParam('shopID');

            $url = Shopware()->Front()->Router()->assemble(array('controller' => 'SwpCleverReach', 'action' => 'searchProducts', 'module' => 'frontend', 'shopID' => $shopID));

            $repository = Shopware()->Models()->getRepository('Shopware\Models\Shop\Shop');
            $shop = $repository->getActiveById($shopID);
            $shop->registerResources(Shopware()->Bootstrap());

            $config = $this->Plugin()->getConfig($shopID);
            if (!$config["status"]) {
                $success = false;
                $message = Shopware()->Snippets()->getNamespace('backend/swp_clever_reach/snippets')->get('product_search/message/not_configured', "This shop is not configured");
            } else {
                $api = new SoapClient($config["wsdl_url"]);

                try {
                    $result = $api->clientRegisterMyProductSearch($config["api_key"], "Shopware - " . Shopware()->Shop()->getName(), $url);
                } catch (Exception $e) {
                    $success = false;
                    $message = $e->getMessage();
                }

                if ($result->status == 'SUCCESS') {
                    // Erfolgsfall
                    $success = true;
                    $message = Shopware()->Snippets()->getNamespace('backend/swp_clever_reach/snippets')->get('product_search/message/activated', "Produkt-Suche aktivieren");
                } elseif ($result->status == 'ERROR' && $result->statuscode == 50) {
                    // Produktsuche war bereits aktiviert
                    $success = true;
                    $message = Shopware()->Snippets()->getNamespace('backend/swp_clever_reach/snippets')->get('product_search/message/already_enabled', "Produkt-Suche bereits für diesen Shop aktiviert");
                } else {
                    // anderer Unbekannter Fehler
                    $success = false;
                    $message = Shopware()->Snippets()->getNamespace('backend/swp_clever_reach/snippets')->get('product_search/message/unknown_error', "Ein unbekannter Fehler ist aufgetreten");
                }
            }
        } catch (Exception $ex) {
            $success = false;
            $message = htmlentities($ex->getMessage());
        }
        if ($message) {
            if($this->Plugin()->Config()->enable_debug){
                __d($message, __FILE__);
            }
        }
        $this->Plugin()->updateConfigs($shopID, array(
            "products_search" => $success
        ));

        $this->View()->assign(array(
            'success' => $success,
            'message' => $message,
            'next_target' => "" //this is not used for products search
        ));
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