/**
 * Sapphire Luxury Aesthetics - Skincare Boutique Script
 * Cart Management, Product Filters, and WhatsApp Checkout
 */

let cart = JSON.parse(localStorage.getItem('sapphire_cart') || '[]');
let currentFilter = 'all';
let currentSort = 'featured';
let searchQuery = '';
let activeModalProduct = null;
let modalQty = 1;

document.addEventListener('DOMContentLoaded', () => {
  initLucide();
  initShopFilters();
  initSearchAndSort();
  initCartDrawer();
  initProductModal();
  renderProducts();
  updateCartUI();
});

function initLucide() {
  if (window.lucide) {
    lucide.createIcons();
  }
}

/* -------------------------------------------------------------------------- */
/* 1. Product Rendering & Filters                                             */
/* -------------------------------------------------------------------------- */
function renderProducts() {
  const container = document.getElementById('shop-products-grid');
  const emptyState = document.getElementById('shop-empty-state');
  if (!container) return;

  const products = typeof getStoredProducts === 'function' ? getStoredProducts() : DEFAULT_PRODUCTS_DATA;

  // Filter by category
  let filtered = currentFilter === 'all' 
    ? products 
    : products.filter(p => p.category === currentFilter);

  // Search query filter
  if (searchQuery.trim()) {
    const q = searchQuery.toLowerCase().trim();
    filtered = filtered.filter(p => 
      p.name.toLowerCase().includes(q) || 
      p.shortDesc.toLowerCase().includes(q) ||
      (p.activeIngredients && p.activeIngredients.some(i => i.toLowerCase().includes(q)))
    );
  }

  // Sorting
  if (currentSort === 'price-low') {
    filtered.sort((a, b) => a.price - b.price);
  } else if (currentSort === 'price-high') {
    filtered.sort((a, b) => b.price - a.price);
  } else if (currentSort === 'rating') {
    filtered.sort((a, b) => (b.rating || 5) - (a.rating || 5));
  }

  container.innerHTML = '';

  if (filtered.length === 0) {
    emptyState.classList.remove('hidden');
    return;
  } else {
    emptyState.classList.add('hidden');
  }

  filtered.forEach(product => {
    const card = document.createElement('div');
    card.className = 'product-card group';

    card.innerHTML = `
      <div class="product-img-wrap">
        <img src="${product.image}" alt="${product.name}" loading="lazy" onerror="this.onerror=null; this.src='Photos/treatments/all treatments.jpg'">
        <div class="absolute top-3.5 left-3.5 z-10">
          <span class="text-[10px] uppercase font-bold px-2.5 py-1 rounded-full bg-white/95 text-gold-700 border border-gold-400/30 shadow-sm backdrop-blur-sm">
            ${product.tag || 'Clinical Standard'}
          </span>
        </div>
        ${product.volume ? `
          <div class="absolute bottom-3 right-3 z-10 text-[10px] bg-slate-900/80 text-white px-2.5 py-0.5 rounded-full backdrop-blur-sm border border-gold-400/20">
            ${product.volume}
          </div>
        ` : ''}
      </div>

      <div class="p-5 flex-1 flex flex-col justify-between space-y-4">
        <div>
          <div class="flex items-center justify-between gap-2 mb-1">
            <span class="text-[11px] uppercase tracking-wider text-gold-600 font-semibold">
              ${product.categoryName || 'Medical Cosmeceutical'}
            </span>
            <div class="flex items-center gap-1 text-gold-500 text-xs">
              <i data-lucide="star" class="w-3.5 h-3.5 fill-gold-500 text-gold-500"></i>
              <span class="text-slate-700 font-bold text-[11px]">${product.rating || '5.0'}</span>
            </div>
          </div>

          <h3 class="font-serif text-lg font-bold text-slate-900 mb-1.5 group-hover:text-gold-700 transition-colors line-clamp-1 cursor-pointer" onclick="openProductModal('${product.id}')">
            ${product.name}
          </h3>

          <p class="text-xs text-slate-600 line-clamp-2 font-light leading-relaxed">
            ${product.shortDesc}
          </p>
        </div>

        <div class="pt-3 border-t border-gold-400/20 flex items-center justify-between gap-2">
          <div>
            <span class="text-[10px] text-slate-400 uppercase tracking-wider block">Price</span>
            <span class="font-serif font-bold text-slate-900 text-base">${product.priceFormatted || 'LKR ' + product.price.toLocaleString()}</span>
          </div>

          <div class="flex items-center gap-2">
            <button onclick="openProductModal('${product.id}')" class="p-2 rounded-full border border-gold-400/40 text-slate-700 hover:text-gold-700 hover:bg-cream-200 transition-colors" title="Quick View">
              <i data-lucide="eye" class="w-4 h-4"></i>
            </button>
            <button onclick="addToCart('${product.id}')" class="btn-luxury-gold px-3.5 py-2 text-xs font-semibold flex items-center gap-1.5 shadow-sm">
              <i data-lucide="shopping-bag" class="w-3.5 h-3.5"></i>
              <span>Add</span>
            </button>
          </div>
        </div>
      </div>
    `;

    container.appendChild(card);
  });

  initLucide();
}

function initShopFilters() {
  const filterBtns = document.querySelectorAll('.shop-filter-btn');
  filterBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      filterBtns.forEach(b => b.classList.remove('active', 'bg-gold-500', 'text-white'));
      btn.classList.add('active', 'bg-gold-500', 'text-white');
      currentFilter = btn.getAttribute('data-filter');
      renderProducts();
    });
  });
}

function initSearchAndSort() {
  const searchInput = document.getElementById('shop-search-input');
  const sortSelect = document.getElementById('shop-sort-select');

  if (searchInput) {
    searchInput.addEventListener('input', (e) => {
      searchQuery = e.target.value;
      renderProducts();
    });
  }

  if (sortSelect) {
    sortSelect.addEventListener('change', (e) => {
      currentSort = e.target.value;
      renderProducts();
    });
  }
}

/* -------------------------------------------------------------------------- */
/* 2. Cart Drawer State & Operations                                          */
/* -------------------------------------------------------------------------- */
function addToCart(productId, quantity = 1) {
  const products = typeof getStoredProducts === 'function' ? getStoredProducts() : DEFAULT_PRODUCTS_DATA;
  const product = products.find(p => p.id === productId);
  if (!product) return;

  const existing = cart.find(item => item.id === productId);
  if (existing) {
    existing.quantity += quantity;
  } else {
    cart.push({
      id: product.id,
      name: product.name,
      price: product.price,
      priceFormatted: product.priceFormatted,
      image: product.image,
      volume: product.volume,
      quantity: quantity
    });
  }

  saveCart();
  updateCartUI();
  openCart();

  // Toast / Vibration
  if (window.confetti) {
    window.confetti({
      particleCount: 30,
      spread: 50,
      origin: { y: 0.8 },
      colors: ['#D4AF37', '#FAF6F0', '#0C1A30']
    });
  }
}

function updateCartQuantity(productId, delta) {
  const item = cart.find(i => i.id === productId);
  if (!item) return;

  item.quantity += delta;
  if (item.quantity <= 0) {
    cart = cart.filter(i => i.id !== productId);
  }

  saveCart();
  updateCartUI();
}

function removeFromCart(productId) {
  cart = cart.filter(i => i.id !== productId);
  saveCart();
  updateCartUI();
}

function saveCart() {
  localStorage.setItem('sapphire_cart', JSON.stringify(cart));
}

function updateCartUI() {
  const counterBadge = document.getElementById('cart-counter-badge');
  const drawerCount = document.getElementById('cart-drawer-count');
  const itemsContainer = document.getElementById('cart-items-container');
  const subtotalEl = document.getElementById('cart-subtotal');
  const deliveryFeeEl = document.getElementById('cart-delivery-fee');
  const totalEl = document.getElementById('cart-total');
  const floatingCount = document.getElementById('floating-cart-count');
  const floatingTotal = document.getElementById('floating-cart-total');
  const deliveryMeterStatus = document.getElementById('delivery-meter-status');
  const deliveryProgressFill = document.getElementById('delivery-progress-fill');

  const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
  const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
  const isFreeDelivery = subtotal >= 20000;
  const deliveryFee = subtotal === 0 ? 0 : (isFreeDelivery ? 0 : 650);
  const grandTotal = subtotal + deliveryFee;

  if (counterBadge) counterBadge.textContent = totalItems;
  if (drawerCount) drawerCount.textContent = `(${totalItems} items)`;
  if (floatingCount) floatingCount.textContent = `${totalItems} Items`;
  if (floatingTotal) floatingTotal.textContent = `LKR ${subtotal.toLocaleString()}`;

  if (subtotalEl) subtotalEl.textContent = `LKR ${subtotal.toLocaleString()}`;
  if (deliveryFeeEl) deliveryFeeEl.textContent = isFreeDelivery ? 'FREE (Eligible)' : 'LKR 650';
  if (totalEl) totalEl.textContent = `LKR ${grandTotal.toLocaleString()}`;

  // Delivery progress meter
  if (deliveryProgressFill && deliveryMeterStatus) {
    const progressPercent = Math.min(100, Math.round((subtotal / 20000) * 100));
    deliveryProgressFill.style.width = `${progressPercent}%`;
    if (isFreeDelivery) {
      deliveryMeterStatus.textContent = '🎉 You unlocked FREE Colombo Delivery!';
      deliveryMeterStatus.classList.add('text-emerald-600');
    } else {
      const remaining = 20000 - subtotal;
      deliveryMeterStatus.textContent = `Add LKR ${remaining.toLocaleString()} for FREE delivery`;
      deliveryMeterStatus.classList.remove('text-emerald-600');
    }
  }

  // Render items
  if (itemsContainer) {
    if (cart.length === 0) {
      itemsContainer.innerHTML = `
        <div class="text-center py-12 text-slate-400 space-y-2">
          <i data-lucide="shopping-bag" class="w-10 h-10 mx-auto text-gold-400/60"></i>
          <p class="text-xs">Your skincare bag is currently empty.</p>
        </div>
      `;
    } else {
      itemsContainer.innerHTML = '';
      cart.forEach(item => {
        const row = document.createElement('div');
        row.className = 'cart-item-row';

        row.innerHTML = `
          <img src="${item.image}" alt="${item.name}" class="w-14 h-14 rounded-xl object-cover border border-gold-400/30 shrink-0" onerror="this.onerror=null; this.src='Photos/treatments/all treatments.jpg'">
          <div class="flex-1 min-w-0">
            <h4 class="font-serif font-bold text-slate-900 text-xs truncate">${item.name}</h4>
            <div class="text-[11px] text-gold-700 font-semibold mt-0.5">LKR ${item.price.toLocaleString()}</div>
            <div class="flex items-center gap-2 mt-2">
              <div class="flex items-center border border-gold-400/30 rounded-full px-2 py-0.5 bg-cream-100 text-xs">
                <button onclick="updateCartQuantity('${item.id}', -1)" class="text-slate-500 hover:text-slate-900 font-bold px-1">-</button>
                <span class="px-2 font-bold text-slate-800 text-xs">${item.quantity}</span>
                <button onclick="updateCartQuantity('${item.id}', 1)" class="text-slate-500 hover:text-slate-900 font-bold px-1">+</button>
              </div>
              <button onclick="removeFromCart('${item.id}')" class="text-[10px] text-red-500 hover:text-red-700 underline">
                Remove
              </button>
            </div>
          </div>
          <div class="text-xs font-bold text-slate-900 shrink-0">
            LKR ${(item.price * item.quantity).toLocaleString()}
          </div>
        `;
        itemsContainer.appendChild(row);
      });
    }
    initLucide();
  }
}

function initCartDrawer() {
  const cartHeaderBtn = document.getElementById('cart-header-btn');
  const closeCartBtn = document.getElementById('close-cart-btn');
  const backdrop = document.getElementById('cart-backdrop');
  const checkoutBtn = document.getElementById('cart-whatsapp-checkout-btn');

  if (cartHeaderBtn) cartHeaderBtn.addEventListener('click', openCart);
  if (closeCartBtn) closeCartBtn.addEventListener('click', closeCart);
  if (backdrop) backdrop.addEventListener('click', closeCart);

  if (checkoutBtn) {
    checkoutBtn.addEventListener('click', () => {
      if (cart.length === 0) {
        alert('Your cart is empty. Please add items before checking out.');
        return;
      }

      const clientName = prompt('Please enter your full name for delivery:');
      if (!clientName) return;

      const clientAddress = prompt('Please enter your delivery address / city:');
      if (!clientAddress) return;

      const orderRef = "SLA-ORD-" + Math.floor(100000 + Math.random() * 900000);
      const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
      const isFreeDelivery = subtotal >= 20000;
      const deliveryFee = isFreeDelivery ? 0 : 650;
      const grandTotal = subtotal + deliveryFee;

      let itemsList = '';
      cart.forEach((i, idx) => {
        itemsList += `${idx + 1}. ${i.name} (x${i.quantity}) - LKR ${(i.price * i.quantity).toLocaleString()}%0A`;
      });

      const waMsg = `*Sapphire Luxury Aesthetics - Skincare Boutique Order*%0A` +
        `Order Ref: ${orderRef}%0A` +
        `Client Name: ${encodeURIComponent(clientName)}%0A` +
        `Delivery Address: ${encodeURIComponent(clientAddress)}%0A%0A` +
        `*Items Ordered:*%0A` +
        itemsList + `%0A` +
        `Subtotal: LKR ${subtotal.toLocaleString()}%0A` +
        `Delivery: ${isFreeDelivery ? 'FREE' : 'LKR 650'}%0A` +
        `*Grand Total: LKR ${grandTotal.toLocaleString()}*%0A%0A` +
        `Please confirm my order and provide payment/bank details for transfer. Thank you!`;

      // Log in order history
      try {
        const storedBookings = JSON.parse(localStorage.getItem('sapphire_bookings_log') || '[]');
        storedBookings.unshift({
          id: orderRef,
          name: clientName,
          phone: "Via WhatsApp",
          treatment: `Product Order (${cart.length} items - LKR ${grandTotal.toLocaleString()})`,
          doctor: "Dr. Indi Formulations",
          date: new Date().toISOString().split('T')[0],
          time: "Boutique Dispatch",
          notes: `Address: ${clientAddress}`,
          status: "Pending",
          createdAt: new Date().toISOString()
        });
        localStorage.setItem('sapphire_bookings_log', JSON.stringify(storedBookings));
      } catch (err) {
        console.error("Failed to log order", err);
      }

      window.open(`https://wa.me/94777143626?text=${waMsg}`, '_blank');
      
      // Clear cart
      cart = [];
      saveCart();
      updateCartUI();
      closeCart();
      alert('Thank you! Your order draft has been generated. Send the message on WhatsApp to finalize delivery.');
    });
  }
}

function openCart() {
  const drawer = document.getElementById('cart-drawer');
  const backdrop = document.getElementById('cart-backdrop');
  if (drawer && backdrop) {
    drawer.classList.remove('translate-x-full');
    backdrop.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
  }
}

function closeCart() {
  const drawer = document.getElementById('cart-drawer');
  const backdrop = document.getElementById('cart-backdrop');
  if (drawer && backdrop) {
    drawer.classList.add('translate-x-full');
    backdrop.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
  }
}

/* -------------------------------------------------------------------------- */
/* 3. Product Quick View Modal                                                */
/* -------------------------------------------------------------------------- */
function initProductModal() {
  const modal = document.getElementById('product-modal');
  const closeBtn = document.getElementById('close-product-modal');
  const minusBtn = document.getElementById('modal-qty-minus');
  const plusBtn = document.getElementById('modal-qty-plus');
  const qtyVal = document.getElementById('modal-qty-val');
  const addBtn = document.getElementById('modal-add-cart-btn');

  if (minusBtn && plusBtn && qtyVal) {
    minusBtn.addEventListener('click', () => {
      if (modalQty > 1) {
        modalQty--;
        qtyVal.textContent = modalQty;
      }
    });
    plusBtn.addEventListener('click', () => {
      modalQty++;
      qtyVal.textContent = modalQty;
    });
  }

  if (addBtn) {
    addBtn.addEventListener('click', () => {
      if (activeModalProduct) {
        addToCart(activeModalProduct.id, modalQty);
        closeProductModal();
      }
    });
  }

  const closeProductModal = () => {
    if (modal) {
      modal.classList.add('hidden');
      document.body.classList.remove('overflow-hidden');
    }
  };

  if (closeBtn) closeBtn.addEventListener('click', closeProductModal);
  if (modal) {
    modal.addEventListener('click', (e) => {
      if (e.target === modal) closeProductModal();
    });
  }

  window.openProductModal = (id) => {
    const products = typeof getStoredProducts === 'function' ? getStoredProducts() : DEFAULT_PRODUCTS_DATA;
    const product = products.find(p => p.id === id);
    if (!product) return;

    activeModalProduct = product;
    modalQty = 1;
    if (qtyVal) qtyVal.textContent = 1;

    document.getElementById('modal-p-img').src = product.image;
    document.getElementById('modal-p-img').onerror = function() { this.src = 'Photos/treatments/all treatments.jpg'; };
    document.getElementById('modal-p-tag').textContent = product.tag || 'Clinical Prescription';
    document.getElementById('modal-p-category').textContent = product.categoryName || 'Medical Cosmeceutical';
    document.getElementById('modal-p-name').textContent = product.name;
    document.getElementById('modal-p-volume').textContent = product.volume || '';
    document.getElementById('modal-p-price').textContent = product.priceFormatted || 'LKR ' + product.price.toLocaleString();
    document.getElementById('modal-p-desc').textContent = product.fullDesc || product.shortDesc;
    document.getElementById('modal-p-how').textContent = product.howToUse || 'Apply as directed by physician.';

    const activesContainer = document.getElementById('modal-p-actives');
    activesContainer.innerHTML = '';
    const actives = product.activeIngredients || ["High-potency bio-actives", "Antioxidant Complex"];
    actives.forEach(act => {
      const pill = document.createElement('span');
      pill.className = 'text-[10px] font-semibold bg-cream-200 text-gold-800 border border-gold-400/30 px-2.5 py-1 rounded-full';
      pill.textContent = act;
      activesContainer.appendChild(pill);
    });

    if (modal) {
      modal.classList.remove('hidden');
      document.body.classList.add('overflow-hidden');
    }
    initLucide();
  };
}
