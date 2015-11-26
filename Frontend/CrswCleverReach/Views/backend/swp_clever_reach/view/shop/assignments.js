//{namespace name=backend/swp_clever_reach/snippets}
Ext.define('Shopware.apps.SwpCleverReach.view.shop.Assignments', {
    extend: 'Ext.grid.Panel',
    alias: 'widget.swp_clever_reach-shop-assignments',
    flex:3,
    collapsible: true,
    border: false,
    stripeRows:true,
    autoScroll:true,
    disabled: true,

    snippets: {
        title: '{s name="assignments_title"}Shop/Kundengruppen-Listen-Zuordnung{/s}',
        columns: {
            shop_name : '{s name=assignments/columns/shop_name}Shop{/s}',
            description : '{s name=assignments/columns/description}Kundengruppen{/s}',
            listID : '{s name=assignments/columns/listID}Listen{/s}',
            formID : '{s name=assignments/columns/formID}Formen{/s}'
        },
        tooltips: {
            shopclients: '{s name=assignments/tooltips/shop_clients}Shopkunden haben die Newsletter-Checkbox aktiv ausgewählt.{/s}',
            orderclients: '{s name=assignments/tooltips/order_clients}Bestellkunden sind alle Kunden im Shop, auch jene ohne Aktivierung der Newsletter-Checkbox.{/s}'
        }
    },

    initComponent: function()
    {
        var me = this;
        me.title = me.snippets.title;
        me.columns = me.getColumns();
        me.editor = me.getRowEditorPlugin();
        me.plugins = [ me.editor ];
        me.callParent(arguments);
    },

    getColumns: function() {
        var me = this;
        return [{
            text: me.snippets.columns.description,
            dataIndex: 'description',
            flex: 1,
            renderer: me.renderCustomerGroupsColumn
        },
        {
            text: me.snippets.columns.listID,
            dataIndex: 'listID',
            flex: 1,
            renderer: me.renderGroupsColumn,
            editor: {
                xtype: 'combobox',
                name: 'groupSelector',
                queryMode: 'local',
                allowBlank: false,
                valueField: 'id',
                displayField: 'name',
                store : me.groupsStore,
                editable: false,
                parent: me,
                listeners: {
                    'select': me.onSelectGroup
                }
            }
        },
        {
            text: me.snippets.columns.formID,
            dataIndex: 'formID',
            flex: 1,
            renderer: me.renderFormsColumn,
            editor: {
                xtype: 'combobox',
                queryMode: 'local',
                allowBlank: false,
                valueField: 'id',
                displayField: 'name',
                store: me.formsStore,
                editable: false
            }
        }
        ];
    },

    getRowEditorPlugin: function() {
        return Ext.create('Ext.grid.plugin.RowEditing', {
            clicksToEdit: 2,
            errorSummary: false,
            pluginId: 'rowEditing'
        });
    },

    renderGroupsColumn: function(value, metaData, record) {
        var me = this,
            group_record;
        if (value === Ext.undefined || value === null) {
            return value;
        }
        group_record =  me.groupsStore.getById(value);

        if (group_record instanceof Ext.data.Model) {
            if(value == -1){
                //auswählen
                return '<span style="color:red;">' + group_record.get('name') + '</span>';
            }else{
                return group_record.get('name');
            }
        } else {
            return value;
        }
    },

    renderFormsColumn: function(value, metaData, record) {
        var me = this,
        form_record;
        if (value === Ext.undefined || value === null) {
            return value;
        }
        form_record =  me.formsStore.getById(value);

        if (form_record instanceof Ext.data.Model) {
            if(value == -1){
                //kein Opt-In
                return '<span style="color:red;">' + form_record.get('name') + '</span>';
            }else{
                return form_record.get('name');
            }
        } else {
            return value;
        }
    },

    onSelectGroup: function(combo, record, index){
        var me = this,
        parent = me.parent,
        combo = parent.getPlugin('rowEditing').editor.form.findField('formID');
        combo.clearValue();
        combo.bindStore(parent.groupsStore.getById(record[0].get("id")).getForms());
        //kein Opt-In
        combo.setValue(-1);
    },

    renderCustomerGroupsColumn: function(value, metaData, record) {
        var me = this;
        if(record.get("customergroup") == 0){
            metaData.tdAttr = 'data-qtip="' + me.snippets.tooltips.orderclients + '"';
            value = '<div class="cleverreach_questionmark_icon">' + value + '</div>';
        } else if(record.get("groupkey") == 'EK'){
            metaData.tdAttr = 'data-qtip="' + me.snippets.tooltips.shopclients + '"';
            value = '<div class="cleverreach_questionmark_icon">' + value + '</div>';
        }
        return value;
    }
});