define(['app/app', 'app/models',
        'tpl!app/templates/LastEventsItem.tpl',
        'tpl!app/templates/LastEventsList.tpl',
        'tpl!app/templates/TopSensorsItem.tpl',
        'tpl!app/templates/TopSensorsList.tpl',
        'tpl!app/templates/Dashboard.tpl',
        'chart', 'app/views/common'],
function(HoneySens, Models, LastEventsItemTpl, LastEventsListTpl, TopSensorsItemTpl, TopSensorsListTpl, DashboardTpl, Chart) {
    HoneySens.module('Views', function(Views, HoneySens, Backbone, Marionette, $, _) {
        Views.DashboardLastEventsItemView = Marionette.ItemView.extend({
            template: LastEventsItemTpl,
            tagName: 'tr',
            templateHelpers: Views.EventTemplateHelpers
        });

        Views.DashboardLastEventsListView = Marionette.CompositeView.extend({
            template: LastEventsListTpl,
            childViewContainer: 'tbody',
            childView: Views.DashboardLastEventsItemView,
            attachHtml: function(collectionView, childView) {
                collectionView.$el.find(this.childViewContainer).prepend(childView.el);
            }
        });

        Views.DashboardTopSensorsItemView = Marionette.ItemView.extend({
            template: TopSensorsItemTpl,
            tagName: 'tr',
            serializeData: function() {
                var data = Marionette.ItemView.prototype.serializeData.apply(this, arguments);
                data.events = HoneySens.data.models.events.where({ sensor: data.id }).length;
                return data;
            }
        });

        Views.DashboardTopSensorsListView = Marionette.CompositeView.extend({
            template: TopSensorsListTpl,
            childViewContainer: 'tbody',
            childView: Views.DashboardTopSensorsItemView
        });

       Views.DashboardView = Marionette.LayoutView.extend({
            id: 'dashboard',
            template: DashboardTpl,
            regions: {
                lastEvents: '#lastEvents',
                topSensors: '#topSensors'
            },
            onRender: function() {
                var sensors = _.last(HoneySens.data.models.sensors.sortBy(function(model) {
                    return HoneySens.data.models.events.where({ sensor: model.id }).length;
                }), 5).reverse();
                this.lastEvents.show(new Views.DashboardLastEventsListView({ collection: new Models.Events(HoneySens.data.models.events.last(5)) }));
                this.topSensors.show(new Views.DashboardTopSensorsListView({ collection: new Models.Sensors(sensors) }));
            },
            onShow: function() {
                var months = HoneySens.data.models.events.groupBy(function(event) {
                        var ts = new Date(event.get('timestamp') * 1000);
                        return ts.getFullYear() + "-" + ('0' + ts.getMonth()).slice(-2);
                    }),
                    now = new Date(),
                    curYear = now.getFullYear(),
                    curMonth = now.getMonth(),
                    dataset = [],
                    monthDict = {
                        '0': 'Januar', '1': 'Februar', '2': 'März', '3': 'April', '4': 'Mai', '5': 'Juni', '6': 'Juli', '7': 'August', '8': 'September',
                        '9': 'Oktober', '10': 'November', '11': 'Dezember'
                    };
                for(var i = 0;i<12;i++) {
                    var curDate = curYear + '-' + ('0' + curMonth).slice(-2),
                        count = 0;
                    if(curDate in months) count = months[curDate].length;
                    dataset.push({ 'date': curDate, 'count': count, 'name': monthDict[curMonth] });
                    if(curMonth == 0) {
                        curYear -= 1;
                        curMonth = 11;
                    } else curMonth -= 1;
                }
                dataset = _.sortBy(dataset, function(datapoint) { return datapoint.date; });
                var data = {
                    labels: _.pluck(dataset, 'name'),
                    datasets: [
                        {
                            label: "Ereignisse",
                            fillColor: "rgba(151,187,205,0.5)",
                            strokeColor: "rgba(151,187,205,0.8)",
                            highlightFill: "rgba(151,187,205,0.75)",
                            highlightStroke: "rgba(151,187,205,1)",
                            data: _.pluck(dataset, 'count')
                        }
                    ]
                };
                new Chart(this.$el.find('canvas')[0].getContext('2d')).Bar(data, { responsive: true });
            }
        });
    });
});
