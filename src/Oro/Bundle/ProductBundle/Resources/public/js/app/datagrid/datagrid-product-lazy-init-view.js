define(function(require) {

    var DatagridProductLazyInitView;
    var _ = require('underscore');
    var LazyInitView = require('orofrontend/js/app/views/lazy-init-view');

    /**
     * @class DatagridProductLazyInitView
     * @extends LazyInitView
     */
    DatagridProductLazyInitView = LazyInitView.extend({
        constructor: function DatagridProductLazyInitView() {
            DatagridProductLazyInitView.__super__.constructor.apply(this, arguments);
        },

        /**
         * Initialize
         *
         * @param {Object} options
         */
        initialize: function(options) {
            DatagridProductLazyInitView.__super__.initialize.apply(this, arguments);

            if (this.lazy === 'scroll') {
                this.listenToOnce(this.collection, {
                    'reset': this.dispose,
                    'backgrid:selectAllVisible': this._onSelectAllVisible
                });
            }
        },

        /**
         * Bind MassAction selectAllVisible trigger with lazy-view
         */
        _onSelectAllVisible: function() {
            this.initLazyView().then(
                _.bind(function() {
                    this.collection.trigger('backgrid:selectAllVisible');
                }, this)
            );
        }
    });

    return DatagridProductLazyInitView;
});
