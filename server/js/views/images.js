define(['app/app',
        'tpl!app/templates/Image.tpl',
        'tpl!app/templates/Images.tpl',
        'tpl!app/templates/ModalImageAdd.tpl',
        'tpl!app/templates/ModalImageUpdateTo.tpl',
        'tpl!app/templates/ModalImageRemove.tpl',
        'app/views/common', 'jquery.fileupload'],
function(HoneySens, ImageTpl, ImagesTpl, ModalImageAddTpl, ModalImageUpdateTo, ModalImageRemove) {
    HoneySens.module('Views', function(Views, HoneySens, Backbone, Marionette, $, _) {
        Views.ImagesItemView = Marionette.ItemView.extend({
            template: ImageTpl,
            tagName: 'tr',
            initialize: function() {
                var view = this;
                this.listenTo(HoneySens.data.models.defaultconfig, 'change', function() {
                    view.render();
                });
            },
            events: {
                'click button.updateToImage': function(e) {
                    e.preventDefault();
                    HoneySens.request('view:modal').show(new Views.ModalUpdateToImage({ model: this.model }));
                },
                'click button.removeImage': function(e) {
                    e.preventDefault();
                    HoneySens.request('view:modal').show(new Views.ModalRemoveImage({ model: this.model }));
                }
            },
            modelEvents: {
                'change': 'render'
            },
            onRender: function() {
                var image = HoneySens.data.models.defaultconfig.get('image');
                if(image != null && this.model.id == image) {
                    this.$el.addClass('warning').siblings('tr').removeClass('warning');
                }
                this.$el.find('button, a').tooltip();
            },
            templateHelpers: {
                isDefault: function() {
                    var image = HoneySens.data.models.defaultconfig.get('image');
                    return image != null && this.id == image;
                },
                isInUse: function() {
                    var id = this.id,
                        inUse = false;
                    _(HoneySens.data.models.configs.toJSON().concat(new Array(HoneySens.data.models.defaultconfig.toJSON()))).each(function(c) {
                        if(c.image == id) {
                            inUse = true;
                        }
                    });
                    return inUse;
                }
            }
        });

        Views.ImagesListView = Marionette.CompositeView.extend({
            template: ImagesTpl,
            childViewContainer: 'tbody',
            childView: Views.ImagesItemView,
            events: {
                'click #addImage': function(e) {
                    e.preventDefault();
                    HoneySens.request('view:modal').show(new Views.ModalAddImage());
                }
            }
        });

        Views.ModalAddImage = Marionette.ItemView.extend({
            template: ModalImageAddTpl,
            onRender: function() {
                var view = this,
                    spinner = Views.inlineSpinner.spin();
                view.$el.find('#imageInfo, div.progress, div.progress-text, div.imageVerify span:not(.glyphicon,.errorMsg)').hide();
                view.$el.find('div.loadingInline').html(spinner.el);
                view.$el.find('#imageUpload').fileupload({
                    url: 'api/sensorimages',
                    dataType: 'json',
                    maxChunkSize: 50000000,
                    start: function() {
                        // TODO use an add callback instead of this to allow saving of the XHR object and allow abortion of the upload task
                        view.$el.find('span.fileinput-button').hide().siblings('div.progress').show();
                        view.$el.find('div.progress-text').show();
                    },
                    progressall: function(e, data) {
                        var progress = parseInt(data.loaded / data.total * 100) + '%';
                        view.$el.find('div.progress-bar').css('width', progress).text(progress);
                        view.$el.find('span.progress-loaded').text((data.loaded / (1000 * 1000)).toFixed(1));
                        view.$el.find('span.progress-total').text(+(data.total / (1000 * 1000)).toFixed(1));
                        if(parseInt(data.loaded / data.total * 100) >= 97) {
                            view.$el.find('div.imageVerify span.imageValidating').show();
                        }
                    },
                    done: function(e, data) {
                        var file = data.result.files[0];
                        if(file.completed) {
                            view.$el.find('div.imageVerify span.imageValid').show().siblings('span').hide();
                            view.$el.find('p.imageName').text(_.escape(file.image.name));
                            view.$el.find('p.imageVersion').text(_.escape(file.image.version));
                            view.$el.find('p.imageDescription').text(_.escape(file.image.description));
                            view.$el.find('#imageInfo').show();
                            view.$el.find('button.btn-default').removeClass('btn-default').addClass('btn-primary').text('Ok');
                            HoneySens.data.models.images.fetch({ reset: true });
                        } else {
                            var errorMsg;
                            switch(file.error) {
                                case 1: errorMsg = 'Firmware ungültig'; break;
                                case 2: errorMsg = 'Firmware enthält fehlerhafte Metadaten'; break;
                                case 3: errorMsg = 'Firmware ist bereits vorhanden'; break;
                            }
                            view.$el.find('div.imageVerify span.errorMsg').text(errorMsg);
                            view.$el.find('div.imageVerify span.imageInvalid').show().siblings('span').hide();
                        }
                    }
                });
            }
        });

        Views.ModalUpdateToImage = Marionette.ItemView.extend({
            template: ModalImageUpdateTo,
            events: {
                'click button.btn-primary': function(e) {
                    e.preventDefault();
                    HoneySens.data.models.defaultconfig.save({ image: this.model.id }, { success: function() {
                        HoneySens.request('view:modal').empty();
                    }});
                }
            }
        });

        Views.ModalRemoveImage = Marionette.ItemView.extend({
            template: ModalImageRemove,
            events: {
                'click button.btn-primary': function(e) {
                    e.preventDefault();
                    this.model.destroy({wait: true, success: function() {
                        HoneySens.request('view:modal').empty();
                    }});
                }
            }
        });
    });
});
