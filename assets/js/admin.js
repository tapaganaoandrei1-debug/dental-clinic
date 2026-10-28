$(function() {
    $.getJSON('api/auth.php?action=check-session', function(res) {
        if (!res.user || (res.user.role !== 'admin' && res.user.role !== 'staff')) {
            alert('Restricted area.'); window.location.href = 'index.html';
        } else {
            $('#admin-user-badge').text(`${res.user.name} (${res.user.role.toUpperCase()})`);
            loadDesk();
            setInterval(loadDesk, 7000);
        }
    });

    function loadDesk() {
        $.getJSON('api/reports.php?action=daily-summary', function(res) {
            let s = res.analytics;
            $('#stat-total-appts').text(s.total_appointments || 0);
            $('#stat-waiting').text(s.total_waiting || 0);
            $('#stat-served').text(s.total_served || 0);
            $('#stat-noshow').text(s.total_no_shows || 0);
        });
        $.getJSON('api/queue.php?action=admin-roster', function(res) {
            let r = '';
            res.queue.forEach(q => {
                r += `<tr><td>#${q.priority_order}</td><td>${q.token_number}</td><td>${q.reference_no}</td><td>${q.service_name}</td><td><span class="badge bg-primary">${q.queue_status}</span></td><td><button class="btn btn-sm btn-outline-success me-1 btn-act" data-id="${q.id}" data-op="serve">Serve</button><button class="btn btn-sm btn-outline-danger btn-act" data-id="${q.id}" data-op="no_show">No-Show</button></td></tr>`;
            });
            $('#admin-queue-tbody').html(r || '<tr><td colspan="6" class="text-center py-3">No patients waiting today.</td></tr>');
        });
    }

    $(document).on('click', '.btn-act', function() {
        $.ajax({
            url: 'api/queue.php?action=admin-action',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ token_id: $(this).data('id'), operation: $(this).data('op') }),
            success: loadDesk
        });
    });

    $('#btn-admin-logout').on('click', function() {
        $.post('api/auth.php?action=logout', function() { window.location.href = 'index.html'; });
    });
});
