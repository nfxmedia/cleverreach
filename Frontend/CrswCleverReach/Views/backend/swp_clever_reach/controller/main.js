//{namespace name=backend/swp_clever_reach/snippets}
Ext.define('Shopware.apps.SwpCleverReach.controller.Main', {
    extend: 'Ext.app.Controller',
    stores: ['Shop'],
    views: ['main.Window', 'shop.List', 'shop.Details', 'shop.Install', 'shop.FirstExport', 'shop.Assignments'],
    mainWindow: null,
    snippets: {
        messages: {
            title_success: '{s name=clever_reach/messages/title_success}Daten gültig{/s}',
            text_success: '{s name=clever_reach/messages/text_success}Die Daten sind gültig{/s}',
            title_error: '{s name=clever_reach/messages/title_error}Daten ungültig{/s}',
            text_error: '{s name=clever_reach/messages/text_error}Die Daten sind ungültig{/s}',
            title_error_msg: '{s name=clever_reach/messages/title_error_msg}Fehler{/s}',
            text_error_msg: '{s name=clever_reach/messages/text_error_msg}Beim Laden der Daten ist ein Fehler aufgetreten...{/s}',
            title_reset: '{s name=clever_reach/messages/title_reset}Reset{/s}',
            confirm_reset: '{s name=clever_reach/messages/confirm_reset}Sind Sie sicher, dass Sie die Zuweisungen zurücksetzen möchten?{/s}',
            text_reset: '{s name=clever_reach/messages/text_reset}Die Daten sind gültig{/s}',
            module: '{s name=clever_reach/messages/module}CleverReach{/s}'
        }
    },
    refs: [
        {
            ref: "detailsForm",
            selector: "swp_clever_reach-shop-details"
        },
    ],
    /**
     * init stores and display the window
     */
    init: function() {
        var me = this;

        me.control({
            'swp_clever_reach-shop-list': {
                selectionchange: me.onSelectShop
            },
            'swp_clever_reach-shop-install': {
                saveAndCheck: me.onSaveAndCheck,
                onReset: me.onReset,
                onProductsSearch: me.onProductsSearch
            },
            'swp_clever_reach-shop-assignments': {
                'beforeedit': me.onBeforeEditAssignment,
                'edit': me.onEditAssignment
            },
            'swp_clever_reach-shop-first-export button': {
                'click': me.onFirstExport
            }
        });

        //init stores
        me.mainWindow = me.getView('main.Window').create({
            shopStore: me.getStore('Shop')
        }).show();
        me.mainWindow.setLoading(true);
        me.mainWindow.formsStore = me.getStore('Form');
        me.mainWindow.assignmentsStore = me.getStore('Assignment');
        me.mainWindow.groupsStore = me.getStore('Group');
        me.mainWindow.groupsStore.on('load', me.onLoadGroup, me);
        me.mainWindow.shopsStore = me.getStore('Shop');
        me.mainWindow.shopsStore.on('load', me.onLoadShops, me);

        me.callParent(arguments);
    },
    /**
     * after the shops list is loaded
     */
    onLoadShops: function(store, records, success) {
        var me = this;
        if (success !== true || !records.length) {
            if (success !== true) {
                var message;
                if (store.getProxy().getReader().rawData) {
                    message = store.getProxy().getReader().rawData.message;
                } else {
                    message = me.snippets.messages.text_error_msg;
                }
                Shopware.Notification.createGrowlMessage(me.snippets.messages.title_error_msg, message, me.snippets.messages.module);
            }
            me.mainWindow.setLoading(false);
            return;
        }
        //check API for each shop
        store.each(function(record)
        {
            if (record.get("status")) {
                Shopware.Notification.createGrowlMessage(me.snippets.messages.title_success, record.get("name") + ": " + me.snippets.messages.text_success, me.snippets.messages.module);
            }
            else
            {
                Shopware.Notification.createGrowlMessage(me.snippets.messages.title_error, record.get("name") + ": " + me.snippets.messages.text_error, me.snippets.messages.module);
            }
        });
        me.mainWindow.setLoading(false);
    },
    /**
     * select a shop
     */
    onSelectShop: function(table, records) {
        var me = this,
                record = records.length ? records[0] : null;
        if (record) {
            me.loadShop(record);
        } else {
            if (me.mainWindow.tabs.items.items.length > 0) {
                me.mainWindow.tabs.remove(me.mainWindow.tabs.items.items[0], true);
            }
        }
    },
    /**
     * load the details of a shop
     */
    loadShop: function(record) {
        var me = this/*,
                detailsForm*/;
        me.mainWindow.setLoading(true);
        me.mainWindow.record = record;
        me.mainWindow.groupsStore.load({
            params: {
                shopId: record.get("id")
            }
        });
    },
    /**
     * refresh the forms (just display the forms associated with the selected group) when the groups/forms are edited
     */
    onBeforeEditAssignment: function(editor, e) {
        var me = this,
                combo = editor.grid.getPlugin('rowEditing').editor.form.findField('formID');

        combo.bindStore(me.mainWindow.groupsStore.getById(e.record.get('listID')).getForms());
    },
    /**
     * save assignment
     */
    onEditAssignment: function(editor, event) {
        var me = this,
                record = event.record;

        if (!record.dirty) {
            return;
        }
        editor.grid.setLoading(true);
        editor.grid.store.loadData([record], true);
        editor.grid.store.sync({
            success: function(response, operation) {
                //refresh settings grid
                me.setConfigValues("groups", editor.grid.store.getProxy().getReader().rawData);
                editor.grid.setLoading(false);
            },
            failure: function(response) {
                response = editor.grid.store.getProxy().getReader().rawData.message;
                Shopware.Notification.createGrowlMessage(me.snippets.messages.title_error_msg, response, me.snippets.messages.module);
                editor.grid.setLoading(false);
            },
            scope: this
        });
    },
    /**
     * the tabs will be enabled only in case the connection to CleverReach is made
     */
    setTabsDisabled: function(value) {
        var me = this;

        me.getDetailsForm().first_exportForm.setDisabled(value);
        me.getDetailsForm().installForm.assignmentsForm.setDisabled(value);
        me.getDetailsForm().installForm.productsSearchButton.setDisabled(value);
    },
    /**
     * save api_key and wsdl_url; check the connection afterwards
     */
    onSaveAndCheck: function(view) {
        var me = this,
                form = me.getDetailsForm().installForm.getForm(),
                record = me.getDetailsForm().installForm.record;

        me.mainWindow.setLoading(true);

        record.set("api_key", form.findField("api_key").getValue());
        record.set("wsdl_url", form.findField("wsdl_url").getValue());

        if (!record.dirty) {
            //the values were not changed => just checkAPI
            me.checkAPI(record.get("id"));
            return;
        }
        record.save({
            success: function() {
                var response = record.getProxy().getReader().rawData;
                if (response.success)
                {
                    me.checkAPI(record.get("id"));
                }
                else
                {
                    me.mainWindow.setLoading(false);
                    Shopware.Notification.createGrowlMessage(me.snippets.messages.title_error, me.snippets.messages.text_error, me.snippets.messages.module);
                    me.setTabsDisabled(true);
                }
            },
            failure: function(response, opt) {
                me.mainWindow.setLoading(false);
                response = record.getProxy().getReader().rawData.message;
                if (response == undefined) {
                    response = "";
                }
                Shopware.Notification.createGrowlMessage(me.snippets.messages.title_error, record.get("name") + ": " + response, me.snippets.messages.module);
                me.setTabsDisabled(true);
            }
        });
    },
    /**
     * products search activation
     */
    onReset: function() {
        var me = this,
                action = 'reset',
                record = me.getDetailsForm().installForm.record,
                shopId = record.get('id'),
                url = '{url action=resetCleverReach}',
                resultsPanel = null;

        Ext.MessageBox.confirm(me.snippets.messages.title_reset, me.snippets.messages.confirm_reset, function(response) {
            if (response !== 'yes') {
                return false;
            }
            me.mainWindow.setLoading(true);

            me.callAPI(url, shopId, resultsPanel, action);
        });
    },
    /**
     * products search activation
     */
    onProductsSearch: function(record) {
        var me = this,
                action = 'products_search',
                record = me.getDetailsForm().installForm.record,
                shopId = record.get('id'),
                url = '{url controller=SwpCleverReachRegisterProductsSearch action=registerProductsSearch}',
                resultsPanel = null;

        me.mainWindow.setLoading(true);

        me.callAPI(url, shopId, resultsPanel, action);
    },
    /**
     * first export or products search activation - call API method
     */
    callAPI: function(url, shopId, resultsPanel, action) {
        var me = this;

        Ext.Ajax.timeout = 300000;
        Ext.Ajax.request({
            url: url,
            params: {
                shopID: shopId
            },
            success: function(response, operation)
            {
                response = Ext.decode(response.responseText);
                if (response.success)
                {
                    if (resultsPanel != null) {
                        resultsPanel.update(response.message);
                    } else {
                        Shopware.Notification.createGrowlMessage(me.snippets.messages.title_success, response.message, me.snippets.messages.module);
                    }
                    if (response.next_target) {
                        //call API again
                        me.callAPI(response.next_target, shopId, resultsPanel, action);
                    } else {
                        //it's finished
                        me.setConfigValues(action, response);
                        if(action == "reset"){
                            me.mainWindow.groupsStore.load({
                                params: {
                                    shopId: shopId
                                }
                            });
                        }
                    }
                }
                else
                {
                    me.mainWindow.setLoading(false);
                    var message = me.snippets.messages.text_error;
                    if (response.message) {
                        message = response.message;
                    }
                    if (resultsPanel != null) {
                        resultsPanel.update(message);
                    }
                    Shopware.Notification.createGrowlMessage(me.snippets.messages.title_error, message, me.snippets.messages.module);
                }
            },
            failure: function(response)
            {
                me.mainWindow.setLoading(false);
                response = response.statusText;
                Shopware.Notification.createGrowlMessage(me.snippets.messages.title_error, response, me.snippets.messages.module);
            }
        });
    },
    /**
     * make a call to CleverReach API in order to check the connection status
     */
    checkAPI: function(shopId) {
        var me = this,
                form = me.getDetailsForm().installForm.getForm();

        Ext.Ajax.request({
            url: '{url action=checkAPI}',
            params: {
                shopId: shopId,
                api_key: form.findField("api_key").getValue(),
                wsdl_url: form.findField("wsdl_url").getValue()
            },
            success: function(response, operation)
            {
                response = Ext.decode(response.responseText);
                if (response.success)
                {
                    Shopware.Notification.createGrowlMessage(me.snippets.messages.title_success, me.snippets.messages.text_success, me.snippets.messages.module);
                    me.setTabsDisabled(false);
                    //load groups, forms, assignments
                    me.mainWindow.groupsStore.load({
                        params: {
                            shopId: shopId
                        }
                    });
                }
                else
                {
                    Shopware.Notification.createGrowlMessage(me.snippets.messages.title_error, me.snippets.messages.text_error, me.snippets.messages.module);
                    me.setTabsDisabled(true);
                }
                me.setConfigValues("checkAPI", response);
            },
            failure: function(response)
            {
                response = response.statusText;
                Shopware.Notification.createGrowlMessage(me.snippets.messages.title_error, response, me.snippets.messages.module);
                me.setTabsDisabled(true);
                me.mainWindow.setLoading(false);
            }});
    },
    /**
     * after the subscriberes groups are loaded, create a store with all the forms
     * load the assignemnts afterwards
     */
    onLoadGroup: function(store, records, success) {
        var me = this, detailsForm;
        if (success !== true || !records.length) {
            if (success !== true) {
                var message;
                if (store.getProxy().getReader().rawData) {
                    message = store.getProxy().getReader().rawData.message;
                } else {
                    message = me.snippets.messages.text_error_msg;
                }
                Shopware.Notification.createGrowlMessage(me.snippets.messages.title_error_msg, message, me.snippets.messages.module);
            }
            me.mainWindow.setLoading(false);
            return;
        }
        //create formStore with distinct forms (they should be distinct anyway but...)
        store.each(function(record)
        {
            record.getForms().each(function(form_record)
            {
                var id = form_record.get('id'),
                        form_record2 = me.mainWindow.formsStore.getById(id);
                if (form_record2 instanceof Ext.data.Model) {
                    //nothing to do; the record is already added
                } else {
                    me.mainWindow.formsStore.add([form_record]);
                }
            });
        });
        //load assignments
        me.mainWindow.assignmentsStore.load({
            params: {
                shopId: me.mainWindow.record.get("id")
            },
            callback: function(records, response)
            {
                if (me.mainWindow.tabs.items.items.length > 0) {
                    me.mainWindow.tabs.remove(me.mainWindow.tabs.items.items[0], true);
                }
                detailsForm = Ext.create('Shopware.apps.SwpCleverReach.view.shop.Details', {
                    record: me.mainWindow.record,
                    groupsStore: me.mainWindow.groupsStore,
                    formsStore: me.mainWindow.formsStore,
                    assignmentsStore: me.mainWindow.assignmentsStore
                });
                detailsForm.loadRecord(me.mainWindow.record);
                me.mainWindow.tabs.add(detailsForm);
                me.setTabsDisabled(!me.mainWindow.record.get("status"));
                me.mainWindow.setLoading(false);
                if (!response.success) {
                    response = response.error.statusText;
                    Shopware.Notification.createGrowlMessage(me.snippets.messages.title_error_msg, response, me.snippets.messages.module);
                    return;
                }
            }
        });
    },
    /**
     * first export
     */
    onFirstExport: function(button) {
        var me = this,
                action = 'first_export',
                url = '{url controller=SwpCleverReachExport action=firstExport}',
                resultsPanel = button.up('form').query('panel[name=apiResults]')[0],
                form = me.getDetailsForm().first_exportForm.getForm(),
                record = me.getDetailsForm().first_exportForm.record,
                export_limit = form.findField('export_limit').getValue(),
                shopId = record.get("id");

        me.mainWindow.setLoading(true);

        //clear previous results
        resultsPanel.update("");
        //save export_limit
        if (record.get('export_limit') != export_limit) {
            //export
            Ext.Ajax.request({
                url: '{url controller=SwpCleverReachExport action=saveExportLimit}',
                params: {
                    shopId: shopId,
                    export_limit: export_limit
                },
                success: function(response, operation)
                {
                    response = Ext.decode(response.responseText);
                    if (response.success)
                    {
                        me.callAPI(url, shopId, resultsPanel, action);
                    }
                    else
                    {
                        Shopware.Notification.createGrowlMessage(me.snippets.messages.title_error, me.snippets.messages.text_error, me.snippets.messages.module);
                        me.setTabsDisabled(true);
                    }
                },
                failure: function(response)
                {
                    response = response.statusText;
                    Shopware.Notification.createGrowlMessage(me.snippets.messages.title_error, response, me.snippets.messages.module);
                    me.setTabsDisabled(true);
                    me.mainWindow.setLoading(false);
                }});
        }
        else {
            me.callAPI(url, shopId, resultsPanel, action);
        }
    },
    /**
     * save the config values and refresh the panels
     */
    setConfigValues: function(action, values) {
        var me = this,
                form = me.getDetailsForm().installForm.getForm(),
                status_field = form.findField('status'),
                products_search = form.findField('products_search'),
                groups = form.findField('groups'),
                first_export = form.findField('first_export'),
                record = me.getDetailsForm().installForm.record;
        switch (action) {
            case "checkAPI":
                record.set("status", values.success);
                record.set("date", values.date);
                break;
            case "products_search":
                record.set("products_search", values.success);
                break;
            case "reset":
                record.set("status", false);
                record.set("date", values.date);
                record.set("products_search", false);
                record.set("wsdl_url", "");
                record.set("api_key", "");
                record.set("first_export", false);
                record.set("groups", false);
                record.set("export_limit", 50);
                break;
            case "first_export":
                record.set("first_export", true);
                record.set("export_limit", values.export_limit);
                break;
            case "groups":
                record.set("groups", values.success);
                break;

        }
        record.dirty = false;
        record.modified = {};
        me.getDetailsForm().installForm.loadRecord(record);
        status_field.fireEvent('render', status_field);
        products_search.fireEvent('render', products_search);
        groups.fireEvent('render', groups);
        first_export.fireEvent('render', first_export);
        me.mainWindow.setLoading(false);
    }
});