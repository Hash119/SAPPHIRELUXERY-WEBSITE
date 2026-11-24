    </main>

    <!-- Bottom system bar -->
    <footer class="px-6 py-3 bg-slate-900/60 border-t border-slate-800/80 text-[11px] text-slate-500 flex flex-col sm:flex-row items-center justify-between gap-2">
        <div>
            &copy; <?= date('Y') ?> <span class="text-slate-400 font-medium"><?= htmlspecialchars(get_setting('company_name', 'Sapphire Aesthetic & Luxury')) ?></span>. All rights reserved.
        </div>
        <div class="flex items-center gap-4">
            <span class="flex items-center gap-1.5"><i class="fa-solid fa-server text-[9px] text-emerald-400"></i> Hostinger Cloud</span>
            <span class="text-slate-600">|</span>
            <span class="flex items-center gap-1.5"><i class="fa-solid fa-fingerprint text-[9px] text-gold-400"></i> VFT Biometrics</span>
        </div>
    </footer>
</div>
</div>

<!-- Theme & Mobile Scripts -->
<script>
    // Theme Switcher Logic
    function updateThemeUI(theme) {
        const themeIcon = document.getElementById('themeIcon');
        const themeLabel = document.getElementById('themeLabel');
        const headerThemeIcon = document.getElementById('headerThemeIcon');

        if (theme === 'light') {
            document.documentElement.classList.remove('dark');
            if (themeIcon) themeIcon.className = 'fa-solid fa-sun w-4 text-center text-amber-500';
            if (themeLabel) themeLabel.innerText = 'Light Mode';
            if (headerThemeIcon) headerThemeIcon.className = 'fa-solid fa-sun text-sm text-amber-500';
        } else {
            document.documentElement.classList.add('dark');
            if (themeIcon) themeIcon.className = 'fa-solid fa-moon w-4 text-center text-slate-300';
            if (themeLabel) themeLabel.innerText = 'Dark Mode';
            if (headerThemeIcon) headerThemeIcon.className = 'fa-solid fa-moon text-sm text-slate-300';
        }
    }

    function toggleTheme() {
        const currentTheme = localStorage.getItem('sapphire_theme') === 'light' ? 'dark' : 'light';
        localStorage.setItem('sapphire_theme', currentTheme);
        updateThemeUI(currentTheme);
    }

    // Initialize Theme on load
    document.addEventListener('DOMContentLoaded', () => {
        const savedTheme = localStorage.getItem('sapphire_theme') || 'dark';
        updateThemeUI(savedTheme);
    });

    // Mobile drawer script
    const mobileBtn = document.getElementById('mobileMenuBtn');
    const mobileDrawer = document.getElementById('mobileDrawer');
    const mobileCloseBtn = document.getElementById('mobileCloseBtn');
    const mobileBackdrop = document.getElementById('mobileBackdrop');

    if (mobileBtn && mobileDrawer) {
        mobileBtn.addEventListener('click', () => mobileDrawer.classList.remove('hidden'));
        if (mobileCloseBtn) mobileCloseBtn.addEventListener('click', () => mobileDrawer.classList.add('hidden'));
        if (mobileBackdrop) mobileBackdrop.addEventListener('click', () => mobileDrawer.classList.add('hidden'));
    }

    // Helper Toast notification function
    function showToast(icon, title) {
        const isDark = document.documentElement.classList.contains('dark');
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: icon,
            title: title,
            showConfirmButton: false,
            timer: 3500,
            timerProgressBar: true,
            background: isDark ? '#0f172a' : '#ffffff',
            color: isDark ? '#f8fafc' : '#0f172a',
            iconColor: icon === 'success' ? '#10b981' : (icon === 'error' ? '#f43f5e' : '#f59e0b')
        });
    }

    // Auto-detect alert messages in URL
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('success')) showToast('success', urlParams.get('success'));
    if (urlParams.has('error')) showToast('error', urlParams.get('error'));

    // ============================================================
    // SMART 5-SECOND LIVE AUTO-UPDATE ENGINE
    // Polls every 5 seconds; only refreshes when live data changes,
    // preserves scroll position, and pauses while typing or modals are open!
    // ============================================================
    (function initSapphireLiveRefresh() {
        const skipPages = ['upload_attendance.php', 'change_password.php', 'settings.php'];
        const curFile = (window.location.pathname.split('/').pop() || '').toLowerCase();
        if (skipPages.includes(curFile)) return;

        const mainEl = document.querySelector('main');
        const savedScrollKey = 'sapphire_scroll_' + curFile;

        // Restore scroll position smoothly after a live update
        if (mainEl) {
            const savedY = sessionStorage.getItem(savedScrollKey);
            if (savedY !== null) {
                mainEl.scrollTop = parseInt(savedY, 10) || 0;
                sessionStorage.removeItem(savedScrollKey);
            }
        }

        let liveEnabled = localStorage.getItem('sapphire_live_5s') !== 'off';
        let baselineDigest = null;
        let isChecking = false;

        function updateLiveUI(statusText) {
            const dot = document.getElementById('liveRefreshDot');
            const lbl = document.getElementById('liveRefreshLabel');
            if (!dot || !lbl) return;
            if (!liveEnabled) {
                dot.className = 'w-2 h-2 rounded-full bg-slate-500';
                lbl.innerText = 'Live Paused';
            } else {
                dot.className = 'w-2 h-2 rounded-full bg-emerald-500 animate-pulse';
                lbl.innerText = statusText || 'Live 5s';
            }
        }

        window.toggleLiveRefresh = function () {
            liveEnabled = !liveEnabled;
            localStorage.setItem('sapphire_live_5s', liveEnabled ? 'on' : 'off');
            updateLiveUI();
        };

        function isUserBusy() {
            if (document.hidden) return true;
            // Pause if any modal or mobile drawer is open
            const openOverlays = document.querySelectorAll('.fixed.inset-0:not(.hidden)');
            if (openOverlays.length > 0) return true;
            // Pause if user is typing or focused inside a form input/textarea/select
            const active = document.activeElement;
            if (active && ['INPUT', 'TEXTAREA', 'SELECT'].includes(active.tagName)) return true;
            return false;
        }

        function extractNormalizedSnapshot(doc) {
            const m = doc.querySelector('main');
            if (!m) return '';
            const clone = m.cloneNode(true);
            // Strip transient flash alert banners & modal forms so alerts don't trigger false refreshes
            clone.querySelectorAll('.fixed.inset-0, script, canvas, [class*="bg-emerald-500/10"], [class*="bg-rose-500/10"]').forEach(el => el.remove());
            const nav = doc.querySelector('aside nav');
            const navText = nav ? nav.textContent.replace(/\s+/g, ' ').trim() : '';
            return navText + '||' + clone.textContent.replace(/\s+/g, ' ').trim();
        }

        updateLiveUI();

        // Capture initial clean GET snapshot on load (so POST flash banners never cause immediate refresh)
        async function pollLiveUpdate() {
            if (!liveEnabled || isChecking || isUserBusy()) return;
            isChecking = true;
            try {
                const resp = await fetch(window.location.href, {
                    method: 'GET',
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: { 'X-Sapphire-Live': '1' }
                });
                if (!resp.ok || resp.redirected) return;
                const html = await resp.text();
                const parsed = new DOMParser().parseFromString(html, 'text/html');
                const snap = extractNormalizedSnapshot(parsed);
                if (!snap) return;

                if (baselineDigest === null) {
                    baselineDigest = snap;
                } else if (snap !== baselineDigest) {
                    // Live server data changed -> preserve scroll & cleanly refresh!
                    if (mainEl) {
                        sessionStorage.setItem(savedScrollKey, String(mainEl.scrollTop));
                    }
                    window.location.replace(window.location.href);
                    return;
                }

                const now = new Date();
                const hh = String(now.getHours()).padStart(2, '0');
                const mm = String(now.getMinutes()).padStart(2, '0');
                const ss = String(now.getSeconds()).padStart(2, '0');
                updateLiveUI(`Live 5s • ${hh}:${mm}:${ss}`);
            } catch (e) {
                // Silent network hiccup ignore
            } finally {
                isChecking = false;
            }
        }

        // Prime baseline after 1s, then check every 5 seconds
        setTimeout(pollLiveUpdate, 1000);
        setInterval(pollLiveUpdate, 5000);
    })();
</script>
</body>
</html>
