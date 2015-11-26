//{namespace name=backend/swp_clever_reach/snippets}
Ext.define('Shopware.apps.SwpCleverReach.view.shop.Details', {
    extend: 'Ext.form.Panel',
    alias: 'widget.swp_clever_reach-shop-details',
    region: 'center',
    layout: 'fit',
    border: false,
    defaults: {
        anchor: '100%'
    },
    initComponent: function() {
        var me = this;

        me.title = me.record.get('name');
        me.items = me.getItems();

        me.callParent(arguments);
    },
    getItems: function()
    {
        var me = this;
        /*me.nameFiled = Ext.create('Ext.form.FieldSet', {
         //height: 45,
         layout: 'anchor',
         defaults: {
         anchor: '100%',
         labelWidth: '10%'
         },
         items: [{
         xtype: 'displayfield',
         //fieldLabel: me.snippets.api.status,
         name: 'name'
         }]
         });*/
        me.installForm = Ext.widget('swp_clever_reach-shop-install', {
            record: me.record,
            groupsStore: me.groupsStore,
            formsStore: me.formsStore,
            assignmentsStore: me.assignmentsStore
        });
        me.first_exportForm = Ext.widget('swp_clever_reach-shop-first-export', {
            record: me.record
        });
        me.installationTabs = Ext.create('Ext.tab.Panel', {
            //height:555,
            items: [
                me.installForm,
                me.first_exportForm
            ]
        });
        return [me.nameFiled, me.installationTabs];
    }
});