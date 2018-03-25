define(['marionette', 'app/views/regions', 'tpl!app/templates/RootLayout.tpl'],
function(Marionette, Regions, RootLayoutTpl) {
    var RootLayout = Marionette.LayoutView.extend({
            el: 'body',
            template: RootLayoutTpl,
            regions: {
                content: '#content'
            },
            onRender: function() {
                this.addRegion('modal', new Regions.ModalRegion({el: '#modal'}));
            }
        });

    return RootLayout;
});