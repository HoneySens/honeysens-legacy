define(['app/app',
        'backgrid',
        'tpl!app/modules/services/templates/ServiceDetails.tpl',
        'tpl!app/modules/services/templates/RevisionListActionsCell.tpl',
        'tpl!app/modules/services/templates/RevisionListStatusCell.tpl',
        'app/views/common'],
function(HoneySens, Backgrid, ServiceDetailsTpl, RevisionListActionsCellTpl, RevisionListStatusCellTpl) {
    HoneySens.module('Services.Views', function(Views, HoneySens, Backbone, Marionette, $, _) {
        Views.ServiceDetails = Marionette.LayoutView.extend({
            template: ServiceDetailsTpl,
            className: 'container-fluid',
            regions: {
                revisions: 'div.revisions'
            },
            revisionStatus: null,
            events: {
                'click button.cancel': function() {
                    HoneySens.request('view:content').overlay.empty();
                }
            },
            onRender: function() {
                var view = this,
                    columns = [{
                    name: 'revision',
                    label: 'Revision',
                    editable: false,
                    cell: 'string'
                }, {
                    name: 'description',
                    label: 'Beschreibung',
                    editable: false,
                    sortable: false,
                    cell: 'string'
                }, {
                    name: 'status',
                    label: 'Status',
                    editable: false,
                    cell: Backgrid.Cell.extend({
                        template: RevisionListStatusCellTpl,
                        render: function() {
                            // Mix template helpers into template data
                            var templateData = this.model.attributes;
                            templateData.getStatus = function() {
                                if(view.revisionStatus == null) return null;
                                if(view.revisionStatus == false) return false;
                                return view.revisionStatus[this.id];
                            };
                            // Color-code cell depending on the model status
                            switch(templateData.getStatus()) {
                                case true:
                                    this.$el.addClass('success');
                                    break;
                                case false:
                                    this.$el.addClass('danger');
                                    break;
                                default:
                                    this.$el.addClass('info');
                                    break;
                            }
                            // Render template
                            this.$el.html(this.template(templateData));
                            return this;
                        }
                    })
                },{
                    label: 'Aktionen',
                    editable: false,
                    sortable: false,
                    cell: Backgrid.Cell.extend({
                        template: RevisionListActionsCellTpl,
                        events: {
                            'click button.setDefaultRevision': function(e) {
                                e.preventDefault();
                                view.model.save({default_revision: this.model.id}, {wait: true});
                            },
                            'click button.removeRevision': function(e) {
                                e.preventDefault();
                                HoneySens.request('services:revisions:remove', this.model);
                            }
                        },
                        render: function() {
                            this.$el.html(this.template(this.model.attributes));
                            this.$el.find('button').tooltip();
                            return this;
                        }
                    })
                }];
                var row = Backgrid.Row.extend({
                    render: function() {
                        Backgrid.Row.prototype.render.call(this);
                        if(view.model.get('default_revision') == this.model.id) this.$el.addClass('warning');
                        return this;
                    }
                });
                var modelCollection = this.model.getRevisions();
                var grid = new Backgrid.Grid({
                    row: row,
                    columns: columns,
                    collection: modelCollection,
                    className: 'table table-striped'
                });
                this.revisions.show(grid);
                grid.sort('revision', 'ascending');

                // Request registry status data for this service in the background
                $.ajax({
                    method: 'GET',
                    url: 'api/services/' + this.model.id + '/status',
                    success: function(data) {
                        view.revisionStatus = JSON.parse(data);
                        modelCollection.trigger('reset', modelCollection, {});
                    },
                    error: function(data) {
                        // Global flag to indicate the repository doesn't exist, is unreachable
                        // or there is some other server-side problem
                        view.revisionStatus = false;
                        modelCollection.trigger('reset', modelCollection, {});
                    }
                });
            }
        });
    });

    return HoneySens.Services.Views.ServiceDetails;
});