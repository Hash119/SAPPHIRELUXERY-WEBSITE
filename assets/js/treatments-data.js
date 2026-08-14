/**
 * Sapphire Luxury Aesthetics - Treatments Database
 * Colombo 05, Sri Lanka
 */

const TREATMENTS_DATA = [
  {
    id: "hydrafacial-deluxe",
    name: "Medical HydraFacial Deluxe",
    category: "skin",
    categoryName: "Skin & Facial Aesthetics",
    tag: "Most Popular Glow",
    duration: "45 - 60 Mins",
    downtime: "Zero Downtime",
    suitableFor: "All Skin Types & Dull Skin",
    image: "Photos/treatments/670314283_122212625606360851_3673288927463636939_n.jpg",
    shortDesc: "Multi-stage patented hydradermabrasion that deeply cleanses, extracts deep-seated impurities, and saturates skin with medical-grade hyaluronic acid & peptides.",
    fullDesc: "The Medical HydraFacial at Sapphire Luxury Aesthetics is an advanced clinical facial designed for immediate, camera-ready radiance. Combining gentle vortex exfoliation, painless extraction of blackheads, and vortex-fusion of potent botanical antioxidants, this doctor-supervised treatment reveals porcelain smooth, intensely hydrated skin from session one.",
    benefits: [
      "Deep pore unclogging & blackhead extraction",
      "Instant plumping & diamond glass skin glow",
      "Boosts cellular hydration by over 300%",
      "Safe for sensitive, acne-prone, and mature skin"
    ],
    recommendedSessions: "1 session every 3-4 weeks",
    doctor: "Dr. Indi & Aesthetic Therapists",
    pricingEstimate: "LKR 14,500 upwards"
  },
  {
    id: "clinical-acne-scar",
    name: "Doctor-Led Clinical Acne & Scar Revision",
    category: "skin",
    categoryName: "Skin & Facial Aesthetics",
    tag: "Doctor Specialty",
    duration: "60 Mins",
    downtime: "1 - 2 Days Mild Redness",
    suitableFor: "Active Acne, Cystic Breakouts & Icepick/Boxcar Scars",
    image: "Photos/treatments/acne.jpg",
    gallery: ["Photos/treatments/acne.jpg", "Photos/treatments/acne 1.jpg"],
    shortDesc: "Comprehensive medical protocol combining customized chemical peeling, subcision, and medical microneedling to eradicate active acne and remodel depressed scars.",
    fullDesc: "Spearheaded by Dr. Indi, our clinical acne protocol goes beyond surface treatments. We address the root cause of sebaceous hyperactivity, bacterial colonization, and deep dermal inflammation. For scarring, we deploy fractional collagen induction and targeted peeling to smooth texture and even out post-inflammatory hyperpigmentation.",
    benefits: [
      "Dramatically calms inflamed cystic acne within days",
      "Stimulates new collagen to lift pitted scar depressions",
      "Reduces dark acne spots and hyperpigmentation",
      "Regulates sebum production for long-term breakout prevention"
    ],
    recommendedSessions: "Course of 3 - 6 sessions",
    doctor: "Dr. Indi (MBBS, Aesthetic Specialist)",
    pricingEstimate: "Customized Clinical Plan"
  },
  {
    id: "carbon-laser-peel",
    name: "Hollywood Carbon Laser Peel",
    category: "laser",
    categoryName: "Laser Aesthetics",
    tag: "Instant Event Glow",
    duration: "40 Mins",
    downtime: "None (Immediate return to routine)",
    suitableFor: "Enlarged Pores, Oily Skin & Uneven Tone",
    image: "Photos/treatments/765085280_122224555040360851_9211217183991600567_n.jpg",
    shortDesc: "Liquid carbon applied to the face is vaporized with a Q-switched laser beam, instantly shrinking enlarged pores, blasting oil, and polishing the epidermis.",
    fullDesc: "Beloved by celebrities prior to red-carpet appearances, the Carbon Laser Peel gently purges microscopic debris from pores while photo-thermal energy stimulates sub-dermal collagen. The result is matte, refined, baby-soft skin with an illuminated porcelain finish.",
    benefits: [
      "Instant reduction in open pore visibility",
      "Controls stubborn T-zone oil and removes blackheads",
      "Exfoliates dead surface cells for remarkable skin smoothness",
      "Non-invasive with zero pain"
    ],
    recommendedSessions: "1 session every 2-4 weeks or pre-event",
    doctor: "Laser Aesthetician & Dr. Indi",
    pricingEstimate: "LKR 12,000 upwards"
  },
  {
    id: "prp-hair-restoration",
    name: "Medical PRP Hair Follicle Regeneration",
    category: "hair",
    categoryName: "Hair Restoration",
    tag: "Proven Regrowth",
    duration: "50 Mins",
    downtime: "Minimal (Back to work next day)",
    suitableFor: "Hair Thinning, Androgenetic Alopecia & Receding Lines",
    image: "Photos/treatments/prp.jpg",
    shortDesc: "High-concentration Platelet-Rich Plasma extracted from your own blood, infused with growth factors to awaken dormant hair follicles and thicken hair shafts.",
    fullDesc: "Our medical-grade PRP Hair Protocol isolates the most vital autologous growth factors (VEGF, PDGF, EGF) using dual-spin centrifugation. Micro-injected into thinning scalp zones, it revitalizes miniaturized follicles, promotes microvascular blood supply, and significantly increases hair density and shaft thickness.",
    benefits: [
      "Halts active excessive hair shedding",
      "Stimulates new follicle germination and thickening",
      "100% natural, biocompatible autologous therapy (zero allergy risk)",
      "Doctor-administered with topical numbing for high comfort"
    ],
    recommendedSessions: "3 - 4 sessions spaced 4 weeks apart",
    doctor: "Dr. Indi (Clinical Aesthetics)",
    pricingEstimate: "Package options available"
  },
  {
    id: "vampire-facial-prp",
    name: "Vampire Facial (PRP Skin Bio-Revitalization)",
    category: "anti-aging",
    categoryName: "Anti-Aging & Injectables",
    tag: "Deep Cellular Youth",
    duration: "60 Mins",
    downtime: "24 - 48 Hours Rosy Tint",
    suitableFor: "Fine Lines, Loss of Elasticity & Tired Texture",
    image: "Photos/treatments/prp.jpg",
    shortDesc: "Autologous Platelet-Rich Plasma combined with clinical microneedling to trigger intense collagen synthesis, firming slack skin and erasing fine lines.",
    fullDesc: "Known globally as the gold standard in regenerative skin medicine, the Vampire Facial harnesses your body's innate healing proteins. By introducing concentrated platelets deep into dermal layers, it boosts elastin, refines skin texture, and gives an age-defying luminescent bounce.",
    benefits: [
      "Softens fine lines around eyes, forehead, and smile lines",
      "Restores dermal elasticity and youthful firmness",
      "Diminishes hollow under-eye dark circles",
      "Improves overall skin resilience and cellular vitality"
    ],
    recommendedSessions: "3 sessions at 4-6 week intervals",
    doctor: "Dr. Indi",
    pricingEstimate: "LKR 18,500 upwards"
  },
  {
    id: "triple-laser-hair-removal",
    name: "Triple-Wavelength Painless Laser Hair Removal",
    category: "laser",
    categoryName: "Laser Aesthetics",
    tag: "Permanent Smoothness",
    duration: "15 - 90 Mins (by area)",
    downtime: "None",
    suitableFor: "All Sri Lankan Skin Tones (Fitzpatrick Types III-VI)",
    image: "Photos/treatments/689495112_122215230932360851_8836278638910805268_n.jpg",
    shortDesc: "Advanced cooling diode & triple-wavelength technology targeting hair follicles at different depths for safe, permanent, and silky hair-free skin.",
    fullDesc: "Specially calibrated for Sri Lankan and South Asian skin tones, our gold-standard triple wavelength laser technology (755nm + 808nm + 1064nm) features continuous ICE contact cooling for virtually painless sessions. Targets fine to coarse hair on face, underarms, arms, legs, and full body safely without burns or pigmentation risk.",
    benefits: [
      "Up to 90% permanent hair reduction after completed course",
      "ICE cooling chill tip ensures painless and comfortable treatment",
      "Completely prevents painful ingrown hairs and strawberry skin",
      "Customized parameter settings for dark and sensitive skin"
    ],
    recommendedSessions: "6 - 8 sessions spaced 4-6 weeks apart",
    doctor: "Certified Laser Specialists",
    pricingEstimate: "Per Session & Full Body Packages"
  },
  {
    id: "melasma-pigmentation-therapy",
    name: "Targeted Melasma & Pigmentation Correction",
    category: "skin",
    categoryName: "Skin & Facial Aesthetics",
    tag: "Even Tone Clarity",
    duration: "45 Mins",
    downtime: "Mild 1-day peeling/none",
    suitableFor: "Melasma, Sun Damage, Freckles & Dark Patches",
    image: "Photos/treatments/723824532_122218375352360851_5535142863310888679_n.jpg",
    shortDesc: "Multi-modal depigmentation therapy utilizing clinical brightening solutions, tyrosinase inhibitors, and gentle laser toning to restore flawless skin tone.",
    fullDesc: "Melasma and stubborn pigmentation require careful medical management rather than aggressive bleaching. Dr. Indi designs bespoke depigmenting therapies that inhibit melanin overproduction, break down deep pigment deposits, and strengthen the epidermal barrier against UV flare-ups.",
    benefits: [
      "Fades stubborn patches of melasma and sun spots",
      "Prevents post-inflammatory hyperpigmentation recurrence",
      "Unifies uneven skin tone and brightens complexion",
      "Formulated specifically for tropical sun-exposed skin"
    ],
    recommendedSessions: "Course of 4 - 6 sessions",
    doctor: "Dr. Indi",
    pricingEstimate: "Consultation-based plan"
  },
  {
    id: "anti-wrinkle-botox-fillers",
    name: "Bespoke Dermal Fillers & Anti-Wrinkle Smoothing",
    category: "anti-aging",
    categoryName: "Anti-Aging & Injectables",
    tag: "Natural Refinement",
    duration: "30 - 45 Mins",
    downtime: "None to minimal swelling (24h)",
    suitableFor: "Forehead Lines, Crow's Feet, Lip Volume, Marionette Lines",
    image: "Photos/treatments/683529831_122213803238360851_45627903908010276_n.jpg",
    shortDesc: "Subtle, physician-administered injectables using US FDA-approved products for natural facial contouring, lip enhancement, and wrinkle softening without freezing expressions.",
    fullDesc: "At Sapphire Luxury Aesthetics, our injectable philosophy is 'natural enhancement, never overdone'. Dr. Indi meticulously maps your facial anatomy to relax hyperactive muscles and replace lost midface volume, restoring youthful contours while preserving your authentic facial expressions.",
    benefits: [
      "Smoothes expression lines on forehead, glabella & eye contours",
      "Restores plumpness to lips and lifts sunken cheek volume",
      "Sharpens jawline and softens nasolabial folds",
      "Results last 6 to 18 months depending on treatment"
    ],
    recommendedSessions: "1 session with scheduled 2-week follow-up",
    doctor: "Dr. Indi (MBBS, Certified Injector)",
    pricingEstimate: "Per Unit / Per Syringe"
  },
  {
    id: "luxury-iv-glow-infusion",
    name: "Sapphire Royal IV Vitamin & Glutathione Glow",
    category: "body",
    categoryName: "Body & Luxury Wellness",
    tag: "Full-Body Vitality",
    duration: "45 Mins",
    downtime: "None",
    suitableFor: "Chronic Fatigue, Dull Skin, Low Immunity & Detox",
    image: "Photos/treatments/756342000_122222854274360851_2200434220206988505_n.jpg",
    shortDesc: "Intravenous cocktail of pure master antioxidant Glutathione, high-dose Vitamin C, B-Complex, and minerals delivered directly to your bloodstream for total luminosity.",
    fullDesc: "Relax in our private luxury lounge while our medical staff administers high-grade micronutrient infusions. Bypassing digestion ensures 100% cellular absorption, revitalizing your liver, boosting immunity, neutralizing free radical damage, and illuminating skin from head to toe.",
    benefits: [
      "Full-body skin brightening and radiant glow",
      "Powerful cellular detoxification and antioxidant shield",
      "Boosts energy levels and fights metabolic exhaustion",
      "Enhances collagen synthesis and hair/nail strength"
    ],
    recommendedSessions: "1 infusion weekly or bi-weekly",
    doctor: "Supervised by Medical Team",
    pricingEstimate: "LKR 16,000 upwards"
  },
  {
    id: "scalp-meso-detox",
    name: "Scalp Detox & Micro-Nutrient Meso Infusion",
    category: "hair",
    categoryName: "Hair Restoration",
    tag: "Healthy Roots",
    duration: "45 Mins",
    downtime: "None",
    suitableFor: "Dandruff, Itchy Scalp, Oily Roots & Weak Hair",
    image: "Photos/treatments/765818213_122224221134360851_1101896344531468092_n.jpg",
    shortDesc: "Exfoliating medical scalp peel followed by micro-infusion of Biotin, zinc, amino acids, and peptides to restore optimal follicular microbiome.",
    fullDesc: "Healthy hair begins with a balanced scalp environment. This clinical therapy clears stubborn sebum buildup, eliminates fungal dandruff spores, and infuses essential hair vitamins directly into the root matrix.",
    benefits: [
      "Removes product buildup, dandruff scales, and excess oil",
      "Improves scalp blood circulation and hair anchoring",
      "Strengthens fragile, brittle hair strands from the bulb",
      "Creates a pristine foundation for thicker hair growth"
    ],
    recommendedSessions: "Course of 3 - 5 sessions",
    doctor: "Trichology & Aesthetic Team",
    pricingEstimate: "LKR 11,500 upwards"
  },
  {
    id: "profhilo-skin-booster",
    name: "Profhilo® & Premium Hyaluronic Skin Boosters",
    category: "anti-aging",
    categoryName: "Anti-Aging & Injectables",
    tag: "The Dermal Remodeler",
    duration: "30 Mins",
    downtime: "12 - 24 Hours Small Bumps",
    suitableFor: "Skin Laxity, Crepey Neck, Dehydrated Skin",
    image: "Photos/treatments/743186544_122221328054360851_3914819105912498017_n.jpg",
    shortDesc: "Ultra-pure ultrapure hyaluronic acid injected into strategic bio-aesthetic points (BAP) to stimulate 4 types of collagen and elastin for non-surgical skin remodeling.",
    fullDesc: "Unlike traditional fillers that add volume, Profhilo acts as an internal moisturizing bio-remodeler. It diffuses seamlessly through dermal layers, dramatically improving skin firmness, elasticity, and crepey texture on face, neck, and hands.",
    benefits: [
      "Intensely hydrates and tightens loose, aging skin",
      "Stimulates natural elastin and collagen synthesis",
      "Firms crepey necklines and jawline contour",
      "Leaves skin with a dewy, youthful glass-skin finish"
    ],
    recommendedSessions: "2 sessions spaced 4 weeks apart",
    doctor: "Dr. Indi",
    pricingEstimate: "Premium Clinic Tier"
  },
  {
    id: "full-body-medical-polish",
    name: "Full Body Medical Polish & Skin Radiance Ritual",
    category: "body",
    categoryName: "Body & Luxury Wellness",
    tag: "Silk Skin Finish",
    duration: "75 Mins",
    downtime: "None",
    suitableFor: "Rough Skin Texture, Body Acne, Keratosis Pilaris & Pre-Bridal",
    image: "Photos/treatments/all treatments.jpg",
    shortDesc: "Luxury clinical body exfoliation with fruit enzymes, botanical scrubs, and thermal hydration wrap for silky, even-toned skin all over.",
    fullDesc: "Ideal for bridal glow preparations or seasonal rejuvenation, this therapeutic body ritual exfoliates dead skin layers, treats back/chest acne, softens keratosis pilaris, and locks in deep lipid hydration for flawless softness.",
    benefits: [
      "Smoothes rough elbows, knees, and strawberry legs",
      "Clears body blemishes and post-inflammatory dark marks",
      "Silky soft, uniform skin tone from neck to toes",
      "Perfect pre-wedding and luxury event preparation"
    ],
    recommendedSessions: "Single pre-event session or monthly ritual",
    doctor: "Aesthetic Therapists",
    pricingEstimate: "LKR 15,000 upwards"
  }
];

const CLINIC_INFO = {
  name: "Sapphire Luxury Aesthetics",
  tagline: "Where Medical Science Meets Timeless Elegance",
  subtitle: "Colombo's Premier Medical Cosmetology & Luxury Wellness Center",
  address: "No. 05, Thimbirigasyaya Place, Colombo 05, Sri Lanka",
  phone: "+94 77 714 3626",
  phoneFormatted: "077 714 3626",
  phoneClean: "94777143626",
  email: "sapphirewellnessmedical@gmail.com",
  facebookUrl: "https://web.facebook.com/SapphireLuxuryAesthetics",
  hours: {
    weekdays: "9:00 AM - 6:00 PM",
    saturday: "9:00 AM - 6:00 PM",
    sunday: "9:30 AM - 5:00 PM"
  },
  doctor: {
    name: "Dr. Indi",
    title: "Lead Aesthetic Physician & Clinical Director",
    credentials: "MBBS (SL), Certified in Clinical Aesthetics & Advanced Cosmetology",
    image: "Photos/Dr Indi.jpeg",
    bio: "With extensive clinical dermatology and aesthetic medicine expertise, Dr. Indi leads Sapphire Luxury Aesthetics in delivering scientifically calibrated, physician-led treatments that prioritize natural beauty, patient safety, and transformative skin health."
  }
};

// Helper to get treatments from localStorage or fall back to defaults
function getStoredTreatments() {
  const saved = localStorage.getItem('sapphire_treatments_data');
  if (saved) {
    try {
      return JSON.parse(saved);
    } catch (e) {
      console.error("Failed to parse stored treatments, using defaults", e);
    }
  }
  return TREATMENTS_DATA;
}

function saveStoredTreatments(treatments) {
  localStorage.setItem('sapphire_treatments_data', JSON.stringify(treatments));
}

