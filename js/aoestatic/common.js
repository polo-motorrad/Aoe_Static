/**
 * Send ajax request to the Magento store in order to insert dynamic content into the
 * static page delivered from Varnish
 *
 * @author Fabrizio Branca
 */
var Aoe_Static = {

    COOKIE_PREFIX: 'aoestatic_',
    PLACEHOLDER_CLASS_NAME: '.as-placeholder',

    storeId: null,
    websiteId: null,
    fullActionName: null,
    ajaxCallUrl: null,
    currentProductId: null,

    init: function(ajaxHomeUrl, fullActionName, storeId, websiteId, currentProductId) {
        this.ajaxCallUrl = ajaxHomeUrl;
        this.storeId = storeId;
        this.websiteId = websiteId;
        this.fullActionName = fullActionName;
        this.currentProductId = currentProductId;

        this.populatePage();
    },

    /**
     * Populate page
     */
    populatePage: function() {
        this.replaceCookieContent();
        this.replacePlaceholderBlocks();
        if (this.isLoggedIn()) {
            jQuery('.aoestatic_notloggedin').hide();
            jQuery('.aoestatic_loggedin').show();
        } else {
            jQuery('.aoestatic_loggedin').hide();
            jQuery('.aoestatic_notloggedin').show();
        }
    },

    /**
     * Replace cookie content
     */
    replaceCookieContent: function() {
        jQuery.each(this.getCookieContent(), function(name, value) {
            jQuery('.aoestatic_' + name).text(value);
        })
    },

    isLoggedIn: function() {
        var cookieValues = this.getCookieContent();
        return typeof cookieValues['customername'] != 'undefined' && cookieValues['customername'].length;
    },

    /**
     * Get info from cookies
     */
    getCookieContent: function() {
        // expected format of cookie name: {Aoe_Static.COOKIE_PREFIX}_[g|w<websiteId>|s<storeId>]
        // check first that there is at least one cookie in such format, otherwise just return {}
        if (document.cookie.indexOf(Aoe_Static.COOKIE_PREFIX) == -1) {
            return {};
        }

        var values = {};
        jQuery.each(jQuery.cookie(), function(name, value) {
            if (name.substr(0, 10) == Aoe_Static.COOKIE_PREFIX) {
                name = name.substr(10);
                var parts = name.split('_');
                var scope = parts.splice(0, 1)[0];
                name = parts.join('_');
                if (name && scope) {
                    if (typeof values[name] == 'undefined') {
                        values[name] = {};
                    }
                    values[name][scope] = value;
                }
            }
        });

        var cookieValues = {};
        jQuery.each(values, function(name, data) {
            if (typeof data['s' + Aoe_Static.storeId] != 'undefined') {
                cookieValues[name] = data['s' + Aoe_Static.storeId];
            } else if (typeof data['w' + Aoe_Static.websiteId] != 'undefined') {
                cookieValues[name] = data['w' + Aoe_Static.websiteId];
            } else if (typeof data['g'] != 'undefined') {
                cookieValues[name] = data['g'];
            }
        });
        return cookieValues;
    },

    /**
     * Collect placeholder blocks (html elements marked with this.PLACEHOLDER_CLASS_NAME class)
     *
     * @returns {string[]}
     */
    collectPlaceholderBlocks: function() {
        var blocks = [];
        jQuery(this.PLACEHOLDER_CLASS_NAME).each(function() {
            var rel = jQuery(this).attr('rel');
            if (rel) {
                blocks.push(rel);
            } else {
                throw 'Found placeholder without rel attribute';
            }
        });

        return blocks;
    },

    /**
     * Load placeholder blocks content from server with ajax
     *
     * @param {string[]} [blocks]
     */
    replacePlaceholderBlocks: function(blocks) {
        // allow reload specific blocks
        blocks = blocks || this.collectPlaceholderBlocks();
        if (Object.keys(blocks).length > 0) {
            var data = {'blocks': blocks};

            // add current product
            if (this.currentProductId) {
                data['currentProductId'] = this.currentProductId;
            }

            // E.T. phone home
            jQuery.get(
                Aoe_Static.ajaxCallUrl,
                data,
                function (response) {
                    var $body = jQuery('body');
                    $body.trigger('aoestatic_beforeblockreplace');
                    for (var rel in response.blocks) {
                        $body.trigger('aoestatic_beforeblock_' + rel + '_replace');
                        jQuery(Aoe_Static.PLACEHOLDER_CLASS_NAME + '[rel="' + rel + '"]').html(response.blocks[rel]);
                        $body.trigger('aoestatic_afterblock_' + rel + '_replace');
                    }
                    $body.trigger('aoestatic_afterblockreplace');
                },
                'json'
            );
        }
    }
};
