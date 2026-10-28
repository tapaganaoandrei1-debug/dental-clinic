$(function() {
    $('#book-date').attr('min', new Date().toISOString().split('T')[0]);

    $.getJSON('api/auth.php?action=check-session', function(res) {
        if (res.status === 'success' && res.user) {
            let desk = (res.user.role === 'admin' || res.user.role === 'staff') ? '<a href="admin.html" class="btn btn-warning btn-sm rounded-pill ms-2">Desk</a>' : '';
            $('#auth-nav-container').html(`<span class="text-white small me-2">Hi, ${res.user.name}</span>${desk}<button class="btn btn-outline-light btn-sm rounded-pill ms-2" id="btn-logout">Sign Out</button>`);
        }
    });

    $('#book-staff, #book-service, #book-date').on('change', function() {
        let staff = $('#book-staff').val(), srv = $('#book-service').val(), d = $('#book-date').val();
        if (staff && srv && d) {
            $.getJSON('api/appointment.php', { action: 'available-slots', staff_id: staff, service_id: srv, date: d }, function(res) {
                let opts = '<option value="" disabled selected>Pick slot...</option>';
                res.slots.forEach(s => { opts += `<option value="${s.start_time}">${s.display}</option>`; });
                $('#book-slot').html(opts).prop('disabled', false);
            });
        }
    });

    $('#booking-form').on('submit', function(e) {
        e.preventDefault();
        $.ajax({
            url: 'api/appointment.php?action=book',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ staff_id: $('#book-staff').val(), service_id: $('#book-service').val(), date: $('#book-date').val(), start_time: $('#book-slot').val() }),
            success: function(res) {
                alert(`Booked! Token: ${res.token} (Ref:${res.reference_no})`);
                $('#track-token-input').val(res.token);
                $('#btn-poll-queue').trigger('click');
            },
            error: function(xhr) { alert(xhr.responseJSON?.message || 'Please sign in first.'); }
        });
    });

    $('#btn-poll-queue').on('click', function() {
        let t = $('#track-token-input').val().trim();
        if (!t) return;
        $.getJSON('api/queue.php', { action: 'live-status', token: t }, function(res) {
            if (res.status === 'success') {
                $('#queue-display-box').removeClass('d-none');
                $('#disp-my-token').text(res.my_token);
                $('#disp-curr-token').text(res.current_called);
                $('#disp-ahead').text(res.ahead);
                $('#disp-est-wait').text(res.wait_estimate);
            } else { alert(res.message); }
        });
    });

    $('#form-login').on('submit', function(e) {
        e.preventDefault();
        $.ajax({
            url: 'api/auth.php?action=login',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ email: $('#login-email').val(), password: $('#login-password').val() }),
            success: function(res) {
                if (res.user.role === 'admin' || res.user.role === 'staff') window.location.href = 'admin.html';
                else location.reload();
            },
            error: function(xhr) { alert(xhr.responseJSON?.message || 'Login failed'); }
        });
    });

    $(document).on('click', '#btn-logout', function() {
        $.post('api/auth.php?action=logout', function() { location.reload(); });
    });
});
