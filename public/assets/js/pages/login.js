/* global jQuery */
(function ($) {
    'use strict';

    $(function () {
        var Farmasi = window.Farmasi;
        var $form = $('#login-form');
        var $feedback = $('#feedback');
        var $submit = $form.find('button[type="submit"]');

        $form.on('submit', function (event) {
            event.preventDefault();
            $feedback.empty();
            $submit.prop('disabled', true);

            Farmasi.api.login({
                username: $('#username').val(),
                password: $('#password').val()
            }).then(
                function () {
                    Farmasi.redirect(Farmasi.boot.redirectUrl);
                },
                function (rejection) {
                    $submit.prop('disabled', false);

                    var message = rejection.body && rejection.body.message
                        ? rejection.body.message
                        : Farmasi.text('loginFailed', '');

                    Farmasi.ui.error($feedback, '', [message]);
                }
            );
        });
    });
})(jQuery);
