/** @lends PaymentSaveForLaterComponent */
define(function(require) {
    'use strict';

    var PaymentSaveForLaterComponent;
    var $ = require('jquery');
    var _ = require('underscore');
    var mediator = require('oroui/js/mediator');

    var BaseComponent = require('oroui/js/app/components/base/component');

    PaymentSaveForLaterComponent = BaseComponent.extend(/** @exports PaymentSaveForLaterComponent.prototype */ {
        /**
         * @property {jQuery}
         */
        $el: null,

        /**
         * @property {Object}
         */
        selectors: {
            saveForLaterSelector: '[name$="[payment_save_for_later]"]'
        },

        /**
         * @property {Boolean}
         */
        defaultState: false,

        /**
         * @inheritDoc
         */
        initialize: function(options) {
            this.$el = $(options._sourceElement);
            this.defaultState = this.getCheckboxState();

            mediator.on('checkout:payment:save-for-later:change', _.bind(this.onChanged, this));
            mediator.on('checkout:payment:save-for-later:restore-default', _.bind(this.onRestoreDefault, this));
        },

        /**
         * @param {Boolean} state
         */
        onChanged: function(state) {
            this.setCheckboxState(state);
        },

        onRestoreDefault: function() {
            this.setCheckboxState(this.defaultState);
        },

        setCheckboxState: function(state) {
            this.getPaymentSaveForLaterElement().prop('checked', state);
            this.getPaymentSaveForLaterElement().trigger('change');
        },

        /**
         * @returns {Boolean}
         */
        getCheckboxState: function() {
            return this.getPaymentSaveForLaterElement().prop('checked');
        },

        /**
         * @returns {jQuery}
         */
        getPaymentSaveForLaterElement: function() {
            if (!this.hasOwnProperty('$paymentSaveForLaterElement')) {
                this.$paymentSaveForLaterElement = this.$el.find(this.selectors.saveForLaterSelector);
            }

            return this.$paymentSaveForLaterElement;
        },

        /**
         * @inheritDoc
         */
        dispose: function() {
            if (this.disposed) {
                return;
            }

            mediator.off('checkout:payment:save-for-later:change', _.bind(this.onChanged, this));
            mediator.off('checkout:payment:save-for-later:restore-default', _.bind(this.onRestoreDefault, this));

            PaymentSaveForLaterComponent.__super__.dispose.call(this);
        }
    });

    return PaymentSaveForLaterComponent;
});
