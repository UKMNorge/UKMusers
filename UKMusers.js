jQuery(document).on('click', '.userActivate', function(e) {
    e.preventDefault();

    var knapp = jQuery(this);
    knapp.attr('startText', knapp.html()).html('Aktiverer...');

    jQuery.post(
        ajaxurl, {
            action: 'UKMusers_ajax',
            controller: 'aktiver',
            user_id: knapp.data('user-id')
        },
        function(response) {
            if (response.success) {
                jQuery('#aktiver_' + response.POST.user_id).html('Aktivert!').addClass('text-success').removeClass('text-danger');
                setTimeout(
                    function() {
                        jQuery('#aktiver_' + response.POST.user_id).slideUp();
                    },
                    2200
                );
                return true;
            }
            alert('Beklager, en feil har oppstått!');
            knapp.html(knapp.attr('startText'));
        },
    ).fail(
        function() {
            alert('Beklager, en feil har oppstått!');
            knapp.html(knapp.attr('startText'));
        }
    );
});