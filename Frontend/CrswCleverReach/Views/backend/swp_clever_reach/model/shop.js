Ext.define('Shopware.apps.SwpCleverReach.model.Shop', {
    extend: 'Ext.data.Model',
    fields: [
            { name: 'id', type: 'int' },
            { name: 'name',  type: 'string' },
            { name: 'api_key',  type: 'string' },
            { name: 'wsdl_url',  type: 'string' },
            { name: 'status',  type: 'boolean' },
            { name: 'date',  type: 'date' },
            { name: 'export_limit',  type: 'int' },
            { name: 'first_export',  type: 'boolean' },
            { name: 'products_search',  type: 'boolean' },
            { name: 'groups',  type: 'boolean' }
    ],
    proxy: {
        type: 'ajax',
        api: {
            read:    '{url action="getShops"}',
            update:    '{url action="saveShop"}'
        },
        reader: {
            type: 'json',
            root: 'data'
        }
    }
});