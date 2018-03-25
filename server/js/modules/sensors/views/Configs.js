define(['app/app',
        'app/models',
        'app/modules/sensors/views/ConfigEdit',
        'tpl!app/modules/sensors/templates/Configs.tpl',
        'tpl!app/modules/sensors/templates/ConfigListActionsCell.tpl'],
function(HoneySens, Models, ConfigEditView, ConfigsTpl, ConfigListActionsCellTpl) {
    HoneySens.module('Sensors.Views', function(Views, HoneySens, Backbone, Marionette, $, _) {
        function refreshAddState() {
            var unconfiguredSensors = new Models.Sensors(HoneySens.data.models.sensors.filter(function(m) {
                return m.getConfig() === HoneySens.data.models.defaultconfig;
            }));
            if(unconfiguredSensors.length > 0) {
                $('#sensorConfigList button.add').prop('disabled', false);
            } else {
                $('#sensorConfigList button.add').prop('disabled', true);
            }
        }

        Views.Configs = Marionette.LayoutView.extend({
            template: ConfigsTpl,
            regions: {
                list: 'div.configList',
                defaultconfig: 'div.defaultConfig'
            },
            events: {
                'click button.add': function(e) {
                    e.preventDefault();
                    HoneySens.request('sensors:configs:add');
                }
            },
            onRender: function() {
                this.defaultconfig.show(new ConfigEditView({model: this.model}));
                // Backgrid
                var columns = [{
                    label: 'Sensor',
                    editable: false,
                    sortable: false,
                    cell: Backgrid.Cell.extend({
                        render: function() {
                            var sensor = HoneySens.data.models.sensors.findWhere({config: this.model.id});
                            if(sensor) this.$el.html(sensor.get('name'));
                            return this;
                        }
                    })
                }, {
                    label: 'Gruppe',
                    editable: false,
                    sortable: false,
                    cell: Backgrid.Cell.extend({
                        render: function() {
                            var sensor = HoneySens.data.models.sensors.findWhere({config: this.model.id});
                            if(sensor) this.$el.html(HoneySens.data.models.divisions.get(sensor.get('division')).get('name'));
                            return this;
                        }
                    })
                }, {
                    name: 'interval',
                    label: 'Updateintervall',
                    editable: false,
                    sortable: false,
                    cell: Backgrid.IntegerCell.extend({
                        orderSeparator: ''
                    })
                }, {
                    label: 'recon',
                    editable: false,
                    sortable: false,
                    cell: Backgrid.Cell.extend({
                        render: function() {
                            if(this.model.get('recon')) {
                                this.$el.html('<span class="glyphicon glyphicon-ok"></span>');
                            } else {
                                this.$el.html('<span class="glyphicon glyphicon-remove"></span>');
                            }
                            return this;
                        }
                    })
                }, {
                    label: 'cowrie',
                    editable: false,
                    sortable: false,
                    cell: Backgrid.Cell.extend({
                        render: function() {
                            if(this.model.get('kippoHoneypot')) {
                                this.$el.html('<span class="glyphicon glyphicon-ok"></span>');
                            } else {
                                this.$el.html('<span class="glyphicon glyphicon-remove"></span>');
                            }
                            return this;
                        }
                    })
                }, {
                    label: 'dionaea',
                    editable: false,
                    sortable: false,
                    cell: Backgrid.Cell.extend({
                        render: function() {
                            if(this.model.get('dionaeaHoneypot')) {
                                this.$el.html('<span class="glyphicon glyphicon-ok"></span>');
                            } else {
                                this.$el.html('<span class="glyphicon glyphicon-remove"></span>');
                            }
                            return this;
                        }
                    })
                }, {
                    label: 'Aktionen',
                    editable: false,
                    sortable: false,
                    cell: Backgrid.Cell.extend({
                        template: ConfigListActionsCellTpl,
                        events: {
                            'click button.edit': function(e) {
                                e.preventDefault();
                                HoneySens.request('sensors:configs:edit', this.model);
                            },
                            'click button.remove': function(e) {
                                e.preventDefault();
                                HoneySens.request('sensors:configs:remove', this.model);
                            }
                        },
                        render: function() {
                            this.$el.html(this.template(this.model.attributes));
                            this.$el.find('button').tooltip();
                            return this;
                        }
                    })
                }];
                var grid = new Backgrid.Grid({
                    columns: columns,
                    collection: this.collection,
                    className: 'table table-striped'
                });
                this.list.show(grid);
                // Only allow adding new configs if there are unconfigured sensors left
                this.listenTo(this.collection, 'update', refreshAddState);
                this.listenTo(this.collection, 'add', refreshAddState);
                this.listenTo(this.collection, 'remove', refreshAddState);
                this.listenTo(this.collection, 'reset', refreshAddState);
            },
            onShow: function() {
                refreshAddState();
            }
        });
    });

    return HoneySens.Sensors.Views.Configs;
});