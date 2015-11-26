//{namespace name=backend/swp_clever_reach/snippets}
Ext.define('Shopware.apps.SwpCleverReach.view.main.Window', {
    extend: 'Enlight.app.Window',
    id:'SwpCleverReachMainWindow',
    alias: 'widget.swp_clever_reach-main-window',
    iconCls: 'cleverreachicon',
    layout: 'border',
    width: 860,
    height: 600,
    autoShow: true,

    snippets: {
        title: '{s name="clever_reach_title"}CleverReach{/s}'
    },

    initComponent: function() {
        var me = this;

        me.title = me.snippets.title;
        me.items = me.getItems();

        me.callParent(arguments);
    },
    /**
     * @return array
     */
    getItems: function() {
        var me = this;

        me.tabs = new Ext.TabPanel({
                            region: 'center',
                            activeTab: 0,
                            bodyBorder: false,
                            border: false,
                            plain:true,
                            hideBorders:false,
                            defaults:{ 
                                autoScroll: true
                            },
                            items:[
                            ]
                        });
        me.tabs.getTabBar().setVisible(false);
        return [{
                    xtype: 'swp_clever_reach-shop-list',
                    store: me.shopStore
                }, me.tabs];
    }
});