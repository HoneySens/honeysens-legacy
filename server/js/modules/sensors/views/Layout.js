define(['app/app',
        'app/views/regions',
        'tpl!app/modules/sensors/templates/Layout.tpl',
        'app/views/common'],
function(HoneySens, Regions, LayoutTpl) {
    HoneySens.module('Sensors.Views', function(Views, HoneySens, Backbone, Marionette, $, _) {
        Views.Layout = Marionette.LayoutView.extend({
            template: LayoutTpl,
            regions: {
                content: 'div.content'
            },
            initialize: function() {
                this.listenTo(HoneySens.vent, 'sensors:shown', function() {
                    this.$el.find('h1').html('<span class="glyphicon glyphicon-hdd"></span>&nbsp;Sensoren');
                });
                this.listenTo(HoneySens.vent, 'sensors:configs:shown', function() {
                    this.$el.find('h1').html('<span class="glyphicon glyphicon-wrench"></span>&nbsp;Sensoren &rsaquo; Konfiguration');
                });
            }
        });
    });

    return HoneySens.Sensors.Views.Layout;
});