define(['app/app', 'tpl!app/templates/Sidebar.tpl', 'app/views/common'], function(HoneySens, SidebarTpl) {
    HoneySens.module('Views', function(Views, HoneySens, Backbone, Marionette, $, _) {
        Views.SidebarView = Marionette.ItemView.extend({
            template: SidebarTpl,
            events: {
                'mouseenter': function() {
                    this.$el.addClass('expanded');
                },
                'mouseleave': function() {
                    this.$el.removeClass('expanded');
                }
            },
            initialize: function() {
                this.listenTo(HoneySens.vent, 'dashboardShown', function() {
                    this.$('ul.nav li').removeClass('active');
                    this.$('li a[href="#"]').parent('li').addClass('active');
                });
                this.listenTo(HoneySens.vent, 'configsShown', function() {
                    this.$('ul.nav li').removeClass('active');
                    this.$('li a[href="#configs"]').parent('li').addClass('active');
                });
                this.listenTo(HoneySens.vent, 'imagesShown', function() {
                    this.$('ul.nav li').removeClass('active');
                    this.$('li a[href="#images"]').parent('li').addClass('active');
                });
                // match routes with sidebar highlighting
                // TODO consider using Marionette AppRouter to get the current fragment more easily
                this.listenTo(Backbone.history, 'route', function(router, route, params) {
                    var $sidebar = this.$el;
                    if(router.current) {
                        var fragment = router.current().fragment;
                        $sidebar.find('ul.nav-sidebar li > a').each(function() {
                            if($(this).attr('href') == '#' + fragment) {
                                var $node = $(this).parent('li').addClass('active');
                                $sidebar.find('ul.nav-sidebar li').not($node).removeClass('active');
                            }
                        });
                    }
                });
            },
            onRender: function() {
                this.$el.find('ul.nav-sidebar').append(Views.createMenu(HoneySens.menuItems));
            },
            templateHelpers: {
                showVersion: function() {
                    return HoneySens.data.system.get('version');
                }
            }
        });
    });
});
