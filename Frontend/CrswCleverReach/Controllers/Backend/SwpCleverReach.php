<?php

/**
 * CleverReach backend controller
 * - register product search
 * - first export
 * - subscribers groups and opt-in forms
 */
class Shopware_Controllers_Backend_SwpCleverReach extends Shopware_Controllers_Backend_ExtJs implements \Shopware\Components\CSRFWhitelistAware {

    /**
     * implements CSRFWhitelistAware
     * @return type
     */
    public function getWhitelistedCSRFActions()
    {
        return array(
            'getShops',
            'saveShop',
            'getGroups',
            'getAssignments',
            'saveAssignment',
            'resetCleverReach',
            'checkAPI'
        );
    }
    
    /**
     * get all Shops from Shopware
     */
    public function getShopsAction() {
        $sql = "
            SELECT scs.id,
                scs.name,
                scc.api_key,
                COALESCE(scc.wsdl_url,'') wsdl_url,
                COALESCE(scc.export_limit,50) export_limit,
                COALESCE(scc.newsletter_extra_info,'Shopware') newsletter_extra_info,
                scc.first_export,
                scc.products_search,
                scc.groups,
                scc.status,
                scc.date
            FROM s_core_shops scs
            LEFT JOIN swp_cleverreach_configs AS scc
                ON scs.id = scc.shop
            ORDER BY scs.id
            ";
        $rows = Shopware()->Db()->fetchAll($sql);
        foreach ($rows as &$row) {
            $result = $this->checkAPI($row);
            $row["status"] = $result["status"];
            $row["date"] = $result["date"];
        }

        $this->View()->assign(array('data' => $rows, 'success' => true));
    }

    /**
     * save api_key and wsdl_url
     */
    public function saveShopAction() {
        $shopId = $this->Request()->getParam("id");
        $params = array(
            "wsdl_url" => $this->Request()->getParam("wsdl_url"),
            "api_key" => $this->Request()->getParam("api_key")
        );
        $this->Plugin()->updateConfigs($shopId, $params);

        $this->View()->assign(array('success' => true));
    }
    /**
     * get all Subscribers Groups and their associatted Forms from CleverReach
     */
    public function getGroupsAction() {
        $shopId = $this->Request()->getParam('shopId');
        $config = $this->Plugin()->getConfig($shopId);
        $groups = array();
        
        $select_option_groups = Shopware()->Snippets()->getNamespace('backend/swp_clever_reach/snippets')->get('assignments/groups/select_option', "auswählen");
        $select_option_forms = Shopware()->Snippets()->getNamespace('backend/swp_clever_reach/snippets')->get('assignments/forms/select_option', "kein Opt-In");
        $groups[] = array(
            "id" => -1,
            "name" => $select_option_groups,
            "forms" => array(
                "id" => -1,
                "name" => $select_option_forms
            )
        );
        if($config["status"]){
            $api = new SoapClient($config["wsdl_url"]);
            //get the Subscribers Groups from CleverReach
            $response = $api->groupGetList($config["api_key"]);
            $success = true;
            $message = "";

            if ($response->status != "SUCCESS") {
                $success = false;
                $message = $response->statuscode . ": " . $response->message;
            }
            foreach ($response->data as $group) {
                $groups[] = array(
                    "id" => $group->id,
                    "name" => $group->name
                );
            }
        }
        //get the Forms for each Subscribers Group from CleverReach
        foreach ($groups as &$group) {
            $group["forms"] = array();
            $group["forms"][] = array(
                "id" => -1,
                "name" => $select_option_forms
            );
            if($config["status"]){
                $response = $api->formsGetList($config["api_key"], $group["id"]);
                if ($response->status != "SUCCESS") {
                    $success = false;
                    $message = $response->statuscode . ": " . $response->message;
                }
                foreach ($response->data as $form) {
                    $group["forms"][] = array(
                        "id" => $form->id,
                        "name" => $form->name
                    );
                }
            }
        }

        if($message){
            if($this->Plugin()->Config()->enable_debug){
                __d($message, __FILE__);
            }
        }

        $this->View()->assign(array('data' => $groups, 'success' => $success, 'message' => $message));
    }

    /**
     * get all the Assignments from Shopware
     */
    public function getAssignmentsAction() {
        $shopId = $this->Request()->getParam("shopId");
        $sql = "
            SELECT cg.id AS customergroup,
                   cg.description,
                   IFNULL(ca.listID, -1) AS listID,
                   IFNULL(ca.formID, -1) AS formID,
                   cg.groupkey,
                   $shopId AS shopId
            FROM (
            	SELECT 0 AS id, 'Bestellkunden' AS description, 1 AS ordtype, '' AS groupkey
                UNION
                SELECT id, description, 2 AS ordtype, groupkey FROM s_core_customergroups
                UNION
                SELECT 100 AS id, 'Interessenten' AS description, 3 AS ordtype, '' AS groupkey
            ) AS cg
            LEFT JOIN swp_cleverreach_assignments ca ON cg.id = ca.customergroup
                        AND ca.shop = ?
            ORDER BY cg.ordtype, cg.description
            ";
        $rows = Shopware()->Db()->fetchAll($sql, array($shopId));

        $this->View()->assign(array('data' => $rows, 'success' => true));
    }

    /**
     * save assignment with direct SQL statements to the database
     */
    public function saveAssignmentAction() {
        $shopId = $this->Request()->getParam("shopId");
        $customergroup = $this->Request()->getParam("customergroup");
        $listID = $this->Request()->getParam("listID");
        $formID = $this->Request()->getParam("formID");
        if ($listID == -1) {
            $listID = "NULL";
        }
        if ($formID == -1) {
            $formID = "NULL";
        }
        $success = true;
        try{
            $sql = "
                    INSERT INTO swp_cleverreach_assignments(shop, customergroup, listID, formID) VALUES(?, ?, $listID, $formID)
                    ON DUPLICATE KEY UPDATE listID=$listID, formID = $formID;
                    ";

            Shopware()->Db()->query($sql, array($shopId, $customergroup));
            //set this settings as checked
            $params = array(
                "groups" => 1
            );
            $this->Plugin()->updateConfigs($shopId, $params);
        } catch (Exception $ex) {
            $success = false;
        }
        $this->View()->assign(array('success' => $success));
    }

    /**
     * delete all the assignments and the settings
     */
    public function resetCleverReachAction() {
        $shopId = $this->Request()->getParam("shopID");
        $date = date("Y-m-d H:i:s");
        $params = array(
            "wsdl_url" => "",
            "api_key" => "",
            "first_export" =>0,
            "products_search" =>0,
            "groups" =>0,
            "export_limit" => 50,
            "status" =>0,
            "date" => $date
        );
        $this->Plugin()->updateConfigs($shopId, $params);
        $sql = "
            DELETE FROM swp_cleverreach_assignments WHERE shop = ?;
        ";
        Shopware()->Db()->query($sql, array($shopId));

        $this->View()->assign(array('success' => true, 'date' => $date, "message" => ""));
    }

    /**
     * call clientGetDetails in order to check if api_key is correct
     */
    public function checkAPIAction() {
        $success = false;
        $shopId = $this->Request()->getParam("shopId");
        $wsdl_url = $this->Request()->getParam("wsdl_url");
        $api_key = $this->Request()->getParam("api_key");
        $result["date"] = date("Y-m-d H:i:s");
        if ($wsdl_url && $api_key && $shopId) {
            try{
                $shop = array(
                    "id" => $shopId,
                    "wsdl_url" => $wsdl_url,
                    "api_key" => $api_key
                );
                $result = $this->checkAPI($shop);
                $success = $result["status"];
            } catch (Exception $ex) {
                $success = false;
                if($this->Plugin()->Config()->enable_debug){
                    __d($ex->getMessage(), __FILE__);
                }
            }
                
        }

        $this->View()->assign(array('success' => $success, "date" => $result["date"]));
    }

    /**
     * call clientGetDetails in order to check if api_key is correct
     * @param type $shop
     * @return type
     */
    private function checkAPI($shop) {
        $result = array(
            "status" => 0,
            "date" => date("Y-m-d H:i:s")
        );
        if ($shop["api_key"]) {
            try{
                $api = new SoapClient($shop["wsdl_url"]);
                $response = $api->clientGetDetails($shop["api_key"]);
                $result["status"] = ($response->status == "SUCCESS");
            } catch (Exception $ex) {
                if($this->Plugin()->Config()->enable_debug){
                    __d($ex->getMessage(), __FILE__);
                }
            }
        }
        $this->Plugin()->updateConfigs($shop["id"], $result);
        return $result;
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