/**
 * Sapphire Luxury Aesthetics - Core Application Script
 * Colombo 05, Sri Lanka | Inspired by World-Class Aesthetic Clinics
 */

document.addEventListener('DOMContentLoaded', () => {
  // Initialize all interactive modules
  initLucide();
  initNavigation();
  initTreatmentsGrid();
  initHomeFeaturedProducts();
  initTreatmentModal();
  initSkinAdvisorQuiz();
  initBookingModal();
  initComparisonSlider();
  initTestimonialsSlider();
  initFaqAccordion();
  initContactForm();
  initAos();
});

/* -------------------------------------------------------------------------- */
/* 1. Initialize Icons & AOS                                                  */
/* -------------------------------------------------------------------------- */
function initLucide() {
  if (window.lucide) {
    lucide.createIcons();
  }
}

function initAos() {
  if (window.AOS) {
    AOS.init({
      duration: 800,
      easing: 'ease-out-cubic',
      once: true,
      offset: 50
    });
  }
}

/* -------------------------------------------------------------------------- */
/* 2. Navigation & Mobile Menu                                               */
/* -------------------------------------------------------------------------- */
function initNavigation() {
  const header = document.getElementById('main-header');
  const mobileMenuBtn = document.getElementById('mobile-menu-btn');
  const mobileMenu = document.getElementById('mobile-menu');
  const closeMobileMenuBtn = document.getElementById('close-mobile-menu');
  const mobileLinks = document.querySelectorAll('.mobile-nav-link');

  // Scroll effect for header glassmorphism
  window.addEventListener('scroll', () => {
    if (window.scrollY > 30) {
      header.classList.add('bg-white/95', 'backdrop-blur-md', 'py-3', 'shadow-md', 'border-b', 'border-gold-400/30');
      header.classList.remove('py-4');
    } else {
      header.classList.remove('bg-white/95', 'backdrop-blur-md', 'py-3', 'shadow-md', 'border-b', 'border-gold-400/30');
      header.classList.add('py-4');
    }
  });

  // Mobile drawer toggle
  if (mobileMenuBtn && mobileMenu) {
    mobileMenuBtn.addEventListener('click', () => {
      mobileMenu.classList.remove('translate-x-full');
      document.body.classList.add('overflow-hidden');
    });

    const closeMenu = () => {
      mobileMenu.classList.add('translate-x-full');
      document.body.classList.remove('overflow-hidden');
    };

    if (closeMobileMenuBtn) {
      closeMobileMenuBtn.addEventListener('click', closeMenu);
    }

    mobileLinks.forEach(link => {
      link.addEventListener('click', closeMenu);
    });
  }
}

/* -------------------------------------------------------------------------- */
/* 3. Treatments Showcase & Category Filter                                    */
/* -------------------------------------------------------------------------- */
function initTreatmentsGrid() {
  const container = document.getElementById('treatments-grid');
  const filterButtons = document.querySelectorAll('.treatment-filter-btn');

  if (!container) return;

  const treatments = typeof getStoredTreatments === 'function' ? getStoredTreatments() : TREATMENTS_DATA;

  const renderCards = (filter = 'all') => {
    container.innerHTML = '';
    const filtered = filter === 'all' 
      ? treatments 
      : treatments.filter(t => t.category === filter);

    filtered.forEach((treatment, index) => {
      const card = document.createElement('div');
      card.className = 'glass-cream-card rounded-2xl overflow-hidden flex flex-col group';
      card.setAttribute('data-aos', 'fade-up');
      card.setAttribute('data-aos-delay', `${(index % 3) * 100}`);

      card.innerHTML = `
        <div class="treatment-img-wrap h-60 w-full relative overflow-hidden bg-slate-900">
          <img src="${treatment.image}" alt="${treatment.name}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700" loading="lazy" onerror="this.onerror=null; this.src='Photos/treatments/all treatments.jpg'">
          <div class="absolute top-4 left-4 z-10">
            <span class="treatment-badge text-xs font-semibold px-3 py-1 rounded-full uppercase tracking-wider shadow-sm bg-white/90 text-gold-700 border border-gold-400/30 backdrop-blur-sm">
              ${treatment.tag || 'Clinical Excellence'}
            </span>
          </div>
          <div class="absolute bottom-3 right-4 z-10 flex items-center gap-1.5 text-xs text-sapphire-900 bg-white/90 px-3 py-1 rounded-md backdrop-blur-sm border border-gold-400/30 font-medium shadow-sm">
            <i data-lucide="clock" class="w-3.5 h-3.5 text-gold-600"></i>
            <span>${treatment.duration || '45 Mins'}</span>
          </div>
        </div>

        <div class="p-6 flex-1 flex flex-col justify-between">
          <div>
            <div class="text-xs uppercase font-semibold tracking-widest text-gold-600 mb-1.5">
              ${treatment.categoryName || 'Medical Cosmetology'}
            </div>
            <h3 class="font-serif text-xl font-bold text-slate-900 mb-2.5 group-hover:text-gold-700 transition-colors">
              ${treatment.name}
            </h3>
            <p class="text-slate-600 text-sm line-clamp-2 mb-4 leading-relaxed font-light">
              ${treatment.shortDesc}
            </p>
          </div>

          <div class="pt-4 border-t border-gold-400/20 flex items-center justify-between gap-3">
            <button onclick="openTreatmentModal('${treatment.id}')" class="text-xs font-semibold text-slate-700 hover:text-gold-600 flex items-center gap-1 transition-colors">
              <span>View Details</span>
              <i data-lucide="arrow-up-right" class="w-4 h-4"></i>
            </button>
            <button onclick="openBookingWithTreatment('${treatment.id}')" class="btn-luxury-gold px-4 py-2 text-xs flex items-center gap-1.5">
              <span>Book</span>
              <i data-lucide="calendar" class="w-3.5 h-3.5"></i>
            </button>
          </div>
        </div>
      `;
      container.appendChild(card);
    });

    initLucide();
  };

  // Initial render
  renderCards('all');

  // Filter button clicks
  filterButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      filterButtons.forEach(b => {
        b.classList.remove('bg-gold-500', 'text-white', 'shadow-md');
        b.classList.add('bg-white', 'text-slate-700', 'border-gold-400/30');
      });

      btn.classList.add('bg-gold-500', 'text-white', 'shadow-md');
      btn.classList.remove('bg-white', 'text-slate-700', 'border-gold-400/30');

      const category = btn.getAttribute('data-filter');
      renderCards(category);
    });
  });
}

/* -------------------------------------------------------------------------- */
/* 4. Featured Skincare Products Spotlight                                   */
/* -------------------------------------------------------------------------- */
function initHomeFeaturedProducts() {
  const container = document.getElementById('home-featured-products');
  if (!container) return;

  const products = typeof getStoredProducts === 'function' ? getStoredProducts() : DEFAULT_PRODUCTS_DATA;
  const featured = products.slice(0, 3);

  container.innerHTML = '';
  featured.forEach((product, index) => {
    const card = document.createElement('div');
    card.className = 'product-card';
    card.setAttribute('data-aos', 'fade-up');
    card.setAttribute('data-aos-delay', `${index * 100}`);

    card.innerHTML = `
      <div class="product-img-wrap">
        <img src="${product.image}" alt="${product.name}" loading="lazy" onerror="this.onerror=null; this.src='Photos/treatments/all treatments.jpg'">
        <div class="absolute top-3.5 left-3.5">
          <span class="text-[10px] uppercase font-bold px-2.5 py-1 rounded-full bg-white/95 text-gold-700 border border-gold-400/30 shadow-sm">
            ${product.tag}
          </span>
        </div>
      </div>

      <div class="p-5 flex-1 flex flex-col justify-between">
        <div>
          <div class="text-[11px] uppercase tracking-wider text-gold-600 font-semibold mb-1">
            ${product.categoryName}
          </div>
          <h3 class="font-serif text-lg font-bold text-slate-900 mb-2 hover:text-gold-700 transition-colors line-clamp-1">
            ${product.name}
          </h3>
          <p class="text-xs text-slate-600 line-clamp-2 mb-3 font-light">
            ${product.shortDesc}
          </p>
        </div>

        <div class="pt-3 border-t border-gold-400/20 flex items-center justify-between">
          <div>
            <span class="text-xs text-slate-500 block">Price</span>
            <span class="font-serif font-bold text-slate-900 text-base">${product.priceFormatted}</span>
          </div>
          <a href="products.html" class="btn-luxury-gold px-4 py-2 text-xs flex items-center gap-1.5">
            <span>Shop Now</span>
            <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
          </a>
        </div>
      </div>
    `;
    container.appendChild(card);
  });

  initLucide();
}

/* -------------------------------------------------------------------------- */
/* 5. Treatment Detail Modal                                                 */
/* -------------------------------------------------------------------------- */
function initTreatmentModal() {
  const modal = document.getElementById('treatment-modal');
  const closeBtn = document.getElementById('close-treatment-modal');

  if (!modal) return;

  const closeModal = () => {
    modal.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
  };

  if (closeBtn) closeBtn.addEventListener('click', closeModal);
  modal.addEventListener('click', (e) => {
    if (e.target === modal) closeModal();
  });

  window.openTreatmentModal = (id) => {
    const treatments = typeof getStoredTreatments === 'function' ? getStoredTreatments() : TREATMENTS_DATA;
    const treatment = treatments.find(t => t.id === id);
    if (!treatment) return;

    document.getElementById('modal-t-img').src = treatment.image;
    document.getElementById('modal-t-img').onerror = function() { this.src = 'Photos/treatments/all treatments.jpg'; };
    document.getElementById('modal-t-tag').textContent = treatment.tag || 'Clinical Standard';
    document.getElementById('modal-t-category').textContent = treatment.categoryName || 'Medical Cosmetology';
    document.getElementById('modal-t-name').textContent = treatment.name;
    document.getElementById('modal-t-desc').textContent = treatment.fullDesc || treatment.shortDesc;
    document.getElementById('modal-t-duration').textContent = treatment.duration || '45 Mins';
    document.getElementById('modal-t-downtime').textContent = treatment.downtime || 'Zero Downtime';
    document.getElementById('modal-t-doctor').textContent = treatment.doctor || 'Dr. Indi';

    // Render benefits list
    const benefitsList = document.getElementById('modal-t-benefits');
    benefitsList.innerHTML = '';
    const benefits = treatment.benefits || [
      "Physician-supervised clinical precision",
      "FDA-cleared medical grade technology",
      "Immediate visible cellular skin enhancement"
    ];

    benefits.forEach(b => {
      const li = document.createElement('li');
      li.className = 'flex items-start gap-2 text-sm text-slate-700';
      li.innerHTML = `
        <i data-lucide="check-circle-2" class="w-4 h-4 text-gold-600 shrink-0 mt-0.5"></i>
        <span>${b}</span>
      `;
      benefitsList.appendChild(li);
    });

    const bookBtn = document.getElementById('modal-t-book-btn');
    if (bookBtn) {
      bookBtn.onclick = () => {
        closeModal();
        openBookingWithTreatment(treatment.id);
      };
    }

    modal.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
    initLucide();
  };
}

/* -------------------------------------------------------------------------- */
/* 6. Virtual Skin & Hair Advisor Quiz                                       */
/* -------------------------------------------------------------------------- */
function initSkinAdvisorQuiz() {
  const concernBtns = document.querySelectorAll('.quiz-concern-btn');
  const typeBtns = document.querySelectorAll('.quiz-type-btn');
  const restartBtn = document.getElementById('quiz-restart-btn');

  let selectedConcern = null;
  let selectedType = null;

  const concernProtocols = {
    acne: {
      title: "Clinical Acne Clearance & Pore Refinement Protocol",
      doctorRec: "Dr. Indi's Targeted Acne Eradication Pathway",
      treatments: ["Doctor-Led Clinical Acne & Scar Revision", "Hollywood Carbon Laser Peel", "Medical HydraFacial"],
      benefits: "Controls active inflammatory outbreaks, unblocks clogged follicles, and reduces red/brown post-acne marks.",
      ctaTreatmentId: "clinical-acne-scar"
    },
    hair: {
      title: "Medical PRP & Follicular Density Restoration",
      doctorRec: "Advanced Autologous Scalp Revival Protocol",
      treatments: ["Medical PRP Hair Follicle Regeneration", "Scalp Detox & Micro-Nutrient Meso Infusion"],
      benefits: "Halts progressive hair thinning, reactivates dormant miniaturized follicles, and fortifies hair shaft thickness.",
      ctaTreatmentId: "prp-hair-restoration"
    },
    pigmentation: {
      title: "Depigmentation & Melasma Restoration Protocol",
      doctorRec: "Medical Brightening & Melanin Inhibition Therapy",
      treatments: ["Targeted Melasma & Pigmentation Correction", "Medical HydraFacial Deluxe", "Laser Toning"],
      benefits: "Fades stubborn dark patches safely without rebound hyperpigmentation under tropical sun conditions.",
      ctaTreatmentId: "melasma-pigmentation-therapy"
    },
    aging: {
      title: "Cellular Bio-Revitalization & Anti-Wrinkle Pathway",
      doctorRec: "Dr. Indi's Bespoke Facial Harmonization & Youth Protocol",
      treatments: ["Vampire Facial (PRP Bio-Revitalization)", "Profhilo® Hyaluronic Remodeling", "Bespoke Dermal Fillers"],
      benefits: "Restores facial elasticity, smoothens dynamic expression lines, and elevates skin firmness naturally.",
      ctaTreatmentId: "vampire-facial-prp"
    },
    glow: {
      title: "Red-Carpet Porcelain Glow & Instant Hydration",
      doctorRec: "Sapphire Signature Radiance Protocol",
      treatments: ["Medical HydraFacial Deluxe", "Hollywood Carbon Laser Peel", "Sapphire Royal IV Glow"],
      benefits: "Instantly revives dull, fatigued skin, deep cleanses pores, and delivers lasting luminous glass-skin glow.",
      ctaTreatmentId: "hydrafacial-deluxe"
    }
  };

  concernBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      selectedConcern = btn.getAttribute('data-concern');
      document.getElementById('quiz-step-1').classList.add('hidden');
      document.getElementById('quiz-step-2').classList.remove('hidden');
    });
  });

  typeBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      selectedType = btn.getAttribute('data-type');
      showResult();
    });
  });

  const showResult = () => {
    document.getElementById('quiz-step-2').classList.add('hidden');
    document.getElementById('quiz-step-3').classList.remove('hidden');

    const protocol = concernProtocols[selectedConcern] || concernProtocols.glow;

    document.getElementById('quiz-protocol-title').textContent = protocol.title;
    document.getElementById('quiz-protocol-doctor').textContent = protocol.doctorRec;
    document.getElementById('quiz-protocol-benefits').textContent = protocol.benefits;

    const list = document.getElementById('quiz-protocol-treatments');
    list.innerHTML = '';
    protocol.treatments.forEach(t => {
      const li = document.createElement('li');
      li.className = 'flex items-center gap-2 text-sm text-slate-800 font-medium';
      li.innerHTML = `
        <i data-lucide="sparkles" class="w-4 h-4 text-gold-600 shrink-0"></i>
        <span>${t}</span>
      `;
      list.appendChild(li);
    });

    const bookBtn = document.getElementById('quiz-book-btn');
    if (bookBtn) {
      bookBtn.onclick = () => {
        openBookingWithTreatment(protocol.ctaTreatmentId);
      };
    }

    initLucide();
  };

  if (restartBtn) {
    restartBtn.addEventListener('click', () => {
      selectedConcern = null;
      selectedType = null;
      document.getElementById('quiz-step-3').classList.add('hidden');
      document.getElementById('quiz-step-1').classList.remove('hidden');
    });
  }
}

/* -------------------------------------------------------------------------- */
/* 7. Multi-Step Appointment Booking System                                 */
/* -------------------------------------------------------------------------- */
function initBookingModal() {
  const modal = document.getElementById('booking-modal');
  const closeBtn = document.getElementById('close-booking-modal');
  const bookingForm = document.getElementById('booking-form');
  const openButtons = document.querySelectorAll('.open-booking-trigger');

  let currentStep = 1;
  const totalSteps = 3;

  // Pre-fill treatment dropdown from treatments database
  const treatmentSelect = document.getElementById('booking-treatment');
  if (treatmentSelect) {
    treatmentSelect.innerHTML = '<option value="" disabled selected>Select Desired Medical Treatment</option>';
    const treatments = typeof getStoredTreatments === 'function' ? getStoredTreatments() : TREATMENTS_DATA;
    treatments.forEach(t => {
      const opt = document.createElement('option');
      opt.value = t.name;
      opt.setAttribute('data-id', t.id);
      opt.textContent = `${t.name} (${t.categoryName || 'Medical'})`;
      treatmentSelect.appendChild(opt);
    });
  }

  // Set min date to today
  const dateInput = document.getElementById('booking-date');
  if (dateInput) {
    const today = new Date().toISOString().split('T')[0];
    dateInput.min = today;
    dateInput.value = today;
  }

  const showStep = (step) => {
    for (let i = 1; i <= totalSteps; i++) {
      const stepEl = document.getElementById(`booking-step-${i}`);
      if (stepEl) {
        if (i === step) {
          stepEl.classList.remove('hidden');
        } else {
          stepEl.classList.add('hidden');
        }
      }
    }
    currentStep = step;
  };

  const openModal = () => {
    showStep(1);
    document.getElementById('booking-success-view').classList.add('hidden');
    document.getElementById('booking-wizard-view').classList.remove('hidden');
    modal.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
    initLucide();
  };

  const closeModal = () => {
    modal.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
  };

  openButtons.forEach(btn => btn.addEventListener('click', openModal));
  if (closeBtn) closeBtn.addEventListener('click', closeModal);
  modal.addEventListener('click', (e) => {
    if (e.target === modal) closeModal();
  });

  window.openBookingWithTreatment = (treatmentId) => {
    openModal();
    if (treatmentSelect) {
      const treatments = typeof getStoredTreatments === 'function' ? getStoredTreatments() : TREATMENTS_DATA;
      const found = treatments.find(t => t.id === treatmentId);
      if (found) {
        treatmentSelect.value = found.name;
      }
    }
  };

  // Step Navigation Buttons
  const nextBtn1 = document.getElementById('step-1-next');
  const prevBtn2 = document.getElementById('step-2-prev');
  const nextBtn2 = document.getElementById('step-2-next');
  const prevBtn3 = document.getElementById('step-3-prev');

  if (nextBtn1) {
    nextBtn1.addEventListener('click', () => {
      const treatment = document.getElementById('booking-treatment').value;
      if (!treatment) {
        alert('Please select a treatment first.');
        return;
      }
      showStep(2);
    });
  }

  if (prevBtn2) {
    prevBtn2.addEventListener('click', () => showStep(1));
  }

  if (nextBtn2) {
    nextBtn2.addEventListener('click', () => {
      const date = document.getElementById('booking-date').value;
      const time = document.getElementById('booking-time').value;
      if (!date || !time) {
        alert('Please pick a preferred date and time slot.');
        return;
      }
      showStep(3);
    });
  }

  if (prevBtn3) {
    prevBtn3.addEventListener('click', () => showStep(2));
  }

  // Handle final submission
  if (bookingForm) {
    bookingForm.addEventListener('submit', async (e) => {
      e.preventDefault();

      const treatment = document.getElementById('booking-treatment').value;
      const doctor = document.getElementById('booking-doctor').value;
      const date = document.getElementById('booking-date').value;
      const time = document.getElementById('booking-time').value;
      const name = document.getElementById('booking-name').value.trim();
      const phone = document.getElementById('booking-phone').value.trim();
      const email = document.getElementById('booking-email').value.trim();
      const notes = document.getElementById('booking-notes').value.trim();

      if (!name || !phone) {
        alert('Please provide your name and contact phone number.');
        return;
      }

      const submitBtn = document.getElementById('booking-submit-btn');
      const originalText = submitBtn.innerHTML;
      submitBtn.innerHTML = `
        <i data-lucide="loader-2" class="w-4 h-4 animate-spin inline mr-1"></i>
        <span>Confirming...</span>
      `;
      submitBtn.disabled = true;

      const bookingRef = "SLA-" + Math.floor(100000 + Math.random() * 900000);

      const bookingPayload = {
        id: bookingRef,
        name,
        phone,
        email,
        treatment,
        doctor,
        date,
        time,
        notes,
        status: "Pending",
        createdAt: new Date().toISOString()
      };

      // Store in localStorage bookings queue for the Admin Dashboard
      try {
        const storedBookings = JSON.parse(localStorage.getItem('sapphire_bookings_log') || '[]');
        storedBookings.unshift(bookingPayload);
        localStorage.setItem('sapphire_bookings_log', JSON.stringify(storedBookings));
      } catch (err) {
        console.error("Failed to log booking in localStorage", err);
      }

      // Generate pre-filled WhatsApp link
      const waMessage = `*Sapphire Luxury Aesthetics Appointment Request*%0A` +
        `Ref ID: ${bookingRef}%0A` +
        `Client: ${encodeURIComponent(name)}%0A` +
        `Phone: ${encodeURIComponent(phone)}%0A` +
        `Treatment: ${encodeURIComponent(treatment)}%0A` +
        `Doctor: ${encodeURIComponent(doctor)}%0A` +
        `Date: ${encodeURIComponent(date)}%0A` +
        `Time: ${encodeURIComponent(time)}%0A` +
        (notes ? `Notes: ${encodeURIComponent(notes)}%0A` : '') +
        `%0APlease confirm my consultation slot. Thank you!`;

      const waUrl = `https://wa.me/94777143626?text=${waMessage}`;

      // Update success view
      document.getElementById('success-client-name').textContent = name;
      document.getElementById('success-booking-ref').textContent = bookingRef;
      document.getElementById('success-treatment').textContent = treatment;
      document.getElementById('success-datetime').textContent = `${date} at ${time}`;
      document.getElementById('success-doctor').textContent = doctor;

      const waDirectBtn = document.getElementById('success-wa-direct-btn');
      if (waDirectBtn) {
        waDirectBtn.href = waUrl;
      }

      // Trigger Confetti
      if (window.confetti) {
        window.confetti({
          particleCount: 80,
          spread: 70,
          origin: { y: 0.6 },
          colors: ['#D4AF37', '#C5A880', '#0C1A30', '#FDFBF7']
        });
      }

      submitBtn.innerHTML = originalText;
      submitBtn.disabled = false;

      // Show success screen
      document.getElementById('booking-wizard-view').classList.add('hidden');
      document.getElementById('booking-success-view').classList.remove('hidden');
      initLucide();
    });
  }
}

/* -------------------------------------------------------------------------- */
/* 8. Interactive Before & After Slider                                      */
/* -------------------------------------------------------------------------- */
function initComparisonSlider() {
  const container = document.getElementById('before-after-slider');
  if (!container) return;

  const afterImg = container.querySelector('.comparison-image-after');
  const handle = container.querySelector('.comparison-slider-handle');

  if (!afterImg || !handle) return;

  let isDragging = false;

  const updateSlider = (clientX) => {
    const rect = container.getBoundingClientRect();
    let x = clientX - rect.left;
    if (x < 0) x = 0;
    if (x > rect.width) x = rect.width;

    const percentage = (x / rect.width) * 100;
    afterImg.style.clipPath = `polygon(0 0, ${percentage}% 0, ${percentage}% 100%, 0 100%)`;
    handle.style.left = `${percentage}%`;
  };

  container.addEventListener('mousedown', (e) => {
    isDragging = true;
    updateSlider(e.clientX);
  });

  window.addEventListener('mouseup', () => {
    isDragging = false;
  });

  window.addEventListener('mousemove', (e) => {
    if (!isDragging) return;
    updateSlider(e.clientX);
  });

  // Touch support for mobile
  container.addEventListener('touchstart', (e) => {
    isDragging = true;
    updateSlider(e.touches[0].clientX);
  });

  window.addEventListener('touchend', () => {
    isDragging = false;
  });

  window.addEventListener('touchmove', (e) => {
    if (!isDragging) return;
    updateSlider(e.touches[0].clientX);
  });
}

/* -------------------------------------------------------------------------- */
/* 9. Testimonials Swiper Slider (Synced with Reviews Database)              */
/* -------------------------------------------------------------------------- */
function initTestimonialsSlider() {
  const wrapper = document.getElementById('reviews-slider-wrapper');
  if (!wrapper) return;

  const reviews = typeof getStoredReviews === 'function' ? getStoredReviews() : DEFAULT_REVIEWS_DATA;

  wrapper.innerHTML = '';
  reviews.forEach(review => {
    const slide = document.createElement('div');
    slide.className = 'swiper-slide';

    const initials = (review.clientName || 'VIP')
      .split(' ')
      .filter(Boolean)
      .map(n => n[0])
      .join('')
      .substring(0, 2)
      .toUpperCase();

    slide.innerHTML = `
      <div class="glass-cream-card rounded-2xl p-7 border border-gold-400/30 flex flex-col justify-between h-full space-y-4">
        <div>
          <!-- Star Rating -->
          <div class="flex items-center gap-1 text-gold-500 mb-3">
            ${Array(review.rating || 5).fill('<i data-lucide="star" class="w-4 h-4 fill-gold-500 text-gold-500"></i>').join('')}
          </div>

          <p class="text-slate-700 text-sm leading-relaxed italic font-light">
            "${review.quote}"
          </p>
        </div>

        <div class="pt-4 border-t border-gold-400/20 flex items-center justify-between">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-gradient-to-br from-cream-200 via-gold-100 to-cream-300 border border-gold-400/50 flex items-center justify-center font-serif font-bold text-xs text-gold-700 shadow-sm shrink-0">
              ${initials}
            </div>
            <div>
              <h4 class="font-serif font-bold text-slate-900 text-sm">${review.clientName}</h4>
              <span class="text-[11px] text-gold-700 font-semibold block">${review.treatment}</span>
            </div>
          </div>
          <span class="text-[10px] text-emerald-700 font-bold bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-200 flex items-center gap-1">
            <i data-lucide="check" class="w-3 h-3"></i>
            <span>Verified</span>
          </span>
        </div>
      </div>
    `;
    wrapper.appendChild(slide);
  });

  if (typeof Swiper !== 'undefined') {
    new Swiper('.testimonials-swiper', {
      slidesPerView: 1,
      spaceBetween: 24,
      loop: true,
      autoplay: {
        delay: 5000,
        disableOnInteraction: false,
      },
      pagination: {
        el: '.swiper-pagination',
        clickable: true,
      },
      navigation: {
        nextEl: '.swiper-button-next',
        prevEl: '.swiper-button-prev',
      },
      breakpoints: {
        768: {
          slidesPerView: 2,
          spaceBetween: 30,
        },
        1024: {
          slidesPerView: 3,
          spaceBetween: 30,
        }
      }
    });
  }
}

/* -------------------------------------------------------------------------- */
/* 10. FAQ Accordion                                                         */
/* -------------------------------------------------------------------------- */
function initFaqAccordion() {
  const faqItems = document.querySelectorAll('.faq-item');

  faqItems.forEach(item => {
    const header = item.querySelector('.faq-header');
    const content = item.querySelector('.faq-content');
    const icon = item.querySelector('.faq-icon');

    if (header && content) {
      header.addEventListener('click', () => {
        const isOpen = !content.classList.contains('hidden');

        // Close other items
        faqItems.forEach(otherItem => {
          const otherContent = otherItem.querySelector('.faq-content');
          const otherIcon = otherItem.querySelector('.faq-icon');
          if (otherContent && otherContent !== content) {
            otherContent.classList.add('hidden');
            if (otherIcon) otherIcon.style.transform = 'rotate(0deg)';
          }
        });

        if (isOpen) {
          content.classList.add('hidden');
          if (icon) icon.style.transform = 'rotate(0deg)';
        } else {
          content.classList.remove('hidden');
          if (icon) icon.style.transform = 'rotate(180deg)';
        }
      });
    }
  });
}

/* -------------------------------------------------------------------------- */
/* 11. Contact & Inquiry Form                                                */
/* -------------------------------------------------------------------------- */
function initContactForm() {
  const form = document.getElementById('contact-form');
  if (!form) return;

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    const name = form.name.value.trim();
    const phone = form.phone.value.trim();
    const treatment = form.treatment.value;
    const message = form.message.value.trim();

    const waMsg = `*Sapphire Luxury Aesthetics Inquiry*%0A` +
      `Name: ${encodeURIComponent(name)}%0A` +
      `Phone: ${encodeURIComponent(phone)}%0A` +
      `Treatment: ${encodeURIComponent(treatment)}%0A` +
      (message ? `Message: ${encodeURIComponent(message)}%0A` : '') +
      `%0APlease provide further details.`;

    window.open(`https://wa.me/94777143626?text=${waMsg}`, '_blank');
    alert('Thank you! Redirecting to WhatsApp Concierge for instant response.');
    form.reset();
  });
}

/* -------------------------------------------------------------------------- */
/* 12. Clinical Stories & Video Reel Controls                                */
/* -------------------------------------------------------------------------- */
function toggleStoryVideo() {
  const video = document.getElementById('clinic-main-video');
  if (!video) return;

  if (video.paused) {
    video.play();
  } else {
    video.pause();
  }
}

function toggleVideoMute() {
  const video = document.getElementById('clinic-main-video');
  const icon = document.getElementById('video-mute-icon');
  if (!video) return;

  video.muted = !video.muted;
  if (icon) {
    icon.setAttribute('data-lucide', video.muted ? 'volume-x' : 'volume-2');
    initLucide();
  }
}
