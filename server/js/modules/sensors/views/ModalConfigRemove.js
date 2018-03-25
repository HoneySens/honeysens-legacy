define(['app/app',
        'tpl!app/modules/sensors/templates/ModalConfigRemove.tpl'],
function(HoneySens, ModalConfigRemoveTpl) {
    HoneySens.module('Sensors.Views', function(Views, HoneySens, Backbone, Marionette, $, _) {
        Views.ModalConfigRemove = Marionette.ItemView.extend({
            template: ModalConfigRemoveTpl,
            events: {
                'click button.btn-primary': function(e) {
                    e.preventDefault();
                    this.model.destroy({wait: true, success: function() {
                        HoneySens.request('view:modal').empty();
                    }});
                }
            },
            templateHelpers: {
                showSensor: function() {
                    return HoneySens.data.models.sensors.findWhere({config: this.id}).get('name');
                }
            }
        });
    });

    return HoneySens.Sensors.Views.ModalConfigRemove;
});