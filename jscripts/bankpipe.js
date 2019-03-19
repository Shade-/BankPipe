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
        currency: 'EUR',
        cartItems: 0,

        init: function(options) {

            BankPipe.options = $.extend({
            }, options);

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

            // Purchase
            $('.purchase').on('click', function(e) {

                e.preventDefault();

                var data = {};

                var items = $(this).data('items');
                if (items) {
                    data['items'] = items;
                }
                else {
                    data['fromCookies'] = 1;
                }

                overlay.fadeIn(500);

                if (BankPipe.requiredFields.length) {

                    $.each(BankPipe.requiredFields, (k, v) => {

                        var input = $('input[name="' + v + '"]');
                        var val = input.val();

                        if (input.is('input:checkbox:checked, input:radio:checked') || (val && !input.is('input:checkbox, input:radio'))) {
                            data[v] = val;
                        }

                    });

                }

                var w;
                var callback = function(response) {

                    // Errors
                    if (response.errors) {
                        overlay.fadeOut(500);
                    }
                    // Success, open window
                    else if (response.url) {

                        // Store the id, will be cleared if the payment is cancelled
                        var orderId = response.invoice;

                        overlay.text(BankPipe.lang.waitingConfirmation);
                        w = BankPipe.popup(response.url, '900', '600');

                        var iterations = 0;
                        var promise = new Promise((resolve, reject) => {

                            var interval = setInterval(function() {
                                try {

                                    // Popup was closed beforehand
                                    if (w.closed) {

                                        // Clear this payment
                                        BankPipe.ajax.request('bankpipe.php?action=cancel&orderId=' + orderId);
                                        resolve({
                                            cancelled: 1
                                        });
                                        clearInterval(interval);

                                    }

                                    // Popup has switched over our own site, close and process response
                                    if (w.location.hostname == window.location.hostname) {

                                        if (w.document.body.textContent) {

                                            resolve(w.document.body.textContent);
                                            clearInterval(interval);

                                        }
                                        else {

                                            // Resolve automatically after 100 iterations = 20 seconds
                                            if (iterations >= 100) {
                                                reject('Timed out');
                                            }

                                            iterations++;

                                        }

                                    }

                                }
                                catch (err) {} // Blocked frames
                            }, 50);

                        });

                        promise.then(response => {

                            console.log('raw', response);

                            w.close();
                            overlay.fadeOut(500).promise().done(() => {
                                return overlay.text(BankPipe.lang.preparingPayment);
                            });

                            if (response.constructor !== {}.constructor) {

                                try {
                                    var response = $.parseJSON(response);
                                }
                                catch (e) {
                                    console.log(e);
                                    return $.jGrowl(BankPipe.lang.responseMalformed, {'theme': 'jgrowl_error'});
                                }

                            }

                            // Cancelled
                            if (response.cancelled) {
                                return $.jGrowl(BankPipe.lang.paymentCancelled, {'theme': 'jgrowl_error'});
                            }
                            // Success
                            else if (response.status == BankPipe.responseStatusCodes.success) {

                                // Show confirmation message
                                $.jGrowl(BankPipe.lang.paymentSuccessful.replace('{reference}', response.reference), {'theme': 'jgrowl_success'});

                                // Set up a 3 seconds timer to redirect the user to the invoice
                                if (response.invoice) {
                                    BankPipe.location.redirect('./usercp.php?action=purchases&env=bankpipe&invoice=' + response.invoice, 3);
                                }

                            }
                            // Pending
                            else if (response.status == BankPipe.responseStatusCodes.pending) {
                                return $.jGrowl(BankPipe.lang.paymentPending.replace('{reference}', response.reference), {'theme': 'jgrowl_success'});
                            }

                        }).catch(error => {
                            overlay.fadeOut(500);
                            console.log(error);
                        });
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

                        if (response.action == 'add') {

                            btn.text(BankPipe.lang.removeFromCart)
                                .removeClass(BankPipe.classes.add)
                                .addClass(BankPipe.classes.remove)
                                .attr('href', btn.attr('href').replace('add=1', 'remove=1'));

                        }
                        else {

                            var aid = btn.data('aid');
                            var row = $('.item[data-aid="' + aid + '"]');

                            if (row.length) {
                                row.remove();
                            }
                            else {

                                btn.text(BankPipe.lang.addToCart)
                                    .removeClass(BankPipe.classes.remove)
                                    .addClass(BankPipe.classes.add)
                                    .attr('href', btn.attr('href').replace('remove=1', 'add=1'));

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

            // Puts focus on the newWindow
            if (window.focus) newWindow.focus();

            return newWindow;
        },

        items: {
            getCart: function() {
                var items = BankPipe.cookies.get('items');
                return (BankPipe.exists(items)) ? JSON.parse(items) : [];
            }
        },

        cookies: {
            get: function(name) {
                return Cookies.get('bankpipe-' + name);
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
