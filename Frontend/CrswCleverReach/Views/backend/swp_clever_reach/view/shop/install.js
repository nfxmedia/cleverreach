//{namespace name=backend/swp_clever_reach/snippets}
Ext.define('Shopware.apps.SwpCleverReach.view.shop.Install', {
    extend: 'Ext.form.Panel',
    alias: 'widget.swp_clever_reach-shop-install',
    autoScroll: true,
    cls: 'shopware-form',
    layout: {
        type: 'vbox',
        align: 'stretch',
        pack: 'start'
    },
    border: false,
    defaults: {
        anchor: '100%',
        margin: '0 10 0 10'
    },
    snippets: {
        title: '{s name="install_title"}Einstellungen{/s}',
        buttons: {
            save_and_status: '{s name="buttons/save_and_status"}Speichern und Status prüfen{/s}',
            reset: '{s name="buttons/reset"}Reset{/s}',
            activate_products_search: '{s name="buttons/activate_products_search"}Produkt-Suche aktivieren{/s}'
        },
        api: {
            title: '{s name="api/title"}API{/s}',
            api_key: '{s name="api/api_key"}API-Key{/s}',
            wsdl_url: '{s name="api/wsdl_url"}WSDL-URL{/s}',
            status: '{s name="api/status"}Status{/s}',
            tested_on: '{s name="api/tested_on"}tested on{/s}',
            products_search: '{s name="api/products_search"}Produkt-Suche{/s}',
            groups: '{s name="api/groups"}Produkt-Suche{/s}',
            first_export: '{s name="api/first_export"}Produkt-Suche{/s}'
        }
    },
    initComponent: function()
    {
        var me = this;
        me.title = me.snippets.title;

        me.items = me.getItems();
        me.addEvents('saveAndCheck', 'onReset', 'onProductsSearch');

        me.callParent(arguments);
        //me.loadRecord(me.record);
    },
    getItems: function()
    {
        var me = this;
        me.productsSearchButton = Ext.create('Ext.Button', {
            xtype: 'button',
            anchor: '30%',
            margin: '0 10 0 0',
            text: me.snippets.buttons.activate_products_search,
            disabled: true,
            handler: function()
            {
                me.fireEvent('onProductsSearch', me);
            }
        });
        me.apiFiledset = Ext.create('Ext.form.FieldSet', {
            title: me.snippets.api.title,
            flex: 3,
            layout: 'anchor',
            collapsible: true,
            defaults: {
                anchor: '100%',
                labelWidth: '20%'
            },
            items: [{
                    xtype: 'textfield',
                    fieldLabel: me.snippets.api.api_key,
                    name: 'api_key'
                },
                {
                    xtype: 'textfield',
                    fieldLabel: me.snippets.api.wsdl_url,
                    name: 'wsdl_url'
                },
                {
                    xtype: 'displayfield',
                    fieldLabel: me.snippets.api.status,
                    name: 'status',
                    bodyPadding: '3 0 0 0',
                    date: me.record.get("date"),
                    tested_on: me.snippets.api.tested_on,
                    parent: me,
                    //renderer: me.booleanColumnRenderer
                    listeners: {
                        render: me.booleanColumnRenderer
                    }
                }, {
                    xtype: 'fieldcontainer',
                    layout: {
                        type: 'hbox',
                        pack: 'start',
                        align: 'stretch'
                    },
                    items: [
                        {
                            xtype: 'displayfield',
                            fieldLabel: me.snippets.api.products_search,
                            name: 'products_search',
                            bodyPadding: '3 0 0 0',
                            flex: 2,
                            labelWidth: '70%',
                            parent: me,
                            //renderer: me.booleanColumnRenderer
                            listeners: {
                                render: me.booleanColumnRenderer
                            }
                        }, {
                            xtype: 'displayfield',
                            fieldLabel: me.snippets.api.groups,
                            name: 'groups',
                            bodyPadding: '3 0 0 0',
                            flex: 3,
                            labelWidth: '90%',
                            parent: me,
                            //renderer: me.booleanColumnRenderer
                            listeners: {
                                render: me.booleanColumnRenderer
                            }
                        }, {
                            xtype: 'displayfield',
                            fieldLabel: me.snippets.api.first_export,
                            name: 'first_export',
                            bodyPadding: '3 0 0 0',
                            flex: 2,
                            labelWidth: '45%',
                            parent: me,
                            //renderer: me.booleanColumnRenderer
                            listeners: {
                                render: me.booleanColumnRenderer
                            }
                        }
                    ]
                }, {
                    xtype: 'button',
                    anchor: '30%',
                    margin: '0 10 0 0',
                    text: me.snippets.buttons.save_and_status,
                    handler: function()
                    {
                        me.fireEvent('saveAndCheck', me);
                    }
                },
                me.productsSearchButton
                        , {
                            xtype: 'button',
                            anchor: '30%',
                            margin: '0 10 0 0',
                            text: me.snippets.buttons.reset,
                            handler: function()
                            {
                                me.fireEvent('onReset', me);
                            }
                        }]
        });
        me.assignmentsForm = Ext.widget('swp_clever_reach-shop-assignments', {
            store: me.assignmentsStore,
            groupsStore: me.groupsStore,//record.getGroups(),
            formsStore: me.formsStore
        });
        return [me.apiFiledset, me.assignmentsForm];
    },
    booleanColumnRenderer: function(obj) {
        var me = this,
                value = obj.value,
                record = me.parent.record;
        value = record.get(obj.name);
        if (value == true) {
            value = '<div class="sprite-tick"  style="width: 25px;display: inline-block;">&nbsp;</div>';
        } else {
            value = '<div class="sprite-cross"  style="width: 25px;display: inline-block;">&nbsp;</div>';
        }
        if (obj.name == "status") {
            if (obj.date != null && obj.date != undefined) {
                value += obj.tested_on + ' ' + Ext.Date.format(record.get("date"), 'd.m.Y H:i:s');
            }
        }
        obj.setValue(value);
    }
});