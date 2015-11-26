//{namespace name=backend/swp_clever_reach/snippets}
Ext.define('Shopware.apps.SwpCleverReach.view.shop.List', {
    extend: 'Ext.grid.Panel',
    alias: 'widget.swp_clever_reach-shop-list',

    region: 'west',
    border: false,
    width: 200,
    stripeRows:true,
    collapsible: false,
    markDirty:false,

    snippets: {
        columns: {
            name : '{s name=shop/list/columns/name}Shop{/s}',
            status : '{s name=shop/list/columns/status}Status{/s}'
        }
    },

    initComponent: function() {
        var me = this;

        me.columns = me.getColumns();
        me.callParent(arguments);
    },

    getColumns: function() {
        var me = this;
        return [{
                dataIndex: 'name',
                text: me.snippets.columns.name,
                flex: 1
            },{
                xtype: 'booleancolumn',
                dataIndex: 'status',
                text: me.snippets.columns.status,
                width: 45,
                renderer: me.booleanColumnRenderer
            }
        ];
    },
    booleanColumnRenderer: function(value) {
        if (value) {
            return '<div class="sprite-tick-small"  style="width: 25px;">&nbsp;</div>';
        } else {
            return '<div class="sprite-cross-small"  style="width: 25px;">&nbsp;</div>';
        }
    }
});