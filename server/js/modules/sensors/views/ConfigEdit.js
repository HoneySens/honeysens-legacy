define(['app/app',
        'app/models',
        'tpl!app/modules/sensors/templates/ConfigEdit.tpl',
        'validate'],
function(HoneySens, Models, ConfigEditTpl) {
    HoneySens.module('Sensors.Views', function(Views, HoneySens, Backbone, Marionette, $, _) {
        Views.ConfigEdit = Marionette.ItemView.extend({
            template: ConfigEditTpl,
            events: {
                'click #configGroupIntervalGlobal button': function(e) {
                    e.preventDefault();
                    $(e.target).addClass('disabled');
                    var validator = this.$el.find('form').data('bootstrapValidator');
                    validator.validateField('sensorConfigInterval');
                    if(validator.isValidField('sensorConfigInterval')) {
                        var interval = this.$el.find('input[name="sensorConfigInterval"]').val();
                        this.model.save({interval: interval}, {
                            success: function() {
                                $(e.target).removeClass('disabled');
                            },
                            error: function() {
                                $(e.target).removeClass('disabled');
                            }
                        });
                    }
                },
                'click button.cancel': function(e) {
                    e.preventDefault();
                    HoneySens.request('view:content').overlay.empty();
                },
                'click button.save': function(e) {
                    this.$el.find('form').bootstrapValidator('validate');
                },
                'click div.panelRecon button.toggle': function(e) {
                    e.preventDefault();
                    if($(e.target).hasClass('active')) {
                        this.disableReconService();
                        if(this.model.id == 1) {
                            this.model.save({recon: false});
                        }
                    } else {
                        this.enableReconService();
                        if(this.model.id == 1) {
                            this.model.save({recon: true});
                        }
                    }
                },
                'click div.panelCowrie button.toggle': function(e) {
                    e.preventDefault();
                    if($(e.target).hasClass('active')) {
                        this.disableCowrieService();
                        if(this.model.id == 1) {
                            this.model.save({kippoHoneypot: false});
                        }
                    } else {
                        this.enableCowrieService();
                        if(this.model.id == 1) {
                            this.model.save({kippoHoneypot: true});
                        }
                    }
                },
                'click div.panelDionaea button.toggle': function(e) {
                    e.preventDefault();
                    if($(e.target).hasClass('active')) {
                        this.disableDionaeaService();
                        if(this.model.id == 1) {
                            this.model.save({dionaeaHoneypot: false});
                        }
                    } else {
                        this.enableDionaeaService();
                        if(this.model.id == 1) {
                            this.model.save({dionaeaHoneypot: true});
                        }
                    }
                }
            },
            onRender: function() {
                var view = this;
                this.$el.find('form').bootstrapValidator({
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
                }).on('success.form.bv', function(e) {
                    e.preventDefault();
                    var sensor = view.$el.find('select.sensorConfigSensor').val(),
                        interval = view.$el.find('input[name="sensorConfigInterval"]').val(),
                        recon = view.$el.find('div.panelRecon button.toggle').hasClass('active'),
                        kippoHoneypot = view.$el.find('div.panelCowrie button.toggle').hasClass('active'),
                        dionaeaHoneypot = view.$el.find('div.panelDionaea button.toggle').hasClass('active'),
                        image = view.$el.find('select.sensorConfigImage').val();
                    if(!view.model.id) HoneySens.data.models.configs.add(view.model);
                    view.model.save({
                        sensor: sensor,
                        interval: interval,
                        recon: recon,
                        kippoHoneypot: kippoHoneypot,
                        dionaeaHoneypot: dionaeaHoneypot,
                        image: image
                    }, {
                        success: function() {
                            HoneySens.execute('fetchUpdates');
                            HoneySens.request('view:content').overlay.empty();
                        }
                    });
                });
                // Parse model data
                if(this.model.get('recon')) {
                    this.enableReconService();
                    this.$el.find('div.panelRecon button.toggle').addClass('active');
                } else this.disableReconService();
                if(this.model.get('kippoHoneypot')) {
                    this.enableCowrieService();
                    this.$el.find('div.panelCowrie button.toggle').addClass('active');
                } else this.disableCowrieService();
                if(this.model.get('dionaeaHoneypot')) {
                    this.enableDionaeaService();
                    this.$el.find('div.panelDionaea button.toggle').addClass('active');
                } else this.disableDionaeaService();
                // Add the container-fluid class to the overlay only
                if(this.model.id != 1) this.$el.addClass('container-fluid');
                // Disable global setting controls for non-admin users
                if(this.model.id == 1 && HoneySens.data.session.user.get('role') != Models.User.role.ADMIN) {
                    this.$el.find('input[name="sensorConfigInterval"]').prop('disabled', true);
                    this.$el.find('button.toggle').prop('disabled', true);
                }
            },
            templateHelpers: {
                isModal: function() {
                    return this.id != 1;
                },
                isEdit: function() {
                    return this.id != undefined;
                },
                showSensor: function() {
                    return HoneySens.data.models.sensors.findWhere({config: this.id}).get('name');
                },
                isAdmin: function() {
                    return HoneySens.data.session.user.get('role') == Models.User.role.ADMIN;
                }
            },
            serializeData: function() {
                var data = Marionette.ItemView.prototype.serializeData.apply(this, arguments);
                var unconfiguredSensors = new Models.Sensors(HoneySens.data.models.sensors.filter(function(m) {
                    return m.getConfig() === HoneySens.data.models.defaultconfig;
                }));
                data.unconfiguredSensors = unconfiguredSensors.toJSON();
                data.images = HoneySens.data.models.images.toJSON();
                return data;
            },
            enableReconService: function() {
                this.$el.find('div.panelRecon button.toggle')
                    .removeClass('btn-primary')
                    .addClass('btn-default')
                    .button('active');
                this.$el.find('div.panelRecon')
                    .removeClass('panel-default')
                    .addClass('panel-success');
            },
            disableReconService: function() {
                this.$el.find('div.panelRecon button.toggle')
                    .removeClass('btn-default')
                    .addClass('btn-primary')
                    .button('inactive');
                this.$el.find('div.panelRecon')
                    .removeClass('panel-success')
                    .addClass('panel-default');
            },
            enableCowrieService: function() {
                this.$el.find('div.panelCowrie button.toggle')
                    .removeClass('btn-primary')
                    .addClass('btn-default')
                    .button('active');
                this.$el.find('div.panelCowrie')
                    .removeClass('panel-default')
                    .addClass('panel-success');

            },
            disableCowrieService: function() {
                this.$el.find('div.panelCowrie button.toggle')
                    .removeClass('btn-default')
                    .addClass('btn-primary')
                    .button('inactive');
                this.$el.find('div.panelCowrie')
                    .removeClass('panel-success')
                    .addClass('panel-default');
            },
            enableDionaeaService: function() {
                this.$el.find('div.panelDionaea button.toggle')
                    .removeClass('btn-primary')
                    .addClass('btn-default')
                    .button('active');
                this.$el.find('div.panelDionaea')
                    .removeClass('panel-default')
                    .addClass('panel-success');

            },
            disableDionaeaService: function() {
                this.$el.find('div.panelDionaea button.toggle')
                    .removeClass('btn-default')
                    .addClass('btn-primary')
                    .button('inactive');
                this.$el.find('div.panelDionaea')
                    .removeClass('panel-success')
                    .addClass('panel-default');
            }
        });
    });

    return HoneySens.Sensors.Views.ConfigEdit;
});