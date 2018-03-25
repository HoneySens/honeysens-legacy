define(['app/app', 'app/routing', 'app/models',
        'app/modules/sensors/views/Layout',
        'app/modules/sensors/views/SensorList',
        'app/modules/sensors/views/SensorEdit',
        'app/modules/sensors/views/ModalSensorRemove',
        'app/modules/sensors/views/Configs',
        'app/modules/sensors/views/ConfigEdit',
        'app/modules/sensors/views/ModalConfigRemove'],
function(HoneySens, Routing, Models, LayoutView, SensorListView, SensorEditView, ModalSensorRemoveView, ConfigsView, ConfigEditView, ModalConfigRemoveView) {
    var SensorsModule = Routing.extend({
        name: 'sensors',
        startWithParent: false,
        rootView: null,
        menuItems: [
            {title: 'Sensoren', uri: 'sensors', iconClass: 'glyphicon glyphicon-hdd', permission: {domain: 'sensors', action: 'get'}, priority: 2},
            {title: 'Konfiguration', uri: 'sensors/configs', iconClass: 'glyphicon glyphicon-wrench', permission: {domain: 'sensorconfigs', action: 'create'}},
            {title: 'Firmware', uri: 'images', iconClass: 'glyphicon glyphicon-save', permission: {domain: 'sensorimages', action: 'get'}, priority: 3}
        ],
        start: function() {
            console.log('Starting module: sensors');
            this.rootView = new LayoutView();
            HoneySens.request('view:content').main.show(this.rootView);

            // Register command handlers
            var contentRegion = this.rootView.getRegion('content'),
                router = this.router;

            HoneySens.reqres.setHandler('sensors:show', function() {
                if(!HoneySens.assureAllowed('sensors', 'get')) return false;
                contentRegion.show(new SensorListView({collection: HoneySens.data.models.sensors}));
                router.navigate('sensors');
                HoneySens.vent.trigger('sensors:shown');
            });
            HoneySens.reqres.setHandler('sensors:add', function() {
                HoneySens.request('view:content').overlay.show(new SensorEditView({model: new Models.Sensor()}));
            });
            HoneySens.reqres.setHandler('sensors:edit', function(model) {
                HoneySens.request('view:content').overlay.show(new SensorEditView({model: model}));
            });
            HoneySens.reqres.setHandler('sensors:remove', function(model) {
                HoneySens.request('view:modal').show(new ModalSensorRemoveView({model: model}));
            });
            HoneySens.reqres.setHandler('sensors:configs:show', function() {
                if(!HoneySens.assureAllowed('sensorconfigs', 'get')) return false;
                contentRegion.show(new ConfigsView({model: HoneySens.data.models.defaultconfig, collection: HoneySens.data.models.configs}));
                HoneySens.vent.trigger('sensors:configs:shown');
                router.navigate('sensors/configs');
            });
            HoneySens.reqres.setHandler('sensors:configs:add', function() {
                if(!HoneySens.assureAllowed('sensorconfigs', 'create')) return false;
                HoneySens.request('view:content').overlay.show(new ConfigEditView({model: new Models.SensorConfig()}));
            });
            HoneySens.reqres.setHandler('sensors:configs:edit', function(config) {
                if(!HoneySens.assureAllowed('sensorconfigs', 'update')) return false;
                HoneySens.request('view:content').overlay.show(new ConfigEditView({model: config}));
            });
            HoneySens.reqres.setHandler('sensors:configs:remove', function(config) {
                HoneySens.request('view:modal').show(new ModalConfigRemoveView({model: config}));
            });

        },
        stop: function() {
            console.log('Stopping module: sensors');
            HoneySens.reqres.removeHandler('sensors:show');
            HoneySens.reqres.removeHandler('sensors:configs:show');
        },
        routesList: {
            'sensors': 'showSensors',
            'sensors/configs': 'showConfigs'
        },
        showSensors: function() {HoneySens.request('sensors:show');},
        showConfigs: function() {HoneySens.request('sensors:configs:show');}
    });

    return HoneySens.module('Sensors.Routing', SensorsModule);
});