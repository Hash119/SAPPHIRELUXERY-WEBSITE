/**
 * Sapphire Luxury Aesthetics - Admin CMS & Management Script
 * Live CRUD, Image Uploads, LocalStorage Synchronization & Security Guard
 */

const DEFAULT_ADMIN_USER = 'drindi@85';
const DEFAULT_ADMIN_PASS = 'Sapphire@2026';

let currentAdminTab = 'overview';
let isCMSInitialized = false;

document.addEventListener('DOMContentLoaded', () => {
  initLucide();
  initAdminAuth();
});

function getAdminCredentials() {
  const saved = localStorage.getItem('sapphire_admin_creds');
  if (saved) {
    try {
      return JSON.parse(saved);
    } catch (e) {
      console.error(e);
    }
  }
  return { user: DEFAULT_ADMIN_USER, pass: DEFAULT_ADMIN_PASS };
}

function isAuthenticated() {
  return sessionStorage.getItem('sapphire_admin_auth') === 'true' || 
         localStorage.getItem('sapphire_admin_auth') === 'true';
}

function initAdminAuth() {
  const loginScreen = document.getElementById('admin-login-screen');
  const appLayout = document.getElementById('admin-app-layout');

  if (isAuthenticated()) {
    if (loginScreen) loginScreen.classList.add('hidden');
    if (appLayout) appLayout.classList.remove('hidden');
    bootstrapCMS();
  } else {
    if (loginScreen) loginScreen.classList.remove('hidden');
    if (appLayout) appLayout.classList.add('hidden');
  }
}

function bootstrapCMS() {
  if (isCMSInitialized) return;
  isCMSInitialized = true;
  
  initAdminNavigation();
  initImageUploaders();
  initFormSubmissions();
  refreshAllData();
  initLucide();
}

function handleAdminLogin(event) {
  if (event) event.preventDefault();

  const userEl = document.getElementById('login-username');
  const passEl = document.getElementById('login-password');
  const rememberEl = document.getElementById('login-remember');
  const errorMsg = document.getElementById('login-error-msg');
  const errorText = document.getElementById('login-error-text');
  const cardBox = document.getElementById('login-card-box');
  const submitBtn = document.getElementById('btn-login-submit');

  const enteredUser = userEl ? userEl.value.trim() : '';
  const enteredPass = passEl ? passEl.value : '';

  const creds = getAdminCredentials();

  if (enteredUser === creds.user && enteredPass === creds.pass) {
    // Authentication successful
    if (errorMsg) errorMsg.classList.add('hidden');

    if (submitBtn) {
      submitBtn.innerHTML = `
        <span class="inline-block w-4 h-4 border-2 border-slate-950 border-t-transparent rounded-full animate-spin"></span>
        <span>Authenticating...</span>
      `;
    }

    setTimeout(() => {
      const isRemember = rememberEl && rememberEl.checked;
      if (isRemember) {
        localStorage.setItem('sapphire_admin_auth', 'true');
      } else {
        sessionStorage.setItem('sapphire_admin_auth', 'true');
      }

      const loginScreen = document.getElementById('admin-login-screen');
      const appLayout = document.getElementById('admin-app-layout');

      if (loginScreen) loginScreen.classList.add('hidden');
      if (appLayout) appLayout.classList.remove('hidden');

      bootstrapCMS();
      showToast('Welcome Dr. Indi! Admin Portal unlocked successfully.');
      
      if (submitBtn) {
        submitBtn.innerHTML = `
          <i data-lucide="shield-check" class="w-4 h-4"></i>
          <span>Unlock Admin Portal</span>
        `;
        initLucide();
      }
    }, 400);

  } else {
    // Authentication failed
    if (errorMsg && errorText) {
      errorText.textContent = 'Invalid credentials. Please enter your correct username & password.';
      errorMsg.classList.remove('hidden');
    }
    if (cardBox) {
      cardBox.classList.remove('shake-animation');
      void cardBox.offsetWidth; // Trigger reflow
      cardBox.classList.add('shake-animation');
    }
    if (passEl) {
      passEl.value = '';
      passEl.focus();
    }
    initLucide();
  }
}

function handleAdminLogout() {
  if (!confirm('Are you sure you want to log out from Sapphire CMS?')) return;

  sessionStorage.removeItem('sapphire_admin_auth');
  localStorage.removeItem('sapphire_admin_auth');

  const appLayout = document.getElementById('admin-app-layout');
  const loginScreen = document.getElementById('admin-login-screen');
  const passEl = document.getElementById('login-password');

  if (appLayout) appLayout.classList.add('hidden');
  if (loginScreen) loginScreen.classList.remove('hidden');
  if (passEl) passEl.value = '';

  isCMSInitialized = false;
  initLucide();
  showToast('You have been logged out securely.');
}

function togglePasswordVisibility(inputId, iconId) {
  const input = document.getElementById(inputId);
  const icon = document.getElementById(iconId);
  if (!input) return;

  if (input.type === 'password') {
    input.type = 'text';
    if (icon) icon.setAttribute('data-lucide', 'eye-off');
  } else {
    input.type = 'password';
    if (icon) icon.setAttribute('data-lucide', 'eye');
  }
  initLucide();
}

function openChangePasswordModal() {
  const modal = document.getElementById('modal-change-password');
  const userCreds = getAdminCredentials();
  const newUserInput = document.getElementById('new-user-input');
  const alertEl = document.getElementById('pwd-change-alert');
  
  if (alertEl) alertEl.classList.add('hidden');
  if (newUserInput) newUserInput.value = userCreds.user;
  
  const form = document.getElementById('form-change-password');
  if (form) form.reset();
  if (newUserInput) newUserInput.value = userCreds.user;

  if (modal) modal.classList.remove('hidden');
  initLucide();
}

function closeChangePasswordModal() {
  const modal = document.getElementById('modal-change-password');
  if (modal) modal.classList.add('hidden');
}

function handleChangePasswordSubmit(event) {
  if (event) event.preventDefault();

  const currentPwd = document.getElementById('current-pwd-input').value;
  const newUser = document.getElementById('new-user-input').value.trim();
  const newPwd = document.getElementById('new-pwd-input').value;
  const confirmPwd = document.getElementById('confirm-new-pwd-input').value;
  const alertEl = document.getElementById('pwd-change-alert');

  const creds = getAdminCredentials();

  if (currentPwd !== creds.pass) {
    if (alertEl) {
      alertEl.textContent = 'Current password is incorrect!';
      alertEl.className = 'mb-4 p-3 rounded-xl text-xs font-medium bg-rose-100 text-rose-800 border border-rose-300 block';
    }
    return;
  }

  if (newPwd !== confirmPwd) {
    if (alertEl) {
      alertEl.textContent = 'New passwords do not match!';
      alertEl.className = 'mb-4 p-3 rounded-xl text-xs font-medium bg-rose-100 text-rose-800 border border-rose-300 block';
    }
    return;
  }

  if (newPwd.length < 6) {
    if (alertEl) {
      alertEl.textContent = 'Password must be at least 6 characters long!';
      alertEl.className = 'mb-4 p-3 rounded-xl text-xs font-medium bg-rose-100 text-rose-800 border border-rose-300 block';
    }
    return;
  }

  // Save new credentials
  localStorage.setItem('sapphire_admin_creds', JSON.stringify({
    user: newUser,
    pass: newPwd
  }));

  closeChangePasswordModal();
  showToast('Security credentials updated successfully!');
}

function initLucide() {
  if (window.lucide) {
    lucide.createIcons();
  }
}


/* -------------------------------------------------------------------------- */
/* 1. Navigation & Tab Switching                                             */
/* -------------------------------------------------------------------------- */
function initAdminNavigation() {
  const navBtns = document.querySelectorAll('.admin-nav-item');
  navBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      const tab = btn.getAttribute('data-tab');
      switchTab(tab);
    });
  });
}

function switchTab(tabName) {
  currentAdminTab = tabName;
  
  // Update nav buttons
  document.querySelectorAll('.admin-nav-item').forEach(btn => {
    if (btn.getAttribute('data-tab') === tabName) {
      btn.classList.add('active');
    } else {
      btn.classList.remove('active');
    }
  });

  // Update tab views
  document.querySelectorAll('.admin-tab-content').forEach(section => {
    section.classList.add('hidden');
  });

  const activeSection = document.getElementById(`tab-${tabName}`);
  if (activeSection) {
    activeSection.classList.remove('hidden');
  }

  // Update title
  const titles = {
    overview: "Dashboard Overview",
    treatments: "Medical Treatments & Procedure Photos",
    reels: "Clinical Reels & Video Stories Timeline",
    reviews: "Patient Reviews & Testimonials",
    products: "Skincare Boutique Product Catalog",
    bookings: "Consultation Bookings & Inquiries Log"
  };
  const titleEl = document.getElementById('tab-title');
  if (titleEl) titleEl.textContent = titles[tabName] || "Dashboard";

  refreshAllData();
}

/* -------------------------------------------------------------------------- */
/* 2. Refresh Data & Stats                                                   */
/* -------------------------------------------------------------------------- */
function refreshAllData() {
  const treatments = getStoredTreatments();
  const reels = typeof getStoredReels === 'function' ? getStoredReels() : [];
  const products = getStoredProducts();
  const reviews = getStoredReviews();
  const bookings = JSON.parse(localStorage.getItem('sapphire_bookings_log') || '[]');

  // Update KPIs
  const kpiBookings = document.getElementById('kpi-bookings-count');
  const kpiTreatments = document.getElementById('kpi-treatments-count');
  const kpiReels = document.getElementById('kpi-reels-count');
  const kpiProducts = document.getElementById('kpi-products-count');

  if (kpiBookings) kpiBookings.textContent = bookings.length;
  if (kpiTreatments) kpiTreatments.textContent = treatments.length;
  if (kpiReels) kpiReels.textContent = reels.length;
  if (kpiProducts) kpiProducts.textContent = products.length;

  renderOverviewRecentBookings(bookings);
  renderTreatmentsTable(treatments);
  renderAdminReels(reels);
  renderReviewsTable(reviews);
  renderProductsTable(products);
  renderBookingsTable(bookings);

  initLucide();
}

/* -------------------------------------------------------------------------- */
/* 3. Image & Video Uploaders                                                */
/* -------------------------------------------------------------------------- */
function initImageUploaders() {
  // Treatment Image File
  const tFile = document.getElementById('t-form-file');
  const tUrl = document.getElementById('t-form-image-url');
  const tPreview = document.getElementById('t-img-preview-tag');
  const tUploadText = document.getElementById('t-upload-preview');

  if (tFile) {
    tFile.addEventListener('change', (e) => {
      const file = e.target.files[0];
      if (file) {
        const reader = new FileReader();
        reader.onload = (event) => {
          const base64 = event.target.result;
          tUrl.value = base64;
          tPreview.src = base64;
          tPreview.classList.remove('hidden');
          if (tUploadText) tUploadText.classList.add('hidden');
        };
        reader.readAsDataURL(file);
      }
    });
  }

  // Reel Video File Uploader
  const reelFile = document.getElementById('reel-form-file');
  const reelUrl = document.getElementById('reel-form-video-url');
  const reelVideoPreview = document.getElementById('reel-video-preview-tag');
  const reelUploadText = document.getElementById('reel-upload-preview');

  if (reelFile) {
    reelFile.addEventListener('change', (e) => {
      const file = e.target.files[0];
      if (file) {
        // If file is smaller than 20MB, read as DataURL, otherwise use object URL or path
        const reader = new FileReader();
        reader.onload = (event) => {
          const base64 = event.target.result;
          reelUrl.value = base64;
          reelVideoPreview.src = base64;
          reelVideoPreview.classList.remove('hidden');
          if (reelUploadText) reelUploadText.classList.add('hidden');
        };
        reader.readAsDataURL(file);
      }
    });
  }

  // Reel Poster Image File Uploader
  const reelPosterFile = document.getElementById('reel-poster-file');
  const reelPosterUrl = document.getElementById('reel-form-poster-url');
  const reelPosterPreview = document.getElementById('reel-poster-preview-tag');
  const reelPosterText = document.getElementById('reel-poster-preview');

  if (reelPosterFile) {
    reelPosterFile.addEventListener('change', (e) => {
      const file = e.target.files[0];
      if (file) {
        const reader = new FileReader();
        reader.onload = (event) => {
          const base64 = event.target.result;
          reelPosterUrl.value = base64;
          reelPosterPreview.src = base64;
          reelPosterPreview.classList.remove('hidden');
          if (reelPosterText) reelPosterText.classList.add('hidden');
        };
        reader.readAsDataURL(file);
      }
    });
  }

  // Product Image File
  const pFile = document.getElementById('p-form-file');
  const pUrl = document.getElementById('p-form-image-url');
  const pPreview = document.getElementById('p-img-preview-tag');
  const pUploadText = document.getElementById('p-upload-preview');

  if (pFile) {
    pFile.addEventListener('change', (e) => {
      const file = e.target.files[0];
      if (file) {
        const reader = new FileReader();
        reader.onload = (event) => {
          const base64 = event.target.result;
          pUrl.value = base64;
          pPreview.src = base64;
          pPreview.classList.remove('hidden');
          if (pUploadText) pUploadText.classList.add('hidden');
        };
        reader.readAsDataURL(file);
      }
    });
  }
}

/* -------------------------------------------------------------------------- */
/* 4. Treatments CRUD Operations                                             */
/* -------------------------------------------------------------------------- */
function renderTreatmentsTable(treatments) {
  const tbody = document.getElementById('treatments-table-body');
  if (!tbody) return;

  tbody.innerHTML = '';
  treatments.forEach(t => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>
        <img src="${t.image}" alt="${t.name}" class="w-12 h-12 rounded-xl object-cover border border-gold-400/30" onerror="this.onerror=null; this.src='Photos/treatments/all treatments.jpg'">
      </td>
      <td>
        <span class="font-serif font-bold text-slate-900 block">${t.name}</span>
        <span class="text-[11px] text-slate-500 line-clamp-1">${t.shortDesc}</span>
      </td>
      <td>
        <span class="text-xs uppercase tracking-wider font-semibold text-gold-700 bg-cream-200 px-2 py-0.5 rounded-full">${t.category || 'skin'}</span>
      </td>
      <td class="text-xs">
        <div><strong>Time:</strong> ${t.duration || '45 Mins'}</div>
        <div><strong>Downtime:</strong> ${t.downtime || 'Zero'}</div>
      </td>
      <td class="text-xs font-semibold text-slate-900">
        ${t.pricingEstimate || 'Customized Plan'}
      </td>
      <td>
        <div class="flex items-center gap-2">
          <button onclick="editTreatment('${t.id}')" class="p-1.5 rounded-lg bg-slate-100 hover:bg-gold-500 hover:text-white transition-colors text-xs" title="Edit">
            <i data-lucide="edit-3" class="w-4 h-4"></i>
          </button>
          <button onclick="deleteTreatment('${t.id}')" class="p-1.5 rounded-lg bg-red-50 hover:bg-red-500 hover:text-white text-red-600 transition-colors text-xs" title="Delete">
            <i data-lucide="trash-2" class="w-4 h-4"></i>
          </button>
        </div>
      </td>
    `;
    tbody.appendChild(tr);
  });
}

function openTreatmentModalForm() {
  document.getElementById('treatment-crud-form').reset();
  document.getElementById('t-form-id').value = '';
  document.getElementById('t-img-preview-tag').classList.add('hidden');
  document.getElementById('t-upload-preview').classList.remove('hidden');
  document.getElementById('treatment-form-title').textContent = 'Add Medical Treatment';
  document.getElementById('modal-treatment-form').classList.remove('hidden');
  initLucide();
}

function closeTreatmentModalForm() {
  document.getElementById('modal-treatment-form').classList.add('hidden');
}

function editTreatment(id) {
  const treatments = getStoredTreatments();
  const t = treatments.find(item => item.id === id);
  if (!t) return;

  document.getElementById('t-form-id').value = t.id;
  document.getElementById('t-form-name').value = t.name;
  document.getElementById('t-form-category').value = t.category || 'skin';
  document.getElementById('t-form-tag').value = t.tag || '';
  document.getElementById('t-form-image-url').value = t.image || '';
  document.getElementById('t-form-duration').value = t.duration || '';
  document.getElementById('t-form-downtime').value = t.downtime || '';
  document.getElementById('t-form-shortdesc').value = t.shortDesc || '';
  document.getElementById('t-form-fulldesc').value = t.fullDesc || '';

  if (t.image) {
    const preview = document.getElementById('t-img-preview-tag');
    preview.src = t.image;
    preview.classList.remove('hidden');
    document.getElementById('t-upload-preview').classList.add('hidden');
  }

  document.getElementById('treatment-form-title').textContent = 'Edit Medical Treatment';
  document.getElementById('modal-treatment-form').classList.remove('hidden');
  initLucide();
}

function deleteTreatment(id) {
  if (!confirm('Are you sure you want to remove this medical treatment from the website?')) return;

  let treatments = getStoredTreatments();
  treatments = treatments.filter(t => t.id !== id);
  saveStoredTreatments(treatments);
  showToast('Treatment deleted and removed from live website');
  refreshAllData();
}

/* -------------------------------------------------------------------------- */
/* 5. Products CRUD Operations                                               */
/* -------------------------------------------------------------------------- */
function renderProductsTable(products) {
  const tbody = document.getElementById('products-table-body');
  if (!tbody) return;

  tbody.innerHTML = '';
  products.forEach(p => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>
        <img src="${p.image}" alt="${p.name}" class="w-12 h-12 rounded-xl object-cover border border-gold-400/30" onerror="this.onerror=null; this.src='Photos/treatments/all treatments.jpg'">
      </td>
      <td>
        <span class="font-serif font-bold text-slate-900 block">${p.name}</span>
        <span class="text-[11px] text-slate-500">${p.volume || ''}</span>
      </td>
      <td>
        <span class="text-xs uppercase tracking-wider font-semibold text-gold-700 bg-cream-200 px-2 py-0.5 rounded-full">${p.category}</span>
      </td>
      <td class="text-xs font-bold text-slate-900">
        ${p.priceFormatted || 'LKR ' + p.price.toLocaleString()}
      </td>
      <td class="text-xs font-semibold ${p.stockCount > 0 ? 'text-emerald-700' : 'text-red-600'}">
        ${p.stockCount || 20} in stock
      </td>
      <td>
        <div class="flex items-center gap-2">
          <button onclick="editProduct('${p.id}')" class="p-1.5 rounded-lg bg-slate-100 hover:bg-gold-500 hover:text-white transition-colors text-xs" title="Edit">
            <i data-lucide="edit-3" class="w-4 h-4"></i>
          </button>
          <button onclick="deleteProduct('${p.id}')" class="p-1.5 rounded-lg bg-red-50 hover:bg-red-500 hover:text-white text-red-600 transition-colors text-xs" title="Delete">
            <i data-lucide="trash-2" class="w-4 h-4"></i>
          </button>
        </div>
      </td>
    `;
    tbody.appendChild(tr);
  });
}

function openProductModalForm() {
  document.getElementById('product-crud-form').reset();
  document.getElementById('p-form-id').value = '';
  document.getElementById('p-img-preview-tag').classList.add('hidden');
  document.getElementById('p-upload-preview').classList.remove('hidden');
  document.getElementById('product-form-title').textContent = 'Add Skincare Product';
  document.getElementById('modal-product-form').classList.remove('hidden');
  initLucide();
}

function closeProductModalForm() {
  document.getElementById('modal-product-form').classList.add('hidden');
}

function editProduct(id) {
  const products = getStoredProducts();
  const p = products.find(item => item.id === id);
  if (!p) return;

  document.getElementById('p-form-id').value = p.id;
  document.getElementById('p-form-name').value = p.name;
  document.getElementById('p-form-category').value = p.category;
  document.getElementById('p-form-price').value = p.price;
  document.getElementById('p-form-image-url').value = p.image || '';
  document.getElementById('p-form-volume').value = p.volume || '';
  document.getElementById('p-form-stock').value = p.stockCount || 20;
  document.getElementById('p-form-shortdesc').value = p.shortDesc || '';

  if (p.image) {
    const preview = document.getElementById('p-img-preview-tag');
    preview.src = p.image;
    preview.classList.remove('hidden');
    document.getElementById('p-upload-preview').classList.add('hidden');
  }

  document.getElementById('product-form-title').textContent = 'Edit Skincare Product';
  document.getElementById('modal-product-form').classList.remove('hidden');
  initLucide();
}

function deleteProduct(id) {
  if (!confirm('Are you sure you want to delete this product from the boutique?')) return;

  let products = getStoredProducts();
  products = products.filter(p => p.id !== id);
  saveStoredProducts(products);
  showToast('Product removed from Skincare Boutique');
  refreshAllData();
}

/* -------------------------------------------------------------------------- */
/* 6. Reviews CRUD Operations                                                */
/* -------------------------------------------------------------------------- */
function renderReviewsTable(reviews) {
  const tbody = document.getElementById('reviews-table-body');
  if (!tbody) return;

  tbody.innerHTML = '';
  reviews.forEach(r => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>
        <span class="font-serif font-bold text-slate-900 block">${r.clientName}</span>
        <span class="text-[11px] text-slate-500">${r.location || 'Colombo'}</span>
      </td>
      <td>
        <span class="text-xs text-gold-700 font-semibold">${r.treatment}</span>
      </td>
      <td>
        <div class="flex text-gold-500 text-xs">
          ${'★'.repeat(r.rating || 5)}
        </div>
      </td>
      <td class="text-xs text-slate-600 line-clamp-2 max-w-xs font-light">
        "${r.quote}"
      </td>
      <td>
        <button onclick="deleteReview('${r.id}')" class="p-1.5 rounded-lg bg-red-50 hover:bg-red-500 hover:text-white text-red-600 transition-colors text-xs" title="Delete">
          <i data-lucide="trash-2" class="w-4 h-4"></i>
        </button>
      </td>
    `;
    tbody.appendChild(tr);
  });
}

function openReviewModalForm() {
  document.getElementById('review-crud-form').reset();
  document.getElementById('modal-review-form').classList.remove('hidden');
  initLucide();
}

function closeReviewModalForm() {
  document.getElementById('modal-review-form').classList.add('hidden');
}

function deleteReview(id) {
  if (!confirm('Remove this review from the homepage carousel?')) return;

  let reviews = getStoredReviews();
  reviews = reviews.filter(r => r.id !== id);
  saveStoredReviews(reviews);
  showToast('Review removed');
  refreshAllData();
}

/* -------------------------------------------------------------------------- */
/* 7. Bookings & Inquiries Management                                        */
/* -------------------------------------------------------------------------- */
function renderBookingsTable(bookings) {
  const tbody = document.getElementById('bookings-table-body');
  if (!tbody) return;

  tbody.innerHTML = '';
  if (bookings.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="7" class="text-center py-8 text-slate-400 text-xs">
          No appointments or inquiries logged yet. Test by submitting a booking on the main website!
        </td>
      </tr>
    `;
    return;
  }

  bookings.forEach(b => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td class="font-mono text-xs font-bold text-gold-700">${b.id}</td>
      <td>
        <span class="font-bold text-slate-900 block text-xs">${b.name}</span>
        <span class="text-[11px] text-slate-500">${b.phone}</span>
      </td>
      <td class="text-xs font-medium text-slate-800">${b.treatment}</td>
      <td class="text-xs text-slate-600">${b.date} at ${b.time}</td>
      <td class="text-xs text-slate-600">${b.doctor || 'Dr. Indi'}</td>
      <td>
        <select onchange="updateBookingStatus('${b.id}', this.value)" class="text-xs px-2.5 py-1 rounded-full font-bold ${b.status === 'Confirmed' ? 'bg-emerald-100 text-emerald-800' : (b.status === 'Completed' ? 'bg-blue-100 text-blue-800' : 'bg-amber-100 text-amber-800')}">
          <option value="Pending" ${b.status === 'Pending' ? 'selected' : ''}>Pending</option>
          <option value="Confirmed" ${b.status === 'Confirmed' ? 'selected' : ''}>Confirmed</option>
          <option value="Completed" ${b.status === 'Completed' ? 'selected' : ''}>Completed</option>
        </select>
      </td>
      <td>
        <a href="https://wa.me/${(b.phone || '').replace(/[^0-9]/g, '')}?text=Hi%20${encodeURIComponent(b.name)},%20this%20is%20Sapphire%20Luxury%20Aesthetics%20regarding%20your%20consultation%20(Ref:%20${b.id})." target="_blank" class="p-1.5 rounded-lg bg-emerald-50 hover:bg-emerald-600 hover:text-white text-emerald-700 transition-colors text-xs inline-flex items-center gap-1" title="Chat on WhatsApp">
          <i data-lucide="message-circle" class="w-4 h-4"></i>
        </a>
      </td>
    `;
    tbody.appendChild(tr);
  });
}

function renderOverviewRecentBookings(bookings) {
  const tbody = document.getElementById('overview-recent-bookings-body');
  if (!tbody) return;

  tbody.innerHTML = '';
  const recent = bookings.slice(0, 5);

  if (recent.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="6" class="text-center py-6 text-slate-400 text-xs">
          No recent bookings yet.
        </td>
      </tr>
    `;
    return;
  }

  recent.forEach(b => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td class="font-mono text-xs font-bold text-gold-700">${b.id}</td>
      <td class="text-xs font-bold text-slate-900">${b.name}</td>
      <td class="text-xs text-slate-700">${b.treatment}</td>
      <td class="text-xs text-slate-500">${b.date} at ${b.time}</td>
      <td>
        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full ${b.status === 'Confirmed' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'}">
          ${b.status || 'Pending'}
        </span>
      </td>
      <td>
        <button onclick="switchTab('bookings')" class="text-xs text-gold-700 font-bold hover:underline">
          Manage
        </button>
      </td>
    `;
    tbody.appendChild(tr);
  });
}

function updateBookingStatus(id, newStatus) {
  let bookings = JSON.parse(localStorage.getItem('sapphire_bookings_log') || '[]');
  const found = bookings.find(b => b.id === id);
  if (found) {
    found.status = newStatus;
    localStorage.setItem('sapphire_bookings_log', JSON.stringify(bookings));
    showToast(`Booking ${id} marked as ${newStatus}`);
    refreshAllData();
  }
}

function clearBookingsLog() {
  if (!confirm('Clear all completed consultation records?')) return;
  let bookings = JSON.parse(localStorage.getItem('sapphire_bookings_log') || '[]');
  bookings = bookings.filter(b => b.status === 'Pending');
  localStorage.setItem('sapphire_bookings_log', JSON.stringify(bookings));
  showToast('Completed bookings cleared');
  refreshAllData();
}

/* -------------------------------------------------------------------------- */
/* 8. Form Submissions                                                       */
/* -------------------------------------------------------------------------- */
function initFormSubmissions() {
  // Treatment Form Submit
  const tForm = document.getElementById('treatment-crud-form');
  if (tForm) {
    tForm.addEventListener('submit', (e) => {
      e.preventDefault();
      const id = document.getElementById('t-form-id').value || ('t-' + Date.now());
      const name = document.getElementById('t-form-name').value.trim();
      const category = document.getElementById('t-form-category').value;
      const tag = document.getElementById('t-form-tag').value.trim() || 'Clinical Excellence';
      const image = document.getElementById('t-form-image-url').value.trim() || 'Photos/treatments/all treatments.jpg';
      const duration = document.getElementById('t-form-duration').value.trim() || '45 - 60 Mins';
      const downtime = document.getElementById('t-form-downtime').value.trim() || 'Zero Downtime';
      const shortDesc = document.getElementById('t-form-shortdesc').value.trim();
      const fullDesc = document.getElementById('t-form-fulldesc').value.trim() || shortDesc;

      const categoryNames = {
        skin: "Skin & Facial Aesthetics",
        laser: "Laser Aesthetics",
        hair: "Hair Restoration",
        "anti-aging": "Anti-Aging & Injectables",
        body: "Wellness & IV Therapy"
      };

      const payload = {
        id,
        name,
        category,
        categoryName: categoryNames[category] || "Medical Cosmetology",
        tag,
        duration,
        downtime,
        image,
        shortDesc,
        fullDesc,
        doctor: "Dr. Indi & Aesthetic Physicians",
        pricingEstimate: "Customized Clinical Plan"
      };

      let treatments = getStoredTreatments();
      const index = treatments.findIndex(t => t.id === id);
      if (index !== -1) {
        treatments[index] = payload;
        showToast('Treatment updated successfully!');
      } else {
        treatments.unshift(payload);
        showToast('New medical treatment published to website!');
      }

      saveStoredTreatments(treatments);
      closeTreatmentModalForm();
      refreshAllData();
    });
  }

  // Product Form Submit
  const pForm = document.getElementById('product-crud-form');
  if (pForm) {
    pForm.addEventListener('submit', (e) => {
      e.preventDefault();
      const id = document.getElementById('p-form-id').value || ('prod-' + Date.now());
      const name = document.getElementById('p-form-name').value.trim();
      const category = document.getElementById('p-form-category').value;
      const price = parseFloat(document.getElementById('p-form-price').value) || 10000;
      const image = document.getElementById('p-form-image-url').value.trim() || 'Photos/treatments/all treatments.jpg';
      const volume = document.getElementById('p-form-volume').value.trim() || '50ml';
      const stockCount = parseInt(document.getElementById('p-form-stock').value) || 20;
      const shortDesc = document.getElementById('p-form-shortdesc').value.trim();

      const catNames = {
        brightening: "Brightening & Pigmentation",
        acne: "Acne & Oil Control",
        "sun-protection": "Sun Protection & Barrier",
        hair: "Hair & Scalp Restoration",
        "anti-aging": "Anti-Aging & Rejuvenation"
      };

      const payload = {
        id,
        name,
        category,
        categoryName: catNames[category] || "Medical Cosmeceutical",
        tag: "Physician Prescribed",
        price,
        priceFormatted: `LKR ${price.toLocaleString()}`,
        volume,
        image,
        inStock: stockCount > 0,
        stockCount,
        rating: 5.0,
        shortDesc,
        fullDesc: shortDesc,
        howToUse: "Apply onto clean skin as advised by physician."
      };

      let products = getStoredProducts();
      const index = products.findIndex(p => p.id === id);
      if (index !== -1) {
        products[index] = payload;
        showToast('Product updated successfully!');
      } else {
        products.unshift(payload);
        showToast('New product added to Skincare Boutique!');
      }

      saveStoredProducts(products);
      closeProductModalForm();
      refreshAllData();
    });
  }

  // Review Form Submit
  const rForm = document.getElementById('review-crud-form');
  if (rForm) {
    rForm.addEventListener('submit', (e) => {
      e.preventDefault();
      const id = 'rev-' + Date.now();
      const clientName = document.getElementById('r-form-name').value.trim();
      const location = document.getElementById('r-form-location').value.trim() || 'Colombo';
      const treatment = document.getElementById('r-form-treatment').value.trim();
      const rating = parseInt(document.getElementById('r-form-rating').value) || 5;
      const quote = document.getElementById('r-form-quote').value.trim();

      const payload = {
        id,
        clientName,
        location,
        treatment,
        rating,
        date: "Just now",
        verified: true,
        quote
      };

      let reviews = getStoredReviews();
      reviews.unshift(payload);
      saveStoredReviews(reviews);
      showToast('Verified review added to homepage!');
      closeReviewModalForm();
      refreshAllData();
    });
  }
}

/* -------------------------------------------------------------------------- */
/* 5. Clinical Reels CRUD Operations                                         */
/* -------------------------------------------------------------------------- */
function renderAdminReels(reels) {
  const grid = document.getElementById('admin-reels-grid');
  if (!grid) return;

  grid.innerHTML = '';
  if (!reels || reels.length === 0) {
    grid.innerHTML = `
      <div class="col-span-full py-12 text-center bg-white rounded-2xl border border-slate-200">
        <i data-lucide="video-off" class="w-10 h-10 text-slate-400 mx-auto mb-2"></i>
        <h4 class="font-serif text-lg font-bold text-slate-800">No Video Reels Published Yet</h4>
        <p class="text-xs text-slate-500 mb-4">Click "+ Upload Video Reel" above to add your first clinical procedure video.</p>
        <button onclick="openReelModalForm();" class="btn-luxury-sapphire px-4 py-2 text-xs">
          + Add First Video Reel
        </button>
      </div>
    `;
    initLucide();
    return;
  }

  reels.forEach(reel => {
    const card = document.createElement('div');
    card.className = 'admin-reel-card';
    card.innerHTML = `
      <div class="admin-reel-video-container group">
        <video src="${reel.videoUrl}" poster="${reel.poster || 'Photos/Logo.jpg'}" preload="metadata" muted playsinline class="w-full h-full object-cover" onmouseenter="this.play().catch(()=>{})" onmouseleave="this.pause()"></video>
        <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-black/30 pointer-events-none"></div>
        <div class="absolute top-3 left-3">
          <span class="text-[10px] uppercase font-bold px-2 py-0.5 rounded-full bg-indigo-600 text-white shadow-sm">
            ${reel.category || 'CLINICAL REEL'}
          </span>
        </div>
        <div class="absolute top-3 right-3">
          <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-black/60 text-gold-300 backdrop-blur-md">
            ${reel.tag || 'LIVE REEL'}
          </span>
        </div>
        <div class="absolute bottom-3 left-3 right-3 text-white">
          <h4 class="font-serif text-sm font-bold line-clamp-1 leading-snug">${reel.title}</h4>
          <p class="text-[11px] text-slate-200 line-clamp-1 mt-0.5 font-light">${reel.description}</p>
        </div>
      </div>

      <div class="p-4 bg-white flex items-center justify-between gap-2 border-t border-slate-100 mt-auto">
        <span class="text-[11px] text-slate-500 font-medium flex items-center gap-1">
          <i data-lucide="play" class="w-3.5 h-3.5 text-gold-600"></i>
          <span>Hover to preview</span>
        </span>
        <div class="flex items-center gap-1.5">
          <button onclick="openReelModalForm('${reel.id}');" class="p-1.5 rounded-lg text-slate-600 hover:text-gold-700 hover:bg-cream-100 transition-colors" title="Edit Reel">
            <i data-lucide="edit-3" class="w-4 h-4"></i>
          </button>
          <button onclick="deleteReel('${reel.id}');" class="p-1.5 rounded-lg text-rose-600 hover:bg-rose-50 transition-colors" title="Delete Reel">
            <i data-lucide="trash-2" class="w-4 h-4"></i>
          </button>
        </div>
      </div>
    `;
    grid.appendChild(card);
  });

  initLucide();
}

function openReelModalForm(reelId = null) {
  const modal = document.getElementById('modal-reel-form');
  const titleEl = document.getElementById('reel-form-title');
  const form = document.getElementById('reel-crud-form');
  const idEl = document.getElementById('reel-form-id');
  const nameEl = document.getElementById('reel-form-name');
  const catEl = document.getElementById('reel-form-category');
  const tagEl = document.getElementById('reel-form-tag');
  const videoUrlEl = document.getElementById('reel-form-video-url');
  const videoPreview = document.getElementById('reel-video-preview-tag');
  const uploadPreview = document.getElementById('reel-upload-preview');
  const posterUrlEl = document.getElementById('reel-form-poster-url');
  const posterPreview = document.getElementById('reel-poster-preview-tag');
  const posterUploadPreview = document.getElementById('reel-poster-preview');
  const descEl = document.getElementById('reel-form-desc');

  if (form) form.reset();

  if (reelId) {
    const reels = getStoredReels();
    const reel = reels.find(r => r.id === reelId);
    if (reel) {
      if (titleEl) titleEl.textContent = 'Edit Clinical Video Reel';
      if (idEl) idEl.value = reel.id;
      if (nameEl) nameEl.value = reel.title || '';
      if (catEl) catEl.value = reel.category || 'CLINICAL VLOG';
      if (tagEl) tagEl.value = reel.tag || 'LIVE PROCEDURE REEL';
      if (videoUrlEl) videoUrlEl.value = reel.videoUrl || '';
      if (posterUrlEl) posterUrlEl.value = reel.poster || '';
      if (descEl) descEl.value = reel.description || '';

      if (videoPreview && reel.videoUrl) {
        videoPreview.src = reel.videoUrl;
        videoPreview.classList.remove('hidden');
        if (uploadPreview) uploadPreview.classList.add('hidden');
      }
      if (posterPreview && reel.poster) {
        posterPreview.src = reel.poster;
        posterPreview.classList.remove('hidden');
        if (posterUploadPreview) posterUploadPreview.classList.add('hidden');
      }
    }
  } else {
    if (titleEl) titleEl.textContent = 'Add Clinical Video Reel';
    if (idEl) idEl.value = '';
    if (videoPreview) {
      videoPreview.src = '';
      videoPreview.classList.add('hidden');
    }
    if (uploadPreview) uploadPreview.classList.remove('hidden');
    if (posterPreview) {
      posterPreview.src = '';
      posterPreview.classList.add('hidden');
    }
    if (posterUploadPreview) posterUploadPreview.classList.remove('hidden');
  }

  if (modal) modal.classList.remove('hidden');
  initLucide();
}

function closeReelModalForm() {
  const modal = document.getElementById('modal-reel-form');
  if (modal) modal.classList.add('hidden');
}

function handleReelFormSubmit(event) {
  if (event) event.preventDefault();

  const id = document.getElementById('reel-form-id').value || ('reel-' + Date.now());
  const title = document.getElementById('reel-form-name').value.trim();
  const category = document.getElementById('reel-form-category').value;
  const tag = document.getElementById('reel-form-tag').value.trim() || 'LIVE PROCEDURE REEL';
  const videoUrl = document.getElementById('reel-form-video-url').value.trim() || 'Video/clinic-reel.mp4';
  const poster = document.getElementById('reel-form-poster-url').value.trim() || 'Photos/Logo.jpg';
  const description = document.getElementById('reel-form-desc').value.trim();

  const payload = {
    id,
    title,
    category,
    tag,
    videoUrl,
    poster,
    description,
    views: "1.5K",
    featured: true
  };

  let reels = getStoredReels();
  const index = reels.findIndex(r => r.id === id);
  if (index !== -1) {
    reels[index] = payload;
    showToast('Video reel updated successfully!');
  } else {
    reels.unshift(payload);
    showToast('New clinical video reel published to homepage!');
  }

  saveStoredReels(reels);
  closeReelModalForm();
  refreshAllData();
}

function deleteReel(reelId) {
  if (!confirm('Are you sure you want to delete this clinical video reel?')) return;

  let reels = getStoredReels();
  reels = reels.filter(r => r.id !== reelId);
  saveStoredReels(reels);
  showToast('Video reel deleted successfully');
  refreshAllData();
}

/* -------------------------------------------------------------------------- */
/* 9. Reset System Data to Defaults                                          */
/* -------------------------------------------------------------------------- */
function resetAllDataToDefaults() {
  if (!confirm('Reset all treatments, reels, boutique products, reviews, and bookings to default factory data?')) return;

  localStorage.removeItem('sapphire_treatments_data');
  localStorage.removeItem('sapphire_reels_data');
  localStorage.removeItem('sapphire_products_data');
  localStorage.removeItem('sapphire_reviews_data');
  localStorage.removeItem('sapphire_bookings_log');

  showToast('All data reset to initial clinic defaults');
  refreshAllData();
}

/* -------------------------------------------------------------------------- */
/* 10. Toast Notification System                                             */
/* -------------------------------------------------------------------------- */
function showToast(message) {
  const container = document.getElementById('admin-toast-container');
  if (!container) return;

  const toast = document.createElement('div');
  toast.className = 'admin-toast';
  toast.innerHTML = `
    <i data-lucide="check-circle" class="w-5 h-5 text-emerald-400 shrink-0"></i>
    <span>${message}</span>
  `;
  container.appendChild(toast);
  initLucide();

  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transform = 'translateY(10px)';
    toast.style.transition = 'all 0.3s ease';
    setTimeout(() => toast.remove(), 300);
  }, 3500);
}

// Explicit global window bindings for inline HTML onclick handlers
window.openReelModalForm = openReelModalForm;
window.closeReelModalForm = closeReelModalForm;
window.handleReelFormSubmit = handleReelFormSubmit;
window.deleteReel = deleteReel;
window.openTreatmentModalForm = openTreatmentModalForm;
window.closeTreatmentModalForm = closeTreatmentModalForm;
window.openProductModalForm = openProductModalForm;
window.closeProductModalForm = closeProductModalForm;
window.openReviewModalForm = openReviewModalForm;
window.closeReviewModalForm = closeReviewModalForm;
window.openChangePasswordModal = openChangePasswordModal;
window.closeChangePasswordModal = closeChangePasswordModal;
window.handleChangePasswordSubmit = handleChangePasswordSubmit;
window.handleAdminLogin = handleAdminLogin;
window.handleAdminLogout = handleAdminLogout;
window.togglePasswordVisibility = togglePasswordVisibility;
window.resetAllDataToDefaults = resetAllDataToDefaults;
window.switchTab = switchTab;
window.showToast = showToast;

