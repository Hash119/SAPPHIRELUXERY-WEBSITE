/**
 * Sapphire Luxury Aesthetics - Medical Skincare Boutique Database
 * Formulated & Curated by Dr. Indi | Colombo 05, Sri Lanka
 */

const DEFAULT_PRODUCTS_DATA = [
  {
    id: "prod-1",
    name: "Cellular Radiance C+ Ferulic Brightening Elixir",
    category: "brightening",
    categoryName: "Brightening & Pigmentation",
    tag: "Dr. Indi's Signature",
    price: 18500,
    priceFormatted: "LKR 18,500",
    volume: "30ml / 1.0 fl. oz.",
    rating: 5.0,
    reviewsCount: 42,
    image: "Photos/treatments/723824532_122218375352360851_5535142863310888679_n.jpg",
    inStock: true,
    stockCount: 18,
    shortDesc: "Medical-grade 15% L-Ascorbic Acid infused with 1% Alpha-Tocopherol and 0.5% Ferulic Acid to eradicate hyperpigmentation and illuminate porcelain glow.",
    fullDesc: "Specially calibrated for Sri Lankan tropical UV conditions, this clinical antioxidant serum neutralizes oxidative stress, fades stubborn sun spots and melasma, and stimulates collagen synthesis for visibly luminous, glass-smooth skin.",
    activeIngredients: ["15% Pure L-Ascorbic Acid", "1.0% Alpha-Tocopherol (Vit E)", "0.5% Ferulic Acid", "Hyaluronic Acid Multi-Complex"],
    howToUse: "Apply 4-5 drops in the morning to cleansed, dry face and neck prior to moisturizer and sunscreen.",
    suitableFor: "Dull skin, melasma, post-acne dark marks, and uneven skin tone."
  },
  {
    id: "prod-2",
    name: "Ultra-Shield Invisible Fluid Sunscreen SPF 50+ PA++++",
    category: "sun-protection",
    categoryName: "Sun Protection & Barrier",
    tag: "Essential Daily Shield",
    price: 12500,
    priceFormatted: "LKR 12,500",
    volume: "50ml / 1.7 fl. oz.",
    rating: 4.9,
    reviewsCount: 68,
    image: "Photos/treatments/670314283_122212625606360851_3673288927463636939_n.jpg",
    inStock: true,
    stockCount: 25,
    shortDesc: "Non-greasy, 100% invisible broad-spectrum physical & chemical filter with zero white cast, enriched with Niacinamide and Centella Asiatica.",
    fullDesc: "Formulated specifically for humid tropical climates, this ultra-lightweight fluid delivers medical-grade defense against UVA/UVB rays and blue light without clogging pores, triggering breakouts, or leaving an oily residue.",
    activeIngredients: ["Zinc Oxide & Titanium Micronized", "2% Niacinamide", "Centella Asiatica Extract", "Ectoin Anti-Pollution Shield"],
    howToUse: "Apply generously 15 minutes before sun exposure as the final step in your morning routine. Reapply every 3 hours outdoors.",
    suitableFor: "All skin tones, acne-prone skin, sensitive and post-laser treated skin."
  },
  {
    id: "prod-3",
    name: "Clinical Acne Clarifying 2% BHA Pore Detox Cleanser",
    category: "acne",
    categoryName: "Acne & Oil Control",
    tag: "Bestseller for Breakouts",
    price: 9800,
    priceFormatted: "LKR 9,800",
    volume: "150ml / 5.1 fl. oz.",
    rating: 4.9,
    reviewsCount: 54,
    image: "Photos/treatments/acne.jpg",
    inStock: true,
    stockCount: 15,
    shortDesc: "Gentle yet potent exfoliating gel cleanser with 2% Salicylic Acid, Tea Tree Terpenes, and Zinc PCA to banish active pimples and dissolve blackheads.",
    fullDesc: "Penetrates deep into sebaceous follicles to melt hardened sebum, kill acne-causing bacteria, and calm inflamed cystic papules without stripping the epidermal moisture barrier.",
    activeIngredients: ["2.0% Encapsulated Salicylic Acid (BHA)", "Zinc PCA Sebum Regulator", "Australian Melaleuca Oil", "Panthenol (Pro-Vit B5)"],
    howToUse: "Massage a small amount onto damp face in circular motions for 60 seconds morning and evening. Rinse thoroughly with lukewarm water.",
    suitableFor: "Oily, congested, acne-prone skin and enlarged pores."
  },
  {
    id: "prod-4",
    name: "Follicle Revive Scalp Growth Peptide Concentrate",
    category: "hair",
    categoryName: "Hair & Scalp Restoration",
    tag: "Clinical Hair Density",
    price: 22000,
    priceFormatted: "LKR 22,000",
    volume: "60ml / 2.0 fl. oz.",
    rating: 5.0,
    reviewsCount: 37,
    image: "Photos/treatments/prp.jpg",
    inStock: true,
    stockCount: 12,
    shortDesc: "High-potency biometric peptide and caffeine solution to awaken dormant follicles, halt shedding, and double hair shaft density.",
    fullDesc: "Engineered to complement Dr. Indi's in-clinic PRP Hair Therapy, this home concentrate utilizes Redensyl®, Capixyl™, and Copper Tripeptide-1 to reactivate the anagen growth phase of hair follicles.",
    activeIngredients: ["Redensyl® 3%", "Capixyl™ 5% Peptide Complex", "Copper Tripeptide-1 (GHK-Cu)", "Botanical Caffeine & Saw Palmetto"],
    howToUse: "Apply one full dropper directly onto thinning scalp areas once daily at bedtime. Massage gently for 2 minutes. Do not rinse.",
    suitableFor: "Thinning hair, crown hair loss, receding hairline, and post-partum shedding."
  },
  {
    id: "prod-5",
    name: "Advanced Retinoid 0.5% Cellular Youth Night Cream",
    category: "anti-aging",
    categoryName: "Anti-Aging & Rejuvenation",
    tag: "Overnight Transformation",
    price: 21500,
    priceFormatted: "LKR 21,500",
    volume: "50ml / 1.7 fl. oz.",
    rating: 4.8,
    reviewsCount: 29,
    image: "Photos/treatments/765085280_122224555040360851_9211217183991600567_n.jpg",
    inStock: true,
    stockCount: 14,
    shortDesc: "Micro-encapsulated Granactive Retinoid combined with multi-ceramides to erase fine lines, refine texture, and restore youthful firmness without peeling.",
    fullDesc: "Delivers the anti-aging potency of prescription retinoids with zero irritation. Stimulates rapid cellular turnover, thickens the collagen matrix, and smoothes dynamic wrinkles overnight.",
    activeIngredients: ["Hydroxypinacolone Retinoate (Granactive Retinoid 0.5%)", "Ceramide Complex NP/AP/EOP", "Bakuchiol", "Squalane"],
    howToUse: "Apply a pea-sized amount to clean, dry skin at night 2-3 times weekly, gradually increasing to nightly use. Always wear sunscreen during daytime.",
    suitableFor: "Fine lines, loss of elasticity, uneven texture, and mature skin."
  },
  {
    id: "prod-6",
    name: "Hyaluronic Acid Multi-Molecular Hydration Infusion",
    category: "brightening",
    categoryName: "Hydration & Barrier",
    tag: "Instant Glass Skin Glow",
    price: 14000,
    priceFormatted: "LKR 14,000",
    volume: "40ml / 1.35 fl. oz.",
    rating: 4.9,
    reviewsCount: 45,
    image: "Photos/treatments/765818213_122224221134360851_1101896344531468092_n.jpg",
    inStock: true,
    stockCount: 20,
    shortDesc: "5-molecular weight Hyaluronic Acid delivering multi-depth dermal hydration with Provitamin B5 and Snow Mushroom extract for plump, glowing skin.",
    fullDesc: "Restores optimal moisture levels across all 5 layers of the epidermis. Plumps dehydration lines instantly and creates a supple, dewy canvas for porcelain radiance.",
    activeIngredients: ["5D Multi-Molecular Hyaluronic Complex", "Provitamin B5 (Panthenol 5%)", "Tremella Fuciformis (Snow Mushroom)", "Beta-Glucan"],
    howToUse: "Apply 3-4 drops onto damp face and neck morning and evening. Follow with moisturizer to lock in hydration.",
    suitableFor: "Dehydrated skin, post-treatment recovery, sensitive skin, and all skin types."
  }
];

// Helper to get products from localStorage or fall back to defaults
function getStoredProducts() {
  const saved = localStorage.getItem('sapphire_products_data');
  if (saved) {
    try {
      return JSON.parse(saved);
    } catch (e) {
      console.error("Failed to parse stored products, using defaults", e);
    }
  }
  return DEFAULT_PRODUCTS_DATA;
}

function saveStoredProducts(products) {
  localStorage.setItem('sapphire_products_data', JSON.stringify(products));
}
