{{-- Header check in / check out button of the field users --}}
<div class="header-element" id="checkInElement">
    <a href="javascript:void(0);" class="header-link d-flex align-items-center gap-1 px-2" id="checkInButton" aria-live="polite" title="Check in">
        <i class="fe fe-log-in" id="checkInIcon"></i>
        <span class="d-none d-md-inline fw-medium" id="checkInLabel">Check In</span>
        <span class="d-none d-md-inline small text-muted" id="checkInTimer"></span>
    </a>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const routes = {
            status: @json(route('attendance.status')),
            checkIn: @json(route('attendance.check-in')),
            checkOut: @json(route('attendance.check-out')),
            acknowledge: @json(route('attendance.acknowledge')),
        };
        const csrf = document.querySelector('meta[name="csrf-token"]').content;
        const button = document.getElementById('checkInButton');
        const icon = document.getElementById('checkInIcon');
        const label = document.getElementById('checkInLabel');
        const timer = document.getElementById('checkInTimer');
        let checkedInAt = null;
        let busy = false;

        function request(url, method, body) {
            return fetch(url, {
                method: method,
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: body ? JSON.stringify(body) : undefined,
            }).then(function (response) {
                return response.json().then(function (data) {
                    if (!response.ok) { throw new Error(data.message || 'Something went wrong. Please try again.'); }
                    return data;
                });
            });
        }

        // the location is optional: no permission, no support or a slow device never blocks the check
        function position() {
            return new Promise(function (resolve) {
                if (!navigator.geolocation) { return resolve({}); }
                navigator.geolocation.getCurrentPosition(function (p) {
                    resolve({ lat: p.coords.latitude, lng: p.coords.longitude, accuracy: p.coords.accuracy });
                }, function () { resolve({}); }, { timeout: 5000, maximumAge: 60000 });
            });
        }

        function notice(type, text, hideAfter) {
            let host = document.getElementById('checkInNotices');
            if (!host) {
                host = document.createElement('div');
                host.id = 'checkInNotices';
                host.style.cssText = 'position:fixed;top:70px;right:16px;z-index:1080;max-width:380px;';
                document.body.appendChild(host);
            }
            const box = document.createElement('div');
            box.className = 'alert alert-' + type + ' alert-dismissible shadow-sm mb-2';
            box.setAttribute('role', 'alert');
            // solid, deeper fills so the notice reads clearly over the page
            const fills = { warning: '#b45309', danger: '#b91c1c', success: '#15803d', info: '#0369a1' };
            box.style.cssText = 'background:' + (fills[type] || '#334155') + ';border-color:transparent;color:#fff;';
            box.textContent = text;
            const close = document.createElement('button');
            close.type = 'button';
            close.className = 'btn-close';
            close.style.filter = 'invert(1) grayscale(100%) brightness(200%)';
            close.setAttribute('aria-label', 'Close');
            close.onclick = function () { box.remove(); };
            box.appendChild(close);
            host.appendChild(box);
            if (hideAfter) { setTimeout(function () { box.remove(); }, hideAfter); }
            return box;
        }

        function warn(dates) {
            if (!dates || !dates.length) { return; }
            const box = notice('warning', 'You forgot to check out on ' + dates.join(', ')
                + '. The session was closed automatically and is marked incomplete.');
            // once the user closes the warning they have seen it
            box.querySelector('.btn-close').addEventListener('click', function () {
                request(routes.acknowledge, 'POST').catch(function () {});
            });
        }

        function tick() {
            if (!checkedInAt) { timer.textContent = ''; return; }
            const minutes = Math.max(0, Math.floor((Date.now() - checkedInAt) / 60000));
            timer.textContent = String(Math.floor(minutes / 60)).padStart(2, '0') + ':' + String(minutes % 60).padStart(2, '0') + 'h';
        }

        function render(state) {
            checkedInAt = state.checked_in ? new Date(state.checked_in_at).getTime() : null;
            button.classList.toggle('text-danger', state.checked_in);
            button.classList.toggle('text-success', !state.checked_in);
            icon.className = 'fe ' + (state.checked_in ? 'fe-log-out' : 'fe-log-in');
            label.textContent = state.checked_in ? 'Check Out' : 'Check In';
            button.title = state.checked_in ? 'Check out' : 'Check in';
            tick();
        }

        // SweetAlert2 is only loaded on some pages, so fetch it on demand when it is missing
        function loadSwal() {
            if (window.Swal) { return Promise.resolve(window.Swal); }
            return new Promise(function (resolve, reject) {
                const script = document.createElement('script');
                script.src = 'https://cdn.jsdelivr.net/npm/sweetalert2@11';
                script.onload = function () { resolve(window.Swal); };
                script.onerror = reject;
                document.head.appendChild(script);
            });
        }

        function confirmCheckOut() {
            return loadSwal().then(function (Swal) {
                return Swal.fire({
                    title: 'Check out now?',
                    text: 'This ends your current work session.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, check out',
                }).then(function (result) { return result.isConfirmed; });
            }).catch(function () {
                return window.confirm('Check out now?');
            });
        }

        function submit(leaving) {
            busy = true;
            button.classList.add('disabled');
            position().then(function (geo) {
                return request(leaving ? routes.checkOut : routes.checkIn, 'POST', geo);
            }).then(function (state) {
                render(state);
                notice('success', state.message, 4000);
                warn(state.warnings);
            }).catch(function (error) {
                notice('danger', error.message, 8000);
            }).finally(function () {
                busy = false;
                button.classList.remove('disabled');
            });
        }

        button.addEventListener('click', function () {
            if (busy) { return; }
            if (checkedInAt === null) { return submit(false); }

            busy = true; // block double clicks while the dialog is open
            confirmCheckOut().then(function (confirmed) {
                busy = false;
                if (confirmed) { submit(true); }
            });
        });

        request(routes.status, 'GET').then(function (state) {
            render(state);
            warn(state.warnings);
            if (state.reminder) {
                const box = notice('info', 'You are still checked in. Remember to check out when you finish for the day.');
                box.querySelector('.btn-close').addEventListener('click', function () {
                    request(routes.acknowledge, 'POST').catch(function () {});
                });
            }
        }).catch(function () {});

        setInterval(tick, 30000);
    });
</script>
