/**
 * Send ajax request to the Magento store in order to insert dynamic content into the
 * static page delivered from Varnish
 *
 * @author Fabrizio Branca
 * @author Bastian Ike
 */
var Aoe_Static = {

    storeId: null,
    websiteId: null,
    fullActionName: null,
    ajaxHomeUrl: null,
    currentProductId: null,

    init: function(ajaxhome_url, fullactionname, storeId, websiteId, currentproductid) {
        this.storeId = storeId;
        this.websiteId = websiteId;
        this.fullActionName = fullactionname;
        this.ajaxHomeUrl = ajaxhome_url;
        this.currentProductId = currentproductid;

        this.populatePage();
    },

    /**
     * populate page
     */
    populatePage: function() {
        this.replaceCookieContent();
        this.replaceAjaxBlocks();
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
        var body = jQuery('body');
        body.trigger('aoestatic_beforecookiereplace');
        jQuery.each(this.getCookieContent(), function(name, value) {
            jQuery('.aoestatic_' + name).text(value);
            // console.log('Replacing ".aoestatic_' + name + '" with "' + value + '"');
        });
        body.trigger('aoestatic_aftercookiereplace');
    },

    isLoggedIn: function() {
        var cookieValues = this.getCookieContent();
        //return typeof cookieValues['customername'] != 'undefined' && cookieValues['customername'].length;
        return typeof cookieValues['isloggedin'] != 'undefined' && cookieValues['isloggedin'] == 1;
    },

    /**
     * Get info from cookies
     */
    getCookieContent: function() {
        // expected format as_[g|w<websiteId>|s<storeId>]
        var values = {};
        jQuery.each(jQuery.cookie(), function(name, value) {
            if (name.substr(0, 10) == 'aoestatic_') {
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
     * Load block content from server
     */
    replaceAjaxBlocks: function() {
        jQuery(document).ready(function($) {
            var data = {
                getBlocks: {}
            };

            // add placeholders
            var counter = 0;
            $('.as-placeholder').each(function() {
                var t = $(this);
                var id = t.attr('id');

                if (!id) {
                    // create dynamic id
                    id = 'ph_' + counter;
                    t.attr('id', id);
                }

                var rel = t.data('rel');
                if (typeof(rel) === 'undefined') {
                    rel = t.attr('rel');
                }

                if (rel) {
                    if (localStorage.getItem('aoe_static_blocks_' + rel)) {
                        $('#' + id).html(localStorage.getItem('aoe_static_blocks_' + rel));
                        jQuery('body').trigger('aoestatic_beforeblockreplace', {
                            blocks : {
                                id: localStorage.getItem('aoe_static_blocks_' + rel)
                            }
                        });
                    }
                    data.getBlocks[id] = rel;
                    counter++;
                } else {
                    // console.log(this);
                    throw 'Found placeholder without rel attribute';
                }
            });

            // E.T. phone home, get blocks and pending flash-messages
            $.get(
                Aoe_Static.ajaxHomeUrl,
                data,
                function (response) {
                    for(var id in response.blocks) {
                        $('#' + id).html(response.blocks[id]);
                        // try to save in localStorage if allowed (f.e. not allowed in private mode on iOS)
                        try {
                            localStorage.setItem('aoe_static_blocks_' + data.getBlocks[id], response.blocks[id]);
                        } catch(e) {}
                    }
                    if (response.form_key) {
                        Aoe_Static.replaceFormKey(response.form_key);
                    }
                    jQuery('body').trigger('aoestatic_afterblockreplace', response);
                },
                'json'
            );
        });
    },

    // replace form_key in links and forms
    replaceFormKey: function (form_key) {
        function newUrl(oldUrl) {
            return oldUrl.replace(/\/form_key\/.*\//g, '/form_key/' + form_key + '/');
        }

        jQuery('a[href*="form_key"]').each(function () {
            var oldLink = jQuery(this).attr('href');
            var newLink = newUrl(oldLink);
            jQuery(this).attr('href', newLink);
        });

        jQuery('form[action*="form_key"]').each(function () {
            var oldAction = jQuery(this).attr('action');
            var newAction = newUrl(oldAction);
            jQuery(this).attr('action', newAction);
        });
    }
};
