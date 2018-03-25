define(['app/app', 'app/models',
        'tpl!app/templates/ConfigEdit.tpl',
        'tpl!app/templates/ConfigItem.tpl',
        'tpl!app/templates/ConfigList.tpl',
        'tpl!app/templates/Configs.tpl',
        'tpl!app/templates/ModalConfigSave.tpl',
        'tpl!app/templates/ModalConfigRemove.tpl',
        'json', 'validate'],
function(HoneySens, Models, ConfigEditTpl, ConfigItemTpl, ConfigListTpl, ConfigsTpl, ModalConfigSaveTpl, ModalConfigRemoveTpl, JSON) {
    HoneySens.module('Views', function(Views, HoneySens, Backbone, Marionette, $, _) {
        Views.ConfigsItemView = Marionette.ItemView.extend({
            template: ConfigEditTpl,
            events: {
                'click button:submit': function(e) {
                    e.preventDefault();
                    this.$el.find('form').bootstrapValidator('validate');
                }
            },
            onRender: function() {
                var view = this;
                this.$el.find('form').bootstrapValidator({
                    feedbackIcons: {
                        valid: 'glyphicon glyphicon-ok',
                        invalid: 'glyphicon glyphicon-remove',
                        validating: 'glyphicon glyphicon-refresh'
                    },
                    fields: {
                        sensorConfigInterval: {
                            validators: {
                                notEmpty: {},
                                between: {
                                    min: 1,
                                    max: 200,
                                    message: 'Intervall muss zwischen 1 und 200 Minuten liegen'
                                }
                            }
                        }
                    }
                }).on('success.form.bv', function() {
                    var recon = view.$el.find('input.sensorConfigRecon').is(':checked'),
                        kippoHoneypot = view.$el.find('input.sensorConfigKippoHoneypot').is(':checked'),
                        dionaeaHoneypot = view.$el.find('input.sensorConfigDionaeaHoneypot').is(':checked'),
                        interval = view.$el.find('input.sensorConfigInterval').val(),
                        $image = view.$el.find('select.sensorConfigImage'),
                        model = view.model,
                        options = view.options,
                        modelData = {interval: interval, recon: recon, kippoHoneypot: kippoHoneypot, dionaeaHoneypot: dionaeaHoneypot};
                    view.$el.find('button:submit').prop('disabled', true);
                    if($image.length == 1) {
                        modelData.image = $image.val();
                    } else {
                        modelData.image = null;
                    }
                    if(model.id) {
                        $.ajax({
                            type: 'PUT',
                            url: 'api/sensorconfigs/' + model.id,
                            data: JSON.stringify(modelData),
                            success: function() {
                                if(model.id == 1) {
                                    model.fetch();
                                    HoneySens.request('view:modal').show(new Views.ModalSaveSensorConfig());
                                    view.$el.find('button:submit').prop('disabled', false);
                                    view.$el.find('form').data('bootstrapValidator').resetForm();
                                } else {
                                    HoneySens.data.models.configs.fetch({ reset: true, success: function() {
                                        if(options.modal) HoneySens.request('view:modal').empty();
                                    }});
                                }
                            }
                        });
                    } else {
                        modelData.sensor = view.$el.find('select.sensorConfigSensor').val();
                        $.post('api/sensorconfigs', JSON.stringify(modelData), function(data) {
                            data = JSON.parse(data);
                            model.id = data.id;
                            HoneySens.data.models.sensors.fetch({ reset: true, success: function() {
                                HoneySens.data.models.configs.fetch({ reset: true , success: function() {
                                    if(options.modal) HoneySens.request('view:modal').empty();
                                }});
                            }});
                        });
                    }
                });
            },
            templateHelpers: {
                isModal: function() {
                    return this.modal;
                },
                isEdit: function() {
                    return typeof this.id !== 'undefined';
                },
                showSensor: function() {
                    return HoneySens.data.models.sensors.findWhere({ config: this.id }).get('name');
                }
            },
            serializeData: function() {
                var data = Marionette.ItemView.prototype.serializeData.apply(this, arguments);
                data.modal = this.options.modal || false;
                var unconfiguredSensors = new Models.Sensors(HoneySens.data.models.sensors.filter(function(m) {
                    return m.getConfig() === HoneySens.data.models.defaultconfig;
                }));
                data.unconfiguredSensors = unconfiguredSensors.toJSON();
                data.images = HoneySens.data.models.images.toJSON();
                return data;
            }
        });

        Views.ConfigsListItem = Marionette.ItemView.extend({
            template: ConfigItemTpl,
            tagName: 'tr',
            events: {
                'click button.editSensorConfig': function(e) {
                    e.preventDefault();
                    var dialog = new Views.ConfigsItemView({ modal: true, model: this.model });
                    HoneySens.request('view:modal').show(dialog);
                },
                'click button.removeSensorConfig': function(e) {
                    e.preventDefault();
                    var dialog = new Views.ModalRemoveSensorConfig({ model: this.model });
                    HoneySens.request('view:modal').show(dialog);
                }
            },
            templateHelpers: {
                showSensor: function() {
                    return HoneySens.data.models.sensors.findWhere({ config: this.id }).get('name');
                }
            },
            onRender: function() {
                this.$el.find('button').tooltip();
            }
        });

        Views.ConfigsListView = Marionette.CompositeView.extend({
            template: ConfigListTpl,
            childViewContainer: 'tbody',
            childView: Views.ConfigsListItem,
            events: {
                'click #addConfig': function(e) {
                    e.preventDefault();
                    HoneySens.request('view:modal').show(new Views.ConfigsItemView({ modal: true, model: new Models.SensorConfig() }));
                }
            },
            initialize: function() {
                var view = this;
                this.listenTo(this.collection, 'reset', function() {
                    view.render();
                });
            },
            templateHelpers: {
                hasUnconfiguredSensors: function() {
                    var unconfiguredSensors = new Models.Sensors(HoneySens.data.models.sensors.filter(function(m) {
                        return m.getConfig() === HoneySens.data.models.defaultconfig;
                    }));
                    return unconfiguredSensors.length > 0;
                }
            }
        });

        Views.ConfigsView = Marionette.LayoutView.extend({
            template: ConfigsTpl,
            regions: {
                list: 'div.configList',
                defaultconfig: 'div.defaultConfig'
            },
            onRender: function() {
                this.list.show(new Views.ConfigsListView({ collection: this.collection }));
                this.defaultconfig.show(new Views.ConfigsItemView({ model: this.model }));
            }
        });

        Views.ModalSaveSensorConfig = Marionette.ItemView.extend({
            template: ModalConfigSaveTpl
        });

        Views.ModalRemoveSensorConfig = Marionette.ItemView.extend({
            template: ModalConfigRemoveTpl,
            events: {
                'click button.btn-primary': function(e) {
                    e.preventDefault();
                    this.model.destroy({wait: true, success: function() {
                        HoneySens.execute('fetchUpdates');
                        HoneySens.request('view:modal').empty();
                    }});
                }
            },
            templateHelpers: {
                showSensor: function() {
                    return HoneySens.data.models.sensors.findWhere({ config: this.id }).get('name');
                }
            }
        });
    });
});
