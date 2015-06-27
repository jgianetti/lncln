var App = (function (my) {
    my.modules.cc_help = new function()
    {
        var _module = this;

        this.cache = {};

        this.view = new function()
        {
            // Create html app-windows parsing App.modules._base.templates.window (title, id)
            this.init = function(data)
            {
                App.ajaxDataProcessComplete();
            };
        };
    };

    return my;

}(App || {}));