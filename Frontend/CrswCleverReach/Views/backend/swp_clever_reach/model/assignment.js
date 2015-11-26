Ext.define('Shopware.apps.SwpCleverReach.model.Assignment', {
    extend: 'Ext.data.Model',
    fields: [
            { name: 'customergroup', type: 'int' },
            { name: 'description',  type: 'string' },
            { name: 'listID',  type: 'int' , useNull: true },
            { name: 'formID',  type: 'int' , useNull: true },
            { name: 'groupkey',  type: 'string' },
            { name: 'shopId', type: 'int' }
    ],
    proxy: {
        type: 'ajax',
        api: {
            read:    '{url action="getAssignments"}',
            update:    '{url action="saveAssignment"}'
        },
        reader: {
            type: 'json',
            root: 'data'
        }
    }
});