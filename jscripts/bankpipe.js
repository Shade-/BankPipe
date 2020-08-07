(function() {

    BankPipe = {

        requiredFields: '',
        templates: {
            noItems: ''
        },
        lang: {
            addToCart: '',
            removeFromCart: '',
            preparingPayment: '',
            paymentCancelled: '',
            paymentSuccessful: '',
            paymentPending: '',
            waitingConfirmation: '',
            responseMalformed: ''
        },
        responseStatusCodes: {
            success: 2,
            pending: 3
        },
        elements: {
            appliedDiscounts: '.appliedDiscounts',
            wrapper: '#wrapper',
            overlay: '#bankpipe-overlay'
        },
        classes: {
            hidden: 'hidden',
            add: 'add',
            remove: 'remove'
        },
		links: {
			cart: 'usercp.php?action=cart&env=bankpipe'
		},
        currency: 'EUR',
        cartItems: 0,
        popupClosedAutomatically: false,
        overlayAnimationSpeed: 350,

        init: function(options) {

            BankPipe.options = $.extend({}, options);

            BankPipe.wrapper = $(BankPipe.elements.wrapper);
            BankPipe.requiredFields = BankPipe.requiredFields.split(',');

            var overlay = $(BankPipe.elements.overlay);
            overlay.hide().css({
                background: 'rgba(0,0,0,.7)',
                position: 'fixed',
                height: '100%',
                width: '100%',
                'z-index': '99999999',
                top: 0,
                left: 0,
                color: 'white',
                'font-size': '1.5rem',
                'text-align': 'center',
                'line-height': '100vh',
            });

            // Close overlay on click, just in case it gets stuck
            overlay.on('click', function(e) {
                return overlay.fadeOut(BankPipe.overlayAnimationSpeed);
            });

            // Purchase
            $('.purchase').on('click', function(e) {

                e.preventDefault();

                // Reset popup marker
                BankPipe.popupClosedAutomatically = false;

                var data = {};

				data.gateway = $(this).data('gateway') || 'Coinbase';

                overlay.fadeIn(BankPipe.overlayAnimationSpeed);

                if (BankPipe.requiredFields.length) {

                    $.each(BankPipe.requiredFields, (k, v) => {

                        var input = $('input[name="' + v + '"]');
                        var val = input.val();

                        if (input.is('input:checkbox:checked, input:radio:checked') || (val && !input.is('input:checkbox, input:radio'))) {
                            data[v] = val;
                        }

                    });

                }

                // Get eventual extra fields
                $.each($('.includeInRequest'), function() {

                    var input = $(this);
                    var val = input.val();
                    var name = input.attr('name');

                    if (name) {
                        data[name] = val;
                    }

                });

                var callback = function(response) {

                    // Errors
                    if (response.errors) {
                        overlay.fadeOut(BankPipe.overlayAnimationSpeed);
                    }
                    // Success, open window
                    else if (response.url) {

                        // Store the id, will be cleared if the payment is cancelled
                        var orderId = response.invoice;

                        overlay.text(BankPipe.lang.waitingConfirmation);
                        var w = BankPipe.popup(response.url, '900', '700');

                        // Set up a listening interval to know if the popup is closed or not
                        var interval = setInterval(function() {
                            try {

                                if (BankPipe.popupClosedAutomatically) {
                                    BankPipe.popupClosedAutomatically = false;
                                    return clearInterval(interval);
                                }

                                // Popup was closed beforehand, manually
                                if (w.closed && !BankPipe.popupClosedAutomatically) {

                                    // Everything but cryptos? Clear
                                    if (data.gateway != 'Coinbase') {

                                        BankPipe.ajax.request('bankpipe.php?action=cancel&type=manual&orderId=' + orderId);

                                        $.jGrowl(BankPipe.lang.paymentCancelled, {'theme': 'jgrowl_error'});

                                    }
                                    // Cryptos are marked as "pending" by default. Clear cart and redirect
                                    else {

                                        $.jGrowl(BankPipe.lang.paymentPending, {'theme': 'jgrowl_success'});

                                        BankPipe.items.clearCart();

                            			if (response.invoice) {
                            				BankPipe.location.redirect('./usercp.php?action=purchases&env=bankpipe&invoice=' + response.invoice, 3);
                            			}

                                    }

                                    overlay.fadeOut(BankPipe.overlayAnimationSpeed).promise().done(() => {
                                        return overlay.text(BankPipe.lang.preparingPayment);
                                    });

                                    clearInterval(interval);

                                }

                            }
                            catch (err) {} // Blocked frames
                        }, 50);
                    }

                };

                BankPipe.ajax.request('bankpipe.php?action=authorize', data, callback);

            });

            // Cart
            $('.bankpipe-item').on('click', function(e) {

                e.preventDefault();

                var btn = $(this);
                var callback = function(response) {

                    var cartNav = $('.cart .counter');
                    var itemsInCart = BankPipe.items.getCart();
                    var itemsCount = itemsInCart.length;

                    if (response.message) {

						var location = btn.attr('href');

                        if (response.action == 'add') {

							// Fast checkout
							if (btn.hasClass('fastCheckout')) {
								return window.location.href = BankPipe.links.prefix + BankPipe.links.cart;
							}

                            btn.text(BankPipe.lang.removeFromCart)
                                .removeClass(BankPipe.classes.add)
                                .addClass(BankPipe.classes.remove)
                                .attr('href', location.replace('add=1', 'remove=1'));

                        }
                        else {

                            var bid = btn.data('bid');
                            var row = $('.item[data-bid="' + bid + '"]');

                            if (row.length) {
                                row.remove();
                            }
                            else {

                                btn.text(BankPipe.lang.addToCart)
                                    .removeClass(BankPipe.classes.remove)
                                    .addClass(BankPipe.classes.add)
                                    .attr('href', location.replace('remove=1', 'add=1'));

                            }

                        }

                        if (itemsCount > 0) {
                            cartNav.removeClass(BankPipe.classes.hidden).text(itemsCount);
                        }
                        else {

                            if (BankPipe.exists(row) && row.length) {
                                BankPipe.wrapper.html(BankPipe.templates.noItems);
                                $('.buy-area').remove();
                            }

                            cartNav.addClass(BankPipe.classes.hidden).text(itemsCount);

                        }

                    }

                };

                BankPipe.ajax.request(btn.attr('href'), {}, callback);

            });

            // Discounts
            $('form.discounts').on('submit', function(e) {

                e.preventDefault();

                var form = $(this);

                var callback = function(response) {

                    if (response.data) {

                        // Reload the page
                        BankPipe.location.reload(2);

                        // TO-DO: refactor the code to apply the discount in real time
/*
                        // Apply the discount to items
                        // Get the type
                        var type = (response.data.type == 1) ? '%' : BankPipe.currency;
                        var applyDiscount = function(item) {

                            var $item = $(item);
                            $item.find('.discounts').text('– ' + response.data.value + type);
                            var currentPrice = Number($item.data('price'));
                            var discount = (response.data.type == 1) ?
                                (currentPrice / 100 * response.data.value) :
                                response.data.value;

                            $item.find('.price').text(currentPrice - discount);

                            var total = Number($('.total').text()) - discount;
                            return $('.total').text(total);

                        };

                        if (response.data.aids == 'all') {

                            $('.item[data-aid]').each((index, item) => {
                                return applyDiscount(item);
                            });

                        }
                        else {
                            $.each(response.data.aids, (index, aid) => {
                                return applyDiscount('[data-aid="' + aid + '"]');
                            });
                        }

                        // Show discount code template
                        if (response.template) {
                            $(BankPipe.elements.appliedDiscounts).append(response.template);
                        }
*/

                    }

                };

                BankPipe.ajax.request(form.attr('action'), form.serialize(), callback);

            });

            $(document).on('click', '.discounts .remove', function(e) {

                e.preventDefault();

                var $this = $(this);
                var callback = function(response) {

                    BankPipe.location.reload(2);

                    // TO-DO: refactor the code to remove the discount in real time
/*
                    if (response.message) {
                        $this.parents('[data-did]').remove();
                    }
*/

                };

                BankPipe.ajax.request($(this).attr('href'), {}, callback);

            });

        },

        popup: function(url, w, h) {
            // Fixes dual-screen position
            var dualScreenLeft = window.screenLeft != undefined ? window.screenLeft : window.screenX;
            var dualScreenTop = window.screenTop != undefined ? window.screenTop : window.screenY;

            var width = window.innerWidth ? window.innerWidth : document.documentElement.clientWidth ? document.documentElement.clientWidth : screen.width;
            var height = window.innerHeight ? window.innerHeight : document.documentElement.clientHeight ? document.documentElement.clientHeight : screen.height;

            var systemZoom = width / window.screen.availWidth;
            var left = (width - w) / 2 / systemZoom + dualScreenLeft;
            var top = (height - h) / 2 / systemZoom + dualScreenTop;
            var newWindow = window.open(url, '', 'scrollbars=yes, width=' + w / systemZoom + ', height=' + h / systemZoom + ', top=' + top + ', left=' + left);

            // Cannot open due to popup blockers? (eg.: iOS Safari, some ad blockers)
            if (newWindow == null || typeof(newWindow) == 'undefined') {
                alert('Please turn off your popup blocker. We are trying to open a secure channel through which you can perform a payment. Thanks!');
            }

            if (window.focus && newWindow) newWindow.focus();

            return newWindow;
        },

        processPopupMessage: function(response) {

            var overlay = $(BankPipe.elements.overlay);

            overlay.fadeOut(BankPipe.overlayAnimationSpeed).promise().done(() => {
                return overlay.text(BankPipe.lang.preparingPayment);
            });

            // Cancelled
            if (response.cancelled) {
                return $.jGrowl(BankPipe.lang.paymentCancelled, {'theme': 'jgrowl_error'});
            }
            // Success
            else if (response.status == BankPipe.responseStatusCodes.success) {
                $.jGrowl(BankPipe.lang.paymentSuccessful.replace('{reference}', response.reference), {'theme': 'jgrowl_success'});
            }
            // Pending
            else if (response.status == BankPipe.responseStatusCodes.pending) {
                $.jGrowl(BankPipe.lang.paymentPending, {'theme': 'jgrowl_success'});
            }

			// Set up a 3 seconds timer to redirect the user to the invoice
			if (response.invoice) {
				BankPipe.location.redirect('./usercp.php?action=purchases&env=bankpipe&invoice=' + response.invoice, 3);
			}
        },

        items: {
            getCart: function() {
                var items = BankPipe.cookies.get('items');
                return (BankPipe.exists(items)) ? JSON.parse(items) : [];
            },
            clearCart: function() {
                BankPipe.cookies.destroy('items');
                BankPipe.cookies.destroy('discounts');
            }
        },

        cookies: {
            get: function(name) {
                return Cookie.get('bankpipe-' + name);
            },
            destroy: function(name) {
                return Cookie.unset('bankpipe-' + name);
            }
        },

        exists: function(variable) {

            return (typeof variable !== 'undefined' && variable != null && variable)
                ? true
                : false;

        },

        location: {

            redirect: function(url, seconds) {
                return setTimeout(() => {
                    window.location.href = url;
                }, seconds * 1000);
            },

            reload: function(seconds) {
                return setTimeout(() => {
                    window.location.reload();
                }, seconds * 1000);
            }

        },

        ajax: {

            request: function(url, data, callback) {

                return $.ajax({
                    type: 'GET',
                    url: url,
                    data: data,
                    complete: (xhr, status) => {

                        try {
                            var response = $.parseJSON(xhr.responseText);
                        }
                        catch (e) {
                            console.log(e);
                            return $.jGrowl(BankPipe.lang.responseMalformed, {'theme': 'jgrowl_error'});
                        }

                        if (response.errors) {
                            $.each(response.errors, (index, msg) => {
                                $.jGrowl(msg, {'theme': 'jgrowl_error'});
                            });
                        }
                        else if (response.message) {
                            $.jGrowl(response.message, {'theme': 'jgrowl_success'});
                        }

                        if (typeof callback == 'function') {
                            callback(response);
                        }

                    }
                });

            }

        }

    }

})();
