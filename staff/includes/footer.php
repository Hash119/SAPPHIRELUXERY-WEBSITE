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
            if (themeIcon) themeIcon.className = 'fa-solid fa-sun w-4 text-center text-amber-400';
            if (themeLabel) themeLabel.innerText = 'Light Theme';
            if (headerThemeIcon) headerThemeIcon.className = 'fa-solid fa-sun text-sm text-amber-400';
        } else {
            document.documentElement.classList.add('dark');
            if (themeIcon) themeIcon.className = 'fa-solid fa-moon w-4 text-center text-slate-300';
            if (themeLabel) themeLabel.innerText = 'Dark Theme';
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
</script>
</body>
</html>
